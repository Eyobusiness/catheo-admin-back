<?php

namespace App\Models;

use App\Traits\Auditable;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tarif extends Model
{
    use Auditable, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'tarifs';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'annee_catechese_id',
        'niveau_id',
        'intitule',
        'description',
        'montant',
        'periode_debut',
        'periode_fin',
        'est_obligatoire',
        'type_tarif',
        'statut',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'periode_debut' => 'date',
        'periode_fin' => 'date',
        'est_obligatoire' => 'boolean',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(CatecheseConfiguration::class, 'paroisse_configuration_id');
    }

    public function anneeCatechese(): BelongsTo
    {
        return $this->belongsTo(AnneeCatechese::class, 'annee_catechese_id');
    }

    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class, 'niveau_id');
    }

    public function niveaux(): BelongsToMany
    {
        return $this->belongsToMany(Niveau::class, 'tarif_niveau', 'tarif_id', 'niveau_id');
    }

    /**
     * Résout le tarif d'inscription approprié pour un niveau, une paroisse et une année pastorale.
     */
    public static function resolveForInscription(
        int|string|null $paroisseId,
        int|string|null $anneeId = null,
        mixed $niveau = null,
        ?string $explicitTarifId = null
    ): ?self {
        // 1. Explicit tarif provided by ID or UUID
        if (!empty($explicitTarifId)) {
            $explicit = is_numeric($explicitTarifId)
                ? self::where('id', (int) $explicitTarifId)->first()
                : self::where('uuid', $explicitTarifId)->first();

            if ($explicit) {
                return $explicit;
            }
        }

        // Convert niveau if object or string/int
        $niveauId = null;
        if ($niveau instanceof Niveau) {
            $niveauId = $niveau->id;
        } elseif (!empty($niveau)) {
            $niveauId = is_numeric($niveau)
                ? (int) $niveau
                : Niveau::where('uuid', $niveau)->value('id');
        }

        // Resolve annee_catechese_id
        if (!empty($anneeId) && !is_numeric($anneeId)) {
            $anneeId = AnneeCatechese::where('uuid', $anneeId)->value('id');
        }

        $baseQuery = self::query()
            ->when($paroisseId, function ($q) use ($paroisseId) {
                $q->where(function ($qp) use ($paroisseId) {
                    $qp->where('paroisse_configuration_id', $paroisseId)
                       ->orWhereNull('paroisse_configuration_id');
                });
            })
            ->where(function ($q) {
                $q->whereNull('statut')
                  ->orWhereIn('statut', ['actif', 'active']);
            })
            ->when($anneeId, function ($q) use ($anneeId) {
                $q->where(function ($qa) use ($anneeId) {
                    $qa->where('annee_catechese_id', $anneeId)
                       ->orWhereNull('annee_catechese_id');
                });
            });

        // 2. Chercher tarif ciblant ce niveau spécifique avec type inscription ou intitulé inscription
        if ($niveauId) {
            $tarifNiveau = (clone $baseQuery)
                ->where(function ($q) {
                    $q->whereIn('type_tarif', ['inscription', 'frais_inscription', 'reinscription'])
                      ->orWhere('type_tarif', 'like', '%inscription%')
                      ->orWhere('intitule', 'like', '%inscription%')
                      ->orWhere('intitule', 'like', '%frais%');
                })
                ->where(function ($q) use ($niveauId) {
                    $q->where('niveau_id', $niveauId)
                      ->orWhereHas('niveaux', fn($nq) => $nq->where('niveaux.id', $niveauId));
                })
                ->orderByRaw('annee_catechese_id IS NULL ASC')
                ->first();

            if ($tarifNiveau) {
                return $tarifNiveau;
            }

            // Chercher n'importe quel tarif ciblant ce niveau (même sans mot clé inscription)
            $tarifNiveauAny = (clone $baseQuery)
                ->where(function ($q) use ($niveauId) {
                    $q->where('niveau_id', $niveauId)
                      ->orWhereHas('niveaux', fn($nq) => $nq->where('niveaux.id', $niveauId));
                })
                ->orderByRaw('est_obligatoire DESC')
                ->orderByRaw('annee_catechese_id IS NULL ASC')
                ->first();

            if ($tarifNiveauAny) {
                return $tarifNiveauAny;
            }
        }

        // 3. Chercher tarif global d'inscription (sans niveau spécifique ou applicable à tous)
        $tarifGlobal = (clone $baseQuery)
            ->where(function ($q) {
                $q->whereIn('type_tarif', ['inscription', 'frais_inscription', 'reinscription'])
                  ->orWhere('type_tarif', 'like', '%inscription%')
                  ->orWhere('intitule', 'like', '%inscription%')
                  ->orWhere('intitule', 'like', '%frais%');
            })
            ->whereNull('niveau_id')
            ->whereDoesntHave('niveaux')
            ->orderByRaw('annee_catechese_id IS NULL ASC')
            ->first();

        if ($tarifGlobal) {
            return $tarifGlobal;
        }

        // 4. Dernier recours : n'importe quel tarif obligatoire global ou premier tarif actif d'inscription
        return (clone $baseQuery)
            ->where(function ($q) {
                $q->whereIn('type_tarif', ['inscription', 'frais_inscription'])
                  ->orWhere('intitule', 'like', '%inscription%');
            })
            ->orderByRaw('est_obligatoire DESC')
            ->orderByRaw('annee_catechese_id IS NULL ASC')
            ->first();
    }
}
