<?php

namespace App\Services\Organisation;

use App\Models\Activite;
use App\Models\CampagnePelerinage;
use App\Models\InscriptionPelerinage;
use App\Models\Organisation;
use App\Models\PaiementPelerinage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class CampagnePelerinageService
{
    /**
     * Génère un code de campagne stable et séquentiel.
     * Convention : PEL-{ANNEE}-{SEQUENCE sur 4 chiffres}
     */
    public function generateCode(): string
    {
        $year = date('Y');
        $prefix = "PEL-{$year}-";

        $countThisYear = CampagnePelerinage::withTrashed()
            ->where('code', 'like', "{$prefix}%")
            ->count();

        return sprintf('%s%04d', $prefix, $countThisYear + 1);
    }

    /**
     * Liste paginée des campagnes d'une organisation avec filtres.
     */
    public function list(Organisation $organisation, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = CampagnePelerinage::with(['activite', 'tarifs'])
            ->withCount('inscriptions')
            ->where('organisation_id', $organisation->id)
            ->latest('id');

        if (!empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        if (!empty($filters['date_depart_min'])) {
            $query->whereDate('date_depart', '>=', $filters['date_depart_min']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('destination', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Récupère une campagne appartenant strictement à l'organisation.
     */
    public function find(Organisation $organisation, int|string $idOrUuid): CampagnePelerinage
    {
        $campagne = CampagnePelerinage::with(['activite', 'tarifs'])
            ->withCount('inscriptions')
            ->where('organisation_id', $organisation->id)
            ->where(function ($q) use ($idOrUuid) {
                if (is_numeric($idOrUuid)) {
                    $q->where('id', $idOrUuid);
                } else {
                    $q->where('uuid', $idOrUuid);
                }
            })
            ->first();

        if (!$campagne) {
            throw new NotFoundHttpException("Campagne de pèlerinage introuvable ou non autorisée pour cette organisation.");
        }

        return $campagne;
    }

    /**
     * Crée une nouvelle campagne de pèlerinage.
     */
    public function create(Organisation $organisation, array $data): CampagnePelerinage
    {
        // Validation stricte du rattachement à l'activité
        if (!empty($data['activite_id'])) {
            $activite = Activite::where('id', $data['activite_id'])
                ->where('organisation_id', $organisation->id)
                ->first();

            if (!$activite) {
                throw new UnprocessableEntityHttpException("L'activité sélectionnée n'appartient pas à la même organisation.");
            }
        }

        $data['organisation_id'] = $organisation->id;
        $data['code'] = $this->generateCode();
        $data['statut'] = $data['statut'] ?? CampagnePelerinage::STATUT_BROUILLON;

        return CampagnePelerinage::create($data);
    }

    /**
     * Met à jour une campagne existante.
     */
    public function update(CampagnePelerinage $campagne, array $data): CampagnePelerinage
    {
        if (isset($data['activite_id']) && !empty($data['activite_id'])) {
            $activite = Activite::where('id', $data['activite_id'])
                ->where('organisation_id', $campagne->organisation_id)
                ->first();

            if (!$activite) {
                throw new UnprocessableEntityHttpException("L'activité sélectionnée n'appartient pas à la même organisation.");
            }
        }

        // Le code et l'organisation ne peuvent jamais être altérés
        unset($data['organisation_id'], $data['code']);

        $campagne->update($data);

        return $campagne->fresh(['activite', 'tarifs']);
    }

    /**
     * Supprime logiquement une campagne.
     */
    public function delete(CampagnePelerinage $campagne): bool
    {
        // Empêcher la suppression si des paiements ont déjà été encaissés
        $hasPaid = $campagne->paiements()
            ->where('paiement_pelerinages.statut', PaiementPelerinage::STATUT_VALIDE)
            ->exists();

        if ($hasPaid) {
            throw new UnprocessableEntityHttpException("Impossible de supprimer une campagne ayant des paiements enregistrés. Veuillez plutôt la clôturer ou l'annuler.");
        }

        return (bool) $campagne->delete();
    }

    /**
     * Ouvre les inscriptions pour la campagne.
     */
    public function ouvrir(CampagnePelerinage $campagne): CampagnePelerinage
    {
        if ($campagne->statut === CampagnePelerinage::STATUT_OUVERTE) {
            return $campagne;
        }

        $campagne->update(['statut' => CampagnePelerinage::STATUT_OUVERTE]);

        return $campagne->fresh();
    }

    /**
     * Clôture de la campagne :
     * - Passe la campagne à cloturee
     * - Identifie les inscriptions CATHEO ou externes impayées (en_attente) et les bascule à annulee
     * - Conserve l'historique intégral sans aucune suppression physique
     */
    public function cloturer(CampagnePelerinage $campagne): array
    {
        return DB::transaction(function () use ($campagne) {
            $campagne->update(['statut' => CampagnePelerinage::STATUT_CLOTUREE]);

            // Récupérer et annuler les inscriptions restées 'en_attente' (aucun paiement)
            $unpaidInscriptions = $campagne->inscriptions()
                ->where('statut_inscription', InscriptionPelerinage::STATUT_EN_ATTENTE)
                ->where('montant_paye', 0)
                ->get();

            $cancelledCount = 0;
            foreach ($unpaidInscriptions as $inscription) {
                $inscription->update([
                    'statut_inscription' => InscriptionPelerinage::STATUT_ANNULEE,
                    'observation'        => trim(($inscription->observation ?? '') . " [Annulation automatique à la clôture de la campagne]"),
                ]);
                $cancelledCount++;
            }

            return [
                'campagne'              => $campagne->fresh(),
                'inscriptions_annulees' => $cancelledCount,
            ];
        });
    }

    /**
     * Annule la campagne.
     */
    public function annuler(CampagnePelerinage $campagne, ?string $observation = null): CampagnePelerinage
    {
        return DB::transaction(function () use ($campagne, $observation) {
            $updateData = ['statut' => CampagnePelerinage::STATUT_ANNULEE];
            if ($observation) {
                $updateData['observation'] = trim(($campagne->observation ?? '') . " [Annulée: {$observation}]");
            }
            $campagne->update($updateData);

            // Basculer les inscriptions non payées à annulee
            $campagne->inscriptions()
                ->where('statut_inscription', InscriptionPelerinage::STATUT_EN_ATTENTE)
                ->update(['statut_inscription' => InscriptionPelerinage::STATUT_ANNULEE]);

            return $campagne->fresh();
        });
    }

    /**
     * Fournit un tableau de bord statistique complet sur la campagne.
     */
    public function getStatistiques(CampagnePelerinage $campagne): array
    {
        $inscriptions = $campagne->inscriptions();

        $totalInscrits = (clone $inscriptions)->count();
        $placesOccupees = $campagne->placesOccupees();
        $capacite = $campagne->capacite;
        $placesRestantes = $capacite ? max(0, $capacite - $placesOccupees) : null;

        $montantAttendu = (float) (clone $inscriptions)
            ->where('statut_inscription', '!=', InscriptionPelerinage::STATUT_ANNULEE)
            ->sum('montant');

        $montantCollecte = (float) $campagne->paiements()
            ->where('paiement_pelerinages.statut', PaiementPelerinage::STATUT_VALIDE)
            ->sum('paiement_pelerinages.montant');

        $soldeRestant = max(0.0, round($montantAttendu - $montantCollecte, 2));

        $repartitionParticipation = [
            'prevue'   => (clone $inscriptions)->where('statut_participation', InscriptionPelerinage::PARTICIPATION_PREVUE)->count(),
            'presente' => (clone $inscriptions)->where('statut_participation', InscriptionPelerinage::PARTICIPATION_PRESENTE)->count(),
            'absente'  => (clone $inscriptions)->where('statut_participation', InscriptionPelerinage::PARTICIPATION_ABSENTE)->count(),
        ];

        $repartitionStatuts = [
            'en_attente'           => (clone $inscriptions)->where('statut_inscription', InscriptionPelerinage::STATUT_EN_ATTENTE)->count(),
            'partiellement_payee'  => (clone $inscriptions)->where('statut_inscription', InscriptionPelerinage::STATUT_PARTIELLEMENT_PAYEE)->count(),
            'payee'                => (clone $inscriptions)->where('statut_inscription', InscriptionPelerinage::STATUT_PAYEE)->count(),
            'annulee'              => (clone $inscriptions)->where('statut_inscription', InscriptionPelerinage::STATUT_ANNULEE)->count(),
        ];

        return [
            'total_inscrits'            => $totalInscrits,
            'capacite'                  => $capacite,
            'places_occupees'           => $placesOccupees,
            'places_restantes'          => $placesRestantes,
            'est_complete'              => $campagne->estComplete(),
            'montant_attendu'           => $montantAttendu,
            'montant_collecte'          => $montantCollecte,
            'solde_restant'             => $soldeRestant,
            'repartition_participation' => $repartitionParticipation,
            'repartition_statuts'       => $repartitionStatuts,
        ];
    }
}
