<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Annonce extends Model
{
    use Auditable, HasAuditFields, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'annonces';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'annee_catechese_id',
        'titre',
        'contenu',
        'cible',
        'cible_type',
        'cible_id',
        'cible_ids',
        'cible_nom',
        'section_id',
        'niveau_id',
        'classe_id',
        'ceb_id',
        'mouvement_id',
        'canal',
        'date_publication',
        'date_diffusion',
        'heure_diffusion',
        'date_expiration',
        'priorite',
        'statut',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'date_publication' => 'date:Y-m-d',
            'date_diffusion'   => 'date:Y-m-d',
            'date_expiration'  => 'date:Y-m-d',
            'cible_ids'        => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Annonce $annonce) {
            if (empty($annonce->date_publication)) {
                $annonce->date_publication = $annonce->date_diffusion ?? now()->toDateString();
            } else {
                $annonce->date_publication = \Illuminate\Support\Carbon::parse($annonce->date_publication)->toDateString();
            }
            if (empty($annonce->date_diffusion)) {
                $annonce->date_diffusion = $annonce->date_publication;
            } else {
                $annonce->date_diffusion = \Illuminate\Support\Carbon::parse($annonce->date_diffusion)->toDateString();
            }
            if (!empty($annonce->date_expiration)) {
                $annonce->date_expiration = \Illuminate\Support\Carbon::parse($annonce->date_expiration)->toDateString();
            }
            if (empty($annonce->cible_type)) {
                $annonce->cible_type = $annonce->cible ? ucfirst($annonce->cible) : 'Tous';
            }
            if (empty($annonce->cible)) {
                $annonce->cible = strtolower($annonce->cible_type);
            }
            if (empty($annonce->canal)) {
                $annonce->canal = 'in_app';
            }
            if (empty($annonce->priorite)) {
                $annonce->priorite = 'normale';
            }
            if (empty($annonce->statut)) {
                $annonce->statut = 'publiee';
            }
        });

        static::updating(function (Annonce $annonce) {
            if (!empty($annonce->date_publication)) {
                $annonce->date_publication = \Illuminate\Support\Carbon::parse($annonce->date_publication)->toDateString();
            }
            if (!empty($annonce->date_diffusion)) {
                $annonce->date_diffusion = \Illuminate\Support\Carbon::parse($annonce->date_diffusion)->toDateString();
            }
            if (!empty($annonce->date_expiration)) {
                $annonce->date_expiration = \Illuminate\Support\Carbon::parse($annonce->date_expiration)->toDateString();
            }
        });
    }

    public function setDatePublicationAttribute($value): void
    {
        $this->attributes['date_publication'] = $value ? \Illuminate\Support\Carbon::parse($value)->toDateString() : null;
    }

    public function setDateDiffusionAttribute($value): void
    {
        $this->attributes['date_diffusion'] = $value ? \Illuminate\Support\Carbon::parse($value)->toDateString() : null;
    }

    public function setDateExpirationAttribute($value): void
    {
        $this->attributes['date_expiration'] = $value ? \Illuminate\Support\Carbon::parse($value)->toDateString() : null;
    }

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(CatecheseConfiguration::class, 'paroisse_configuration_id');
    }

    public function anneeCatechese(): BelongsTo
    {
        return $this->belongsTo(AnneeCatechese::class, 'annee_catechese_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class, 'niveau_id');
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class, 'classe_id');
    }

    public function ceb(): BelongsTo
    {
        return $this->belongsTo(Ceb::class, 'ceb_id');
    }

    public function mouvement(): BelongsTo
    {
        return $this->belongsTo(Mouvement::class, 'mouvement_id');
    }

    public function lectures(): HasMany
    {
        return $this->hasMany(AnnonceLecture::class, 'annonce_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope pour filtrer les annonces visibles par un utilisateur selon son rôle et ses cibles.
     */
    public function scopeForUser(Builder $query, mixed $user): Builder
    {
        if (!$user) {
            return $query->whereRaw('1 = 0');
        }

        // 1. Super Admin & Admin Paroissial
        $isSuperAdmin = ($user->user_type ?? null) === 'super_admin' || ($user->profil?->code ?? null) === 'SUPER_ADMIN';
        $isAdmin = ($user->user_type ?? null) === 'admin' || in_array($user->profil?->code ?? '', ['ADMIN', 'SUPER_ADMIN', 'CURE', 'COORDINATEUR']);

        if ($isSuperAdmin) {
            return $query;
        }

        if ($isAdmin) {
            return $query->where('paroisse_configuration_id', $user->paroisse_configuration_id);
        }

        // 2. Animateur
        if (($user->user_type ?? null) === 'animateur' || $user instanceof Animateur) {
            $animateur = $user instanceof Animateur
                ? $user
                : (Animateur::where('telephone', $user->telephone)->orWhere('email', $user->email)->first() ?? $user);

            $paroisseId = $animateur->paroisse_configuration_id ?? $user->paroisse_configuration_id;

            $affectations = AffectationAnimateur::with('classe.niveau.section')
                ->where('animateur_id', $animateur->id ?? 0)
                ->get();

            $classIds = [];
            $classUuids = [];
            $niveauIds = [];
            $niveauUuids = [];
            $sectionIds = [];
            $sectionUuids = [];

            foreach ($affectations as $aff) {
                if ($aff->classe) {
                    $classIds[] = (string) $aff->classe->id;
                    $classUuids[] = $aff->classe->uuid;
                    if ($aff->classe->niveau) {
                        $niveauIds[] = (string) $aff->classe->niveau->id;
                        $niveauUuids[] = $aff->classe->niveau->uuid;
                        if ($aff->classe->niveau->section) {
                            $sectionIds[] = (string) $aff->classe->niveau->section->id;
                            $sectionUuids[] = $aff->classe->niveau->section->uuid;
                        }
                    }
                }
            }

            return $query->where('paroisse_configuration_id', $paroisseId)
                ->where(function (Builder $sub) use ($classIds, $classUuids, $niveauIds, $niveauUuids, $sectionIds, $sectionUuids) {
                    $sub->whereIn('cible_type', ['TOUS', 'Tous', 'tous'])
                        ->orWhereIn('cible', ['tous', 'animateurs'])
                        ->orWhereIn('cible_type', ['ANIMATEURS', 'Animateurs', 'animateurs']);

                    if (!empty($sectionIds) || !empty($sectionUuids)) {
                        $sub->orWhere(function (Builder $q) use ($sectionIds, $sectionUuids) {
                            $q->whereIn('cible_type', ['SECTION', 'Section', 'section'])
                                ->where(function (Builder $q2) use ($sectionIds, $sectionUuids) {
                                    $q2->whereIn('section_id', $sectionIds)
                                        ->orWhereIn('cible_id', array_merge($sectionIds, $sectionUuids));
                                });
                        });
                    }

                    if (!empty($niveauIds) || !empty($niveauUuids)) {
                        $sub->orWhere(function (Builder $q) use ($niveauIds, $niveauUuids) {
                            $q->whereIn('cible_type', ['NIVEAU', 'Niveau', 'niveau'])
                                ->where(function (Builder $q2) use ($niveauIds, $niveauUuids) {
                                    $q2->whereIn('niveau_id', $niveauIds)
                                        ->orWhereIn('cible_id', array_merge($niveauIds, $niveauUuids));
                                });
                        });
                    }

                    if (!empty($classIds) || !empty($classUuids)) {
                        $sub->orWhere(function (Builder $q) use ($classIds, $classUuids) {
                            $q->whereIn('cible_type', ['CLASSE', 'Classe', 'classe'])
                                ->where(function (Builder $q2) use ($classIds, $classUuids) {
                                    $q2->whereIn('classe_id', $classIds)
                                        ->orWhereIn('cible_id', array_merge($classIds, $classUuids));
                                });
                        });
                    }
                });
        }

        // 3. Parent / Catéchumène
        if (($user->user_type ?? null) === 'parent' || $user instanceof Catechumene) {
            $catechumene = $user instanceof Catechumene
                ? $user
                : ($user->catechumene ?? Catechumene::where('matricule', $user->username ?? '')
                    ->orWhere('telephone_tuteur', $user->telephone ?? '')
                    ->first());

            $paroisseId = $catechumene->paroisse_configuration_id ?? $user->paroisse_configuration_id;

            $inscription = InscriptionAnnuelle::with(['niveau.section', 'classe', 'ceb', 'mouvement'])
                ->where('catechumene_id', $catechumene->id ?? 0)
                ->latest()
                ->first();

            $sectionId = $inscription?->niveau?->section_id ? (string) $inscription->niveau->section_id : null;
            $sectionUuid = $inscription?->niveau?->section?->uuid;
            $niveauId = $inscription?->niveau_id ? (string) $inscription->niveau_id : null;
            $niveauUuid = $inscription?->niveau?->uuid;
            $classeId = $inscription?->classe_id ? (string) $inscription->classe_id : null;
            $classeUuid = $inscription?->classe?->uuid;
            $cebId = $inscription?->ceb_id ? (string) $inscription->ceb_id : ($catechumene?->ceb_id ? (string) $catechumene->ceb_id : null);
            $cebUuid = $inscription?->ceb?->uuid ?? $catechumene?->ceb?->uuid;
            $mouvementId = $inscription?->mouvement_id ? (string) $inscription->mouvement_id : null;
            $mouvementUuid = $inscription?->mouvement?->uuid;

            return $query->where('paroisse_configuration_id', $paroisseId)
                ->where(function (Builder $sub) use ($sectionId, $sectionUuid, $niveauId, $niveauUuid, $classeId, $classeUuid, $cebId, $cebUuid, $mouvementId, $mouvementUuid) {
                    $sub->whereIn('cible_type', ['TOUS', 'Tous', 'tous'])
                        ->orWhereIn('cible', ['tous', 'parents'])
                        ->orWhereIn('cible_type', ['PARENTS', 'Parents', 'parents', 'CATECHUMENES', 'Catéchumènes', 'catechumenes']);

                    if ($sectionId || $sectionUuid) {
                        $sub->orWhere(function (Builder $q) use ($sectionId, $sectionUuid) {
                            $q->whereIn('cible_type', ['SECTION', 'Section', 'section'])
                                ->where(function (Builder $q2) use ($sectionId, $sectionUuid) {
                                    if ($sectionId) $q2->orWhere('section_id', $sectionId)->orWhere('cible_id', $sectionId);
                                    if ($sectionUuid) $q2->orWhere('cible_id', $sectionUuid);
                                });
                        });
                    }

                    if ($niveauId || $niveauUuid) {
                        $sub->orWhere(function (Builder $q) use ($niveauId, $niveauUuid) {
                            $q->whereIn('cible_type', ['NIVEAU', 'Niveau', 'niveau'])
                                ->where(function (Builder $q2) use ($niveauId, $niveauUuid) {
                                    if ($niveauId) $q2->orWhere('niveau_id', $niveauId)->orWhere('cible_id', $niveauId);
                                    if ($niveauUuid) $q2->orWhere('cible_id', $niveauUuid);
                                });
                        });
                    }

                    if ($classeId || $classeUuid) {
                        $sub->orWhere(function (Builder $q) use ($classeId, $classeUuid) {
                            $q->whereIn('cible_type', ['CLASSE', 'Classe', 'classe'])
                                ->where(function (Builder $q2) use ($classeId, $classeUuid) {
                                    if ($classeId) $q2->orWhere('classe_id', $classeId)->orWhere('cible_id', $classeId);
                                    if ($classeUuid) $q2->orWhere('cible_id', $classeUuid);
                                });
                        });
                    }

                    if ($cebId || $cebUuid) {
                        $sub->orWhere(function (Builder $q) use ($cebId, $cebUuid) {
                            $q->whereIn('cible_type', ['CEB', 'CEB', 'ceb'])
                                ->where(function (Builder $q2) use ($cebId, $cebUuid) {
                                    if ($cebId) $q2->orWhere('ceb_id', $cebId)->orWhere('cible_id', $cebId);
                                    if ($cebUuid) $q2->orWhere('cible_id', $cebUuid);
                                });
                        });
                    }

                    if ($mouvementId || $mouvementUuid) {
                        $sub->orWhere(function (Builder $q) use ($mouvementId, $mouvementUuid) {
                            $q->whereIn('cible_type', ['MOUVEMENT', 'Mouvement', 'mouvement'])
                                ->where(function (Builder $q2) use ($mouvementId, $mouvementUuid) {
                                    if ($mouvementId) $q2->orWhere('mouvement_id', $mouvementId)->orWhere('cible_id', $mouvementId);
                                    if ($mouvementUuid) $q2->orWhere('cible_id', $mouvementUuid);
                                });
                        });
                    }
                });
        }

        // Fallback: paroisse notifications
        return $query->where('paroisse_configuration_id', $user->paroisse_configuration_id);
    }

    /**
     * Vérifie si l'annonce a été lue par l'utilisateur.
     */
    public function estLuePar(mixed $user): bool
    {
        if (!$user) {
            return false;
        }

        $query = $this->lectures();

        if ($user instanceof Animateur || ($user->user_type ?? null) === 'animateur') {
            $animateurId = $user instanceof Animateur ? $user->id : ($user->id ?? 0);
            return $query->where('animateur_id', $animateurId)->exists();
        }

        if ($user instanceof Catechumene || ($user->user_type ?? null) === 'parent') {
            $catId = $user instanceof Catechumene ? $user->id : ($user->catechumene_id ?? ($user->catechumene->id ?? 0));
            return $query->where('catechumene_id', $catId)->exists();
        }

        return $query->where('user_id', $user->id)->exists();
    }

    /**
     * Marque l'annonce comme lue par l'utilisateur.
     */
    public function marquerCommeLue(mixed $user): AnnonceLecture
    {
        $userId = null;
        $animateurId = null;
        $catechumeneId = null;

        if ($user instanceof Animateur || ($user->user_type ?? null) === 'animateur') {
            $animateurId = $user instanceof Animateur ? $user->id : ($user->id ?? null);
        } elseif ($user instanceof Catechumene || ($user->user_type ?? null) === 'parent') {
            $catechumeneId = $user instanceof Catechumene ? $user->id : ($user->catechumene_id ?? ($user->catechumene->id ?? null));
        } else {
            $userId = $user->id ?? null;
        }

        return AnnonceLecture::firstOrCreate([
            'annonce_id'     => $this->id,
            'user_id'        => $userId,
            'animateur_id'   => $animateurId,
            'catechumene_id' => $catechumeneId,
        ], [
            'lu_at' => now(),
        ]);
    }
}
