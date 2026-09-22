<?php

namespace App\Services\Organisation;

use App\Models\OperationOrganisation;
use App\Models\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class OrganisationCaisseService
{
    /**
     * Calcule et retourne l'état de caisse de l'organisation pour une période donnée.
     */
    public function getEtatCaisse(Organisation $organisation, array $filters = [], int $perPage = 25): array
    {
        $dateDebut = $filters['date_debut'] ?? null;
        $dateFin = $filters['date_fin'] ?? null;

        // 1. Calcul du solde initial antérieur à la date de début
        $soldeInitial = 0.0;
        if ($dateDebut) {
            $entreesAnterieures = (float) OperationOrganisation::where('organisation_id', $organisation->id)
                ->where('statut', OperationOrganisation::STATUT_VALIDE)
                ->where('type_operation', OperationOrganisation::TYPE_ENTREE)
                ->whereDate('date_operation', '<', $dateDebut)
                ->sum('montant');

            $sortiesAnterieures = (float) OperationOrganisation::where('organisation_id', $organisation->id)
                ->where('statut', OperationOrganisation::STATUT_VALIDE)
                ->where('type_operation', OperationOrganisation::TYPE_SORTIE)
                ->whereDate('date_operation', '<', $dateDebut)
                ->sum('montant');

            $soldeInitial = round($entreesAnterieures - $sortiesAnterieures, 2);
        }

        // 2. Requête sur les opérations de la période
        $query = OperationOrganisation::with(['operateur', 'campagne', 'inscription', 'paiement'])
            ->where('organisation_id', $organisation->id)
            ->where('statut', OperationOrganisation::STATUT_VALIDE)
            ->latest('date_operation');

        if ($dateDebut) {
            $query->whereDate('date_operation', '>=', $dateDebut);
        }

        if ($dateFin) {
            $query->whereDate('date_operation', '<=', $dateFin);
        }

        if (!empty($filters['type_operation'])) {
            $query->where('type_operation', $filters['type_operation']);
        }

        if (!empty($filters['campagne_id'])) {
            $query->where('campagne_pelerinage_id', $filters['campagne_id']);
        }

        // Totaux de la période
        $totalEntreesPeriode = (float) (clone $query)->where('type_operation', OperationOrganisation::TYPE_ENTREE)->sum('montant');
        $totalSortiesPeriode = (float) (clone $query)->where('type_operation', OperationOrganisation::TYPE_SORTIE)->sum('montant');
        $soldePeriode = round($totalEntreesPeriode - $totalSortiesPeriode, 2);
        $soldeFinal = round($soldeInitial + $soldePeriode, 2);

        $nombreOperations = (clone $query)->count();

        // Pagination des opérations
        $operationsPaginees = $query->paginate($perPage);

        return [
            'synthese' => [
                'periode_debut'     => $dateDebut,
                'periode_fin'       => $dateFin,
                'solde_initial'     => $soldeInitial,
                'total_entrees'     => $totalEntreesPeriode,
                'total_sorties'     => $totalSortiesPeriode,
                'solde_periode'     => $soldePeriode,
                'solde_final'       => $soldeFinal,
                'nombre_operations' => $nombreOperations,
            ],
            'operations' => $operationsPaginees,
        ];
    }
}
