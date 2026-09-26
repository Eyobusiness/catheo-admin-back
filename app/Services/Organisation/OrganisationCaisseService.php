<?php

namespace App\Services\Organisation;

use App\Models\CampagnePelerinage;
use App\Models\OperationOrganisation;
use App\Models\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class OrganisationCaisseService
{
    /**
     * Génère une référence séquentielle pour une dépense / opération de caisse.
     */
    public function generateReference(Organisation $organisation, string $prefix = 'DEP'): string
    {
        $year = date('Y');
        $opCount = OperationOrganisation::where('organisation_id', $organisation->id)
            ->where('reference', 'like', "{$prefix}-{$year}-%")
            ->count();

        return sprintf('%s-%s-%04d', $prefix, $year, $opCount + 1);
    }

    /**
     * Enregistre une dépense (décaissement / sortie de caisse) pour l'organisation.
     * Décompte immédiatement et automatiquement du solde de caisse.
     */
    public function createDepense(Organisation $organisation, array $data, ?int $userId = null): OperationOrganisation
    {
        $montant = round((float) $data['montant'], 2);
        if ($montant <= 0) {
            throw new UnprocessableEntityHttpException("Le montant de la dépense doit être supérieur à zéro.");
        }

        // Vérification de la campagne de pèlerinage si rattachée
        $campagneId = null;
        if (!empty($data['campagne_pelerinage_id'])) {
            $campagne = CampagnePelerinage::where('id', $data['campagne_pelerinage_id'])
                ->where('organisation_id', $organisation->id)
                ->first();

            if (!$campagne) {
                throw new UnprocessableEntityHttpException("La campagne de pèlerinage sélectionnée n'appartient pas à votre organisation.");
            }
            $campagneId = $campagne->id;
        }

        $libelle = trim($data['libelle']);
        if (!empty($data['beneficiaire'])) {
            $libelle .= ' (Bénéficiaire : ' . trim($data['beneficiaire']) . ')';
        }

        $reference = $this->generateReference($organisation, 'DEP');

        return DB::transaction(function () use ($organisation, $campagneId, $montant, $libelle, $reference, $data, $userId) {
            return OperationOrganisation::create([
                'organisation_id'        => $organisation->id,
                'campagne_pelerinage_id' => $campagneId,
                'reference'              => $reference,
                'type_operation'         => OperationOrganisation::TYPE_SORTIE,
                'montant'                => $montant,
                'devise'                 => $data['devise'] ?? 'XOF',
                'libelle'                => $libelle,
                'mode_reglement'         => $data['mode_reglement'] ?? 'especes',
                'date_operation'         => !empty($data['date_operation']) ? $data['date_operation'] : now(),
                'statut'                 => OperationOrganisation::STATUT_VALIDE,
                'created_by'             => $userId,
            ]);
        });
    }

    /**
     * Annule une opération de dépense de caisse.
     */
    public function annulerDepense(Organisation $organisation, OperationOrganisation|int|string $operation, ?string $motif = null, ?int $userId = null): OperationOrganisation
    {
        $op = $operation instanceof OperationOrganisation
            ? $operation
            : OperationOrganisation::where('organisation_id', $organisation->id)
                ->where(function ($q) use ($operation) {
                    if (is_numeric($operation)) {
                        $q->where('id', $operation);
                    } else {
                        $q->where('uuid', $operation)->orWhere('reference', $operation);
                    }
                })
                ->first();

        if (!$op) {
            throw new NotFoundHttpException("Opération de caisse introuvable pour cette organisation.");
        }

        if ($op->type_operation !== OperationOrganisation::TYPE_SORTIE) {
            throw new UnprocessableEntityHttpException("Seules les opérations de type sortie (dépense) peuvent être annulées via cette action.");
        }

        if ($op->statut === OperationOrganisation::STATUT_ANNULE) {
            throw new UnprocessableEntityHttpException("Cette dépense est déjà annulée.");
        }

        $op->update([
            'statut'     => OperationOrganisation::STATUT_ANNULE,
            'updated_by' => $userId,
        ]);

        return $op->fresh(['operateur', 'campagne']);
    }

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

        if (!empty($filters['type_operation']) && $filters['type_operation'] !== 'tous') {
            $query->where('type_operation', $filters['type_operation']);
        }

        if (!empty($filters['campagne_id'])) {
            $query->where('campagne_pelerinage_id', $filters['campagne_id']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhere('libelle', 'like', "%{$search}%");
            });
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
