<?php

namespace App\Services\Organisation;

use App\Models\AnneeCatechese;
use App\Models\InscriptionAnnuelle;
use App\Models\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CatheoPopulationService
{
    /**
     * Codes officiels de section dans CATHEO
     */
    public const CODE_ENFANTS_PRIMAIRE = 'SEC-ENFANTS-PRI';
    public const CODE_ENFANTS_COLLEGE  = 'SEC-ENFANTS-COL';
    public const CODE_JEUNES           = 'SEC-JEUNES';
    public const CODE_ADULTES          = 'SEC-ADULTES';

    /**
     * Retourne les codes de section CATHEO autorisés pour une organisation donnée.
     * RÈGLE MÉTIER STRICTE :
     * OPPE -> SEC-ENFANTS-PRI et SEC-ENFANTS-COL
     * OPPJ -> SEC-JEUNES
     * OPPA -> SEC-ADULTES
     */
    public function getTargetSectionCodes(string $typeOrganisation): array
    {
        return match (strtoupper(trim($typeOrganisation))) {
            Organisation::TYPE_OPPE => [self::CODE_ENFANTS_PRIMAIRE, self::CODE_ENFANTS_COLLEGE],
            Organisation::TYPE_OPPJ => [self::CODE_JEUNES],
            Organisation::TYPE_OPPA => [self::CODE_ADULTES],
            default                 => [],
        };
    }

    /**
     * Extrait la population catéchétique de la paroisse correspondant rigoureusement à l'organisation
     * pour l'année pastorale active/courante uniquement.
     */
    public function getPopulation(Organisation $organisation, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $paroisseId = (int) $organisation->paroisse_configuration_id;
        $targetCodes = $this->getTargetSectionCodes($organisation->type_organisation);

        // Récupérer l'année pastorale en cours/active de la paroisse
        $anneeCourante = AnneeCatechese::getAnneeCourante($paroisseId);

        // Si aucune année active trouvée, renvoyer une pagination vide
        if (!$anneeCourante || empty($targetCodes)) {
            return InscriptionAnnuelle::whereRaw('1 = 0')->paginate($perPage);
        }

        $query = InscriptionAnnuelle::with([
            'catechumene',
            'section',
            'niveau',
            'classe',
            'anneeCatechese',
        ])
        ->where('paroisse_configuration_id', $paroisseId)
        ->where('annee_catechese_id', $anneeCourante->id)
        ->whereHas('section', function ($q) use ($targetCodes) {
            $q->whereIn('code', $targetCodes);
        })
        ->latest('id');

        // Filtres optionnels
        if (!empty($filters['niveau_id'])) {
            $query->where('niveau_id', $filters['niveau_id']);
        }

        if (!empty($filters['classe_id'])) {
            $query->where('classe_id', $filters['classe_id']);
        }

        if (!empty($filters['sexe'])) {
            $sexe = strtoupper(trim($filters['sexe']));
            $query->whereHas('catechumene', function ($q) use ($sexe) {
                $q->where('sexe', $sexe);
            });
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->whereHas('catechumene', function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenoms', 'like', "%{$search}%")
                  ->orWhere('matricule', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }
}
