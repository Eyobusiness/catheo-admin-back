<?php

namespace App\Services\SuperAdmin;

use App\Models\Abonnement;
use App\Models\CatecheseConfiguration;
use App\Models\Formule;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SuperAdminAbonnementService
{
    public function __construct(
        protected SuperAdminBillingService $billingService
    ) {}

    /**
     * Liste des abonnements avec filtres.
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Abonnement::with(['paroisse', 'formule.produit', 'echeances.paiements'])
            ->latest('date_debut');

        if (!empty($filters['paroisse_id'])) {
            $pVal = $filters['paroisse_id'];
            $pId = is_numeric($pVal) ? (int) $pVal : CatecheseConfiguration::where('uuid', $pVal)->value('id');
            if ($pId) {
                $query->where('paroisse_configuration_id', $pId);
            }
        }

        if (!empty($filters['produit_id'])) {
            $prodId = $filters['produit_id'];
            $query->whereHas('formule', function ($q) use ($prodId) {
                $q->where('produit_id', $prodId);
            });
        }

        if (!empty($filters['statut']) && $filters['statut'] !== 'tous') {
            $query->where('statut', $filters['statut']);
        }

        return $query->paginate($perPage);
    }

    /**
     * Souscription d'une paroisse à une formule de produit.
     */
    public function souscrire(array $data): Abonnement
    {
        $paroisseId = (int) $data['paroisse_configuration_id'];
        $formuleId  = (int) $data['formule_id'];

        $paroisse = CatecheseConfiguration::findOrFail($paroisseId);
        $formule  = Formule::with('produit')->findOrFail($formuleId);

        return DB::transaction(function () use ($paroisse, $formule, $data) {
            // Snapshot du montant et de la devise
            $montantSnapshot = (float) $formule->montant;
            $deviseSnapshot  = $formule->devise;

            $dateDebut = !empty($data['date_debut']) ? Carbon::parse($data['date_debut']) : now();
            $dateFin   = !empty($data['date_fin']) ? Carbon::parse($data['date_fin']) : (
                ($formule->periodicite === Formule::PERIODICITE_MENSUELLE)
                    ? $dateDebut->copy()->addMonth()->subDay()
                    : $dateDebut->copy()->addYear()->subDay()
            );

            // Pour une formule gratuite : activation directe sans échéance
            // Pour une formule payante : statut en_attente jusqu'au premier encaissement
            $statutInitial = $formule->est_gratuite ? Abonnement::STATUT_ACTIF : Abonnement::STATUT_EN_ATTENTE;

            $abonnement = Abonnement::create([
                'paroisse_configuration_id' => $paroisse->id,
                'formule_id'                => $formule->id,
                'reference'                 => $this->billingService->generateAbonnementReference(),
                'date_debut'                => $dateDebut->toDateString(),
                'date_fin'                  => $dateFin ? $dateFin->toDateString() : null,
                'statut'                    => $statutInitial,
                'montant'                   => $montantSnapshot,
                'devise'                    => $deviseSnapshot,
                'renouvellement_automatique'=> $data['renouvellement_automatique'] ?? true,
                'observation'               => $data['observation'] ?? null,
            ]);

            // Si payant, créer la première échéance (et facture)
            if (!$formule->est_gratuite) {
                $echeance = $this->billingService->createInitialEcheance($abonnement);
                $this->billingService->createFactureForEcheance($echeance);
            }

            return $abonnement->load(['paroisse', 'formule.produit', 'echeances.facture']);
        });
    }

    /**
     * Changement de statut d'un abonnement.
     */
    public function changerStatut(Abonnement $abonnement, string $nouveauStatut, ?string $observation = null): Abonnement
    {
        if (!in_array($nouveauStatut, Abonnement::STATUTS, true)) {
            throw new InvalidArgumentException("Statut d'abonnement invalide [{$nouveauStatut}].");
        }

        $abonnement->update([
            'statut'      => $nouveauStatut,
            'observation' => $observation ? trim($abonnement->observation . "\n" . $observation) : $abonnement->observation,
        ]);

        return $abonnement->fresh(['paroisse', 'formule.produit']);
    }

    /**
     * Résiliation d'un abonnement.
     */
    public function resilier(Abonnement $abonnement, array $resiliationData): Abonnement
    {
        $abonnement->update([
            'statut'            => Abonnement::STATUT_RESILIE,
            'date_resiliation'  => $resiliationData['date_resiliation'] ?? now()->toDateString(),
            'motif_resiliation' => $resiliationData['motif_resiliation'] ?? 'Résiliation demandée par l\'administrateur.',
            'observation'       => !empty($resiliationData['observation'])
                ? trim($abonnement->observation . "\n" . $resiliationData['observation'])
                : $abonnement->observation,
        ]);

        return $abonnement->fresh(['paroisse', 'formule.produit']);
    }
}
