<?php

namespace App\Services\Organisation;

use App\Models\CampagnePelerinage;
use App\Models\InscriptionPelerinage;
use App\Models\OperationOrganisation;
use App\Models\PaiementPelerinage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PaiementPelerinageService
{
    /**
     * Génère une référence séquentielle unique pour le paiement.
     * Convention : PAI-{CAMPAGNE_CODE}-{SEQUENCE sur 4 chiffres}
     */
    public function generateReference(CampagnePelerinage $campagne): string
    {
        $prefix = "PAI-{$campagne->code}-";

        $count = PaiementPelerinage::withTrashed()
            ->whereHas('inscription', function ($q) use ($campagne) {
                $q->where('campagne_pelerinage_id', $campagne->id);
            })
            ->count();

        return sprintf('%s%04d', $prefix, $count + 1);
    }

    /**
     * Liste paginée des paiements au niveau d'une campagne complète.
     */
    public function list(CampagnePelerinage $campagne, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = PaiementPelerinage::with(['inscription.tarif', 'caissier'])
            ->whereHas('inscription', function ($q) use ($campagne) {
                $q->where('campagne_pelerinage_id', $campagne->id);
            })
            ->latest('id');

        if (!empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        if (!empty($filters['mode_paiement'])) {
            $query->where('mode_paiement', $filters['mode_paiement']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhere('reference_transaction', 'like', "%{$search}%")
                  ->orWhereHas('inscription', function ($sub) use ($search) {
                      $sub->where('nom', 'like', "%{$search}%")
                          ->orWhere('prenoms', 'like', "%{$search}%")
                          ->orWhere('reference', 'like', "%{$search}%");
                  });
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Liste des paiements pour une inscription donnée.
     */
    public function listForInscription(InscriptionPelerinage $inscription): Collection
    {
        return $inscription->paiements()->with('caissier')->latest('id')->get();
    }

    /**
     * Récupère un paiement pour une campagne donnée.
     */
    public function find(CampagnePelerinage $campagne, int|string $idOrUuid): PaiementPelerinage
    {
        $paiement = PaiementPelerinage::with(['inscription.tarif', 'caissier'])
            ->whereHas('inscription', function ($q) use ($campagne) {
                $q->where('campagne_pelerinage_id', $campagne->id);
            })
            ->where(function ($q) use ($idOrUuid) {
                if (is_numeric($idOrUuid)) {
                    $q->where('id', $idOrUuid);
                } else {
                    $q->where('uuid', $idOrUuid);
                }
            })
            ->first();

        if (!$paiement) {
            throw new NotFoundHttpException("Paiement introuvable pour cette campagne de pèlerinage.");
        }

        return $paiement;
    }

    /**
     * Enregistre un paiement (acompte, paiement partiel ou solde) de manière transactionnelle.
     */
    public function create(InscriptionPelerinage $inscription, array $data, int|string|null $userId = null): array
    {
        // 1. Contrôles préalables sur l'inscription
        if ($inscription->statut_inscription === InscriptionPelerinage::STATUT_ANNULEE) {
            throw new UnprocessableEntityHttpException("Impossible d'enregistrer un paiement pour une inscription annulée.");
        }

        $campagne = $inscription->campagne;
        if (in_array($campagne->statut, [CampagnePelerinage::STATUT_CLOTUREE, CampagnePelerinage::STATUT_ANNULEE])) {
            throw new UnprocessableEntityHttpException("Impossible d'enregistrer un paiement sur une campagne {$campagne->statut}.");
        }

        $montant = round((float) $data['montant'], 2);
        if ($montant <= 0) {
            throw new UnprocessableEntityHttpException("Le montant du paiement doit être supérieur à zéro.");
        }

        $resteAPayer = (float) $inscription->reste_a_payer;
        if ($montant > $resteAPayer) {
            throw new UnprocessableEntityHttpException(
                "Le montant ({$montant} F) dépasse le solde restant à payer ({$resteAPayer} F) pour cette inscription."
            );
        }

        return DB::transaction(function () use ($inscription, $campagne, $data, $montant, $userId) {
            // 2. Création du paiement de pèlerinage
            $reference = $this->generateReference($campagne);

            $paiement = PaiementPelerinage::create([
                'inscription_pelerinage_id' => $inscription->id,
                'reference'                 => $reference,
                'montant'                   => $montant,
                'devise'                    => $data['devise'] ?? 'XOF',
                'mode_paiement'             => $data['mode_paiement'],
                'date_paiement'             => $data['date_paiement'] ?? now(),
                'statut'                    => PaiementPelerinage::STATUT_VALIDE,
                'reference_transaction'     => $data['reference_transaction'] ?? null,
                'observation'               => $data['observation'] ?? null,
                'created_by'                => $userId,
            ]);

            // 3. Recalcul automatique des montants et du statut d'inscription
            $inscription->recalculerMontants();

            // 4. Enregistrement de l'opération financière d'entrée dans la caisse de l'organisation
            $opCount = OperationOrganisation::where('organisation_id', $campagne->organisation_id)->count();
            $opRef = sprintf('OP-ORG-%s-%04d', date('Y'), $opCount + 1);

            OperationOrganisation::create([
                'organisation_id'           => $campagne->organisation_id,
                'campagne_pelerinage_id'    => $campagne->id,
                'inscription_pelerinage_id' => $inscription->id,
                'paiement_pelerinage_id'    => $paiement->id,
                'reference'                 => $opRef,
                'type_operation'            => OperationOrganisation::TYPE_ENTREE,
                'montant'                   => $montant,
                'devise'                    => $data['devise'] ?? 'XOF',
                'libelle'                   => "Paiement pèlerinage [{$inscription->nom} {$inscription->prenoms}] - Ref: {$reference}",
                'mode_reglement'            => $data['mode_paiement'],
                'date_operation'            => $paiement->date_paiement,
                'statut'                    => OperationOrganisation::STATUT_VALIDE,
                'created_by'                => $userId,
            ]);

            return [
                'paiement'    => $paiement->load('caissier'),
                'inscription' => $inscription->fresh(['tarif']),
            ];
        });
    }

    /**
     * Annule un paiement de pèlerinage et recalcule le solde de l'inscription.
     */
    public function annuler(PaiementPelerinage $paiement, ?string $motif = null, int|string|null $userId = null): array
    {
        if ($paiement->statut === PaiementPelerinage::STATUT_ANNULE) {
            throw new UnprocessableEntityHttpException("Ce paiement est déjà annulé.");
        }

        return DB::transaction(function () use ($paiement, $motif, $userId) {
            $inscription = $paiement->inscription;

            $updateData = ['statut' => PaiementPelerinage::STATUT_ANNULE];
            if ($motif) {
                $updateData['observation'] = trim(($paiement->observation ?? '') . " [Annulé: {$motif}]");
            }
            $paiement->update($updateData);

            // Annuler l'opération financière correspondante
            OperationOrganisation::where('paiement_pelerinage_id', $paiement->id)
                ->update(['statut' => OperationOrganisation::STATUT_ANNULE]);

            // Recalculer les montants de l'inscription
            $inscription->recalculerMontants();

            return [
                'paiement'    => $paiement->fresh(),
                'inscription' => $inscription->fresh(),
            ];
        });
    }
}
