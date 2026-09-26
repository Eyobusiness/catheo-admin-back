<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use InvalidArgumentException;

class Organisation extends Model
{
    use Auditable, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'organisations';

    public const TYPE_OPPE = 'OPPE';
    public const TYPE_OPPJ = 'OPPJ';
    public const TYPE_OPPA = 'OPPA';

    public const TYPES = [
        self::TYPE_OPPE,
        self::TYPE_OPPJ,
        self::TYPE_OPPA,
    ];

    protected $fillable = [
        'uuid',
        'mode',
        'paroisse_configuration_id',
        'produit_id',
        'type_organisation',
        'code',
        'nom',
        'description',
        'logo_path',
        'telephone',
        'email',
        'adresse',
        'responsable_nom',
        'responsable_telephone',
        'responsable_email',
        'statut',
        'date_activation',
        'date_desactivation',
    ];

    public function isIndependant(): bool
    {
        return $this->mode === 'independant' || empty($this->paroisse_configuration_id);
    }

    public function isLiee(): bool
    {
        return !$this->isIndependant();
    }

    protected $casts = [
        'date_activation'   => 'date',
        'date_desactivation' => 'date',
    ];

    protected $appends = [
        'logo_url',
    ];

    /**
     * URL publique du logo de l'organisation.
     */
    public function getLogoUrlAttribute(): ?string
    {
        if (empty($this->logo_path)) {
            return null;
        }

        if (str_starts_with($this->logo_path, 'http://') || str_starts_with($this->logo_path, 'https://')) {
            return $this->logo_path;
        }

        if (str_contains($this->logo_path, '/')) {
            return asset('storage/' . ltrim($this->logo_path, '/'));
        }

        return asset('storage/organisations/logos/' . $this->logo_path);
    }

    protected static function booted(): void
    {
        static::saving(function (Organisation $organisation) {
            $type = strtoupper(trim((string) $organisation->type_organisation));
            $organisation->type_organisation = $type;

            if (!in_array($type, self::TYPES, true)) {
                throw new InvalidArgumentException("Type d'organisation invalide [{$type}]. Les types autorisés sont : " . implode(', ', self::TYPES));
            }

            // Déterminer le mode : 'independant' si pas de paroisse liée
            if (empty($organisation->paroisse_configuration_id)) {
                $organisation->mode = 'independant';
            } else {
                $organisation->mode = $organisation->mode ?? 'liee';
            }

            // Vérification applicative d'unicité active par paroisse (seulement pour organisation liée à une paroisse)
            if (!empty($organisation->paroisse_configuration_id)) {
                $existingQuery = static::where('paroisse_configuration_id', $organisation->paroisse_configuration_id)
                    ->where('type_organisation', $type);

                if ($organisation->exists) {
                    $existingQuery->where('id', '!=', $organisation->id);
                }

                if ($existingQuery->exists()) {
                    throw new InvalidArgumentException("Une organisation active de type [{$type}] existe déjà pour cette paroisse.");
                }
            }
        });
    }

    /**
     * Résolution robuste pour Route Model Binding (UUID ou ID numérique).
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return is_numeric($value)
            ? $this->where('id', $value)->first()
            : $this->where('uuid', $value)->first()
            ?? parent::resolveRouteBinding($value, $field);
    }

    /**
     * Paroisse parente hébergeant cette organisation.
     */
    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(CatecheseConfiguration::class, 'paroisse_configuration_id');
    }

    /**
     * Produit SaaS associé (OPPE, OPPJ, OPPA).
     */
    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }

    /**
     * Utilisateurs rattachés directement à cette organisation.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'organisation_id');
    }

    /**
     * Membres appartenant à cette organisation.
     */
    public function membres(): HasMany
    {
        return $this->hasMany(Membre::class, 'organisation_id');
    }

    /**
     * Activités organisées par cette organisation.
     */
    public function activites(): HasMany
    {
        return $this->hasMany(Activite::class, 'organisation_id');
    }

    /**
     * Campagnes de pèlerinage organisées par cette organisation.
     */
    public function campagnesPelerinage(): HasMany
    {
        return $this->hasMany(CampagnePelerinage::class, 'organisation_id');
    }

    /**
     * Opérations financières propres à cette organisation.
     */
    public function operations(): HasMany
    {
        return $this->hasMany(OperationOrganisation::class, 'organisation_id');
    }

    /**
     * Abonnements souscrits pour cette organisation.
     */
    public function abonnements(): HasMany
    {
        return $this->hasMany(Abonnement::class, 'organisation_id');
    }

    /**
     * Dernier abonnement actif de l'organisation.
     */
    public function abonnementActif()
    {
        return $this->hasOne(Abonnement::class, 'organisation_id')->where('statut', Abonnement::STATUT_ACTIF)->latestOfMany();
    }

    /**
     * Scope pour filtrer les organisations actives.
     */
    public function scopeActif($query)
    {
        return $query->where('statut', 'actif');
    }
}
