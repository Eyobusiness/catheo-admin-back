<?php

namespace App\Services\Organisation;

use App\Models\AnneeCatechese;
use App\Models\InscriptionAnnuelle;
use App\Models\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CatheoPopulationService
{
    /**
     * Codes officiels et variantes des sections dans CATHEO
     */
    public const CODE_ENFANTS_PRIMAIRE     = 'SEC-ENFANTS-PRI';
    public const CODE_ENFANTS_PRIMAIRE_ALT = 'SEC-ENF-PRI';
    public const CODES_ENFANTS_PRIMAIRE    = ['SEC-ENFANTS-PRI', 'SEC-ENF-PRI'];

    public const CODE_ENFANTS_COLLEGE      = 'SEC-ENFANTS-COL';
    public const CODE_ENFANTS_COLLEGE_ALT  = 'SEC-ENF-COL';
    public const CODES_ENFANTS_COLLEGE     = ['SEC-ENFANTS-COL', 'SEC-ENF-COL'];

    public const CODE_JEUNES               = 'SEC-JEUNES';
    public const CODE_JEUNES_ALT           = 'SEC-JEUNE';
    public const CODES_JEUNES              = ['SEC-JEUNES', 'SEC-JEUNE'];

    public const CODE_ADULTES              = 'SEC-ADULTES';
    public const CODE_ADULTES_ALT          = 'SEC-ADULTE';
    public const CODES_ADULTES             = ['SEC-ADULTES', 'SEC-ADULTE'];

    /**
     * Retourne tous les codes de section CATHEO autorisés pour une organisation donnée.
     * RÈGLE MÉTIER STRICTE :
     * OPPE -> Enfants Primaire + Enfants Collège (SEC-ENFANTS-PRI, SEC-ENF-PRI, SEC-ENFANTS-COL, SEC-ENF-COL)
     * OPPJ -> Jeunes (SEC-JEUNES, SEC-JEUNE)
     * OPPA -> Adultes (SEC-ADULTES, SEC-ADULTE)
     */
    public function getTargetSectionCodes(string $typeOrganisation): array
    {
        return match (strtoupper(trim($typeOrganisation))) {
            Organisation::TYPE_OPPE => array_merge(self::CODES_ENFANTS_PRIMAIRE, self::CODES_ENFANTS_COLLEGE),
            Organisation::TYPE_OPPJ => self::CODES_JEUNES,
            Organisation::TYPE_OPPA => self::CODES_ADULTES,
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

        // Si aucune année active trouvée ou pas de paroisse liée, renvoyer une pagination vide
        if (!$anneeCourante || empty($targetCodes) || !$paroisseId) {
            return InscriptionAnnuelle::whereRaw('1 = 0')->paginate($perPage);
        }

        $typeOrg = strtoupper($organisation->type_organisation);

        $query = InscriptionAnnuelle::with([
            'catechumene',
            'section',
            'niveau',
            'classe',
            'anneeCatechese',
        ])
        ->where('paroisse_configuration_id', $paroisseId)
        ->where('annee_catechese_id', $anneeCourante->id)
        ->whereHas('section', function ($q) use ($targetCodes, $typeOrg) {
            $q->where(function ($sub) use ($targetCodes, $typeOrg) {
                $sub->whereIn('code', $targetCodes);
                if ($typeOrg === Organisation::TYPE_OPPE) {
                    $sub->orWhere('code', 'like', 'SEC-ENF%')
                        ->orWhere('nom', 'like', '%enfant%')
                        ->orWhere('nom', 'like', '%primaire%')
                        ->orWhere('nom', 'like', '%college%')
                        ->orWhere('nom', 'like', '%collège%');
                } elseif ($typeOrg === Organisation::TYPE_OPPJ) {
                    $sub->orWhere('code', 'like', 'SEC-JEUN%')
                        ->orWhere('nom', 'like', '%jeune%');
                } elseif ($typeOrg === Organisation::TYPE_OPPA) {
                    $sub->orWhere('code', 'like', 'SEC-ADULT%')
                        ->orWhere('nom', 'like', '%adulte%');
                }
            });
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