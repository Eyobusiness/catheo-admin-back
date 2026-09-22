<?php

namespace App\Services\SuperAdmin;

use App\Models\Abonnement;
use App\Models\EcheanceAbonnement;
use App\Models\Facture;
use App\Models\Formule;
use App\Models\PaiementAbonnement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SuperAdminBillingService
{
    /**
     * Génère une référence séquentielle unique pour un abonnement.
     */
    public function generateAbonnementReference(): string
    {
        $yearShort = date('y');
        $maxSeq = Abonnement::withTrashed()
            ->where('reference', 'like', "ABO-{$yearShort}-%")
            ->lockForUpdate()
            ->count();

        $seq = $maxSeq + 1;
        $ref = sprintf('ABO-%s-%04d', $yearShort, $seq);

        while (Abonnement::withTrashed()->where('reference', $ref)->exists()) {
            $seq++;
            $ref = sprintf('ABO-%s-%04d', $yearShort, $seq);
        }

        return $ref;
    }

    /**
     * Génère une référence séquentielle unique pour une échéance.
     */
    public function generateEcheanceReference(): string
    {
        $yearShort = date('y');
        $maxSeq = EcheanceAbonnement::withTrashed()
            ->where('reference', 'like', "ECH-{$yearShort}-%")
            ->lockForUpdate()
            ->count();

        $seq = $maxSeq + 1;
        $ref = sprintf('ECH-%s-%04d', $yearShort, $seq);

        while (EcheanceAbonnement::withTrashed()->where('reference', $ref)->exists()) {
            $seq++;
            $ref = sprintf('ECH-%s-%04d', $yearShort, $seq);
        }

        return $ref;
    }

    /**
     * Génère une référence séquentielle unique pour un paiement plateforme.
     */
    public function generatePaiementReference(): string
    {
        $yearShort = date('y');
        $maxSeq = PaiementAbonnement::withTrashed()
            ->where('reference', 'like', "PAY-{$yearShort}-%")
            ->lockForUpdate()
            ->count();

        $seq = $maxSeq + 1;
        $ref = sprintf('PAY-%s-%04d', $yearShort, $seq);

        while (PaiementAbonnement::withTrashed()->where('reference', $ref)->exists()) {
            $seq++;
            $ref = sprintf('PAY-%s-%04d', $yearShort, $seq);
        }

        return $ref;
    }

    /**
     * Génère une référence séquentielle unique pour une facture.
     */
    public function generateFactureReference(): string
    {
        $yearShort = date('y');
        $maxSeq = Facture::withTrashed()
            ->where('reference', 'like', "FAC-{$yearShort}-%")
            ->lockForUpdate()
            ->count();

        $seq = $maxSeq + 1;
        $ref = sprintf('FAC-%s-%04d', $yearShort, $seq);

        while (Facture::withTrashed()->where('reference', $ref)->exists()) {
            $seq++;
            $ref = sprintf('FAC-%s-%04d', $yearShort, $seq);
        }

        return $ref;
    }

    /**
     * Crée une échéance initiale pour un abonnement payant.
     */
    public function createInitialEcheance(Abonnement $abonnement): EcheanceAbonnement
    {
        $formule = $abonnement->formule;
        $dateDebut = Carbon::parse($abonnement->date_debut);

        $dateFin = ($formule->periodicite === Formule::PERIODICITE_MENSUELLE)
            ? $dateDebut->copy()->addMonth()->subDay()
            : $dateDebut->copy()->addYear()->subDay();

        return EcheanceAbonnement::create([
            'abonnement_id' => $abonnement->id,
            'reference'     => $this->generateEcheanceReference(),
            'periode_debut' => $dateDebut->toDateString(),
            'periode_fin'   => $dateFin->toDateString(),
            'date_echeance' => $dateDebut->copy()->addDays(15)->toDateString(),
            'montant'       => $abonnement->montant,
            'devise'        => $abonnement->devise,
            'statut'        => EcheanceAbonnement::STATUT_EN_ATTENTE,
        ]);
    }

    /**
     * Crée une facture associée à une échéance.
     */
    public function createFactureForEcheance(EcheanceAbonnement $echeance, array $customData = []): Facture
    {
        if ($echeance->facture) {
            return $echeance->facture;
        }

        $montantTotal = (float) ($customData['montant_total'] ?? $echeance->montant);
        $tauxTva      = (float) ($customData['taux_tva'] ?? 0.0);
        $montantHt    = ($tauxTva > 0) ? round($montantTotal / (1 + ($tauxTva / 100)), 2) : $montantTotal;
        $montantTva   = round($montantTotal - $montantHt, 2);

        $dateFacture  = $customData['date_facture'] ?? now()->toDateString();
        $dateEcheance = $customData['date_echeance'] ?? $echeance->date_echeance->toDateString();

        return Facture::create([
            'echeance_abonnement_id' => $echeance->id,
            'reference'              => $this->generateFactureReference(),
            'date_facture'           => $dateFacture,
            'date_echeance'          => $dateEcheance,
            'montant_ht'             => $montantHt,
            'taux_tva'               => $tauxTva,
            'montant_tva'            => $montantTva,
            'montant_total'          => $montantTotal,
            'devise'                 => $echeance->devise,
            'statut'                 => $echeance->isSolded() ? Facture::STATUT_PAYEE : Facture::STATUT_EN_ATTENTE,
            'description'            => $customData['description'] ?? 'Facture pour échéance ' . $echeance->reference,
            'observation'            => $customData['observation'] ?? null,
        ]);
    }

    /**
     * Enregistre un paiement plateforme pour une échéance de manière atomique et transactionnelle.
     */
    public function enregistrerPaiement(EcheanceAbonnement $echeance, array $paiementData): PaiementAbonnement
    {
        $montant = (float) ($paiementData['montant'] ?? 0);

        if ($montant <= 0) {
            throw new InvalidArgumentException("Le montant du paiement doit être strictement supérieur à zéro.");
        }

        return DB::transaction(function () use ($echeance, $paiementData, $montant) {
            $soldeAvant = $echeance->solde_restant;

            if ($montant > ($soldeAvant + 0.01)) {
                throw new InvalidArgumentException("Le montant payé [{$montant}] dépasse le solde restant dû [{$soldeAvant}].");
            }

            $paiement = PaiementAbonnement::create([
                'echeance_abonnement_id' => $echeance->id,
                'reference'              => $this->generatePaiementReference(),
                'montant'                => $montant,
                'devise'                 => $paiementData['devise'] ?? $echeance->devise,
                'mode_paiement'          => $paiementData['mode_paiement'] ?? PaiementAbonnement::MODE_ESPECES,
                'date_paiement'          => $paiementData['date_paiement'] ?? now()->toDateString(),
                'statut'                 => PaiementAbonnement::STATUT_VALIDE,
                'reference_transaction'  => $paiementData['reference_transaction'] ?? null,
                'observation'            => $paiementData['observation'] ?? null,
            ]);

            // Recharger les paiements de l'échéance et mettre à jour les statuts
            $echeance->refresh();

            if ($echeance->isSolded()) {
                $echeance->update(['statut' => EcheanceAbonnement::STATUT_PAYEE]);

                // Mettre à jour la facture liée si existante
                if ($echeance->facture) {
                    $echeance->facture->update(['statut' => Facture::STATUT_PAYEE]);
                }

                // Si premier paiement complet d'un abonnement en attente, basculer l'abonnement en actif
                $abonnement = $echeance->abonnement;
                if ($abonnement && $abonnement->statut === Abonnement::STATUT_EN_ATTENTE) {
                    $abonnement->update(['statut' => Abonnement::STATUT_ACTIF]);
                }
            }

            return $paiement;
        });
    }

    /**
     * Annule un paiement plateforme et recalcule les statuts.
     */
    public function annulerPaiement(PaiementAbonnement $paiement, ?string $observation = null): PaiementAbonnement
    {
        return DB::transaction(function () use ($paiement, $observation) {
            $paiement->update([
                'statut'      => PaiementAbonnement::STATUT_ANNULE,
                'observation' => $observation ? trim($paiement->observation . "\n" . $observation) : $paiement->observation,
            ]);

            $echeance = $paiement->echeance;
            if ($echeance) {
                $echeance->refresh();
                if (!$echeance->isSolded() && $echeance->statut === EcheanceAbonnement::STATUT_PAYEE) {
                    $echeance->update(['statut' => EcheanceAbonnement::STATUT_EN_ATTENTE]);
                    if ($echeance->facture) {
                        $echeance->facture->update(['statut' => Facture::STATUT_EN_ATTENTE]);
                    }
                }
            }

            return $paiement;
        });
    }

    /**
     * Rembourse un paiement plateforme.
     */
    public function rembourserPaiement(PaiementAbonnement $paiement, ?string $observation = null): PaiementAbonnement
    {
        return DB::transaction(function () use ($paiement, $observation) {
            $paiement->update([
                'statut'      => PaiementAbonnement::STATUT_REMBOURSE,
                'observation' => $observation ? trim($paiement->observation . "\n" . $observation) : $paiement->observation,
            ]);

            $echeance = $paiement->echeance;
            if ($echeance) {
                $echeance->refresh();
                if (!$echeance->isSolded() && $echeance->statut === EcheanceAbonnement::STATUT_PAYEE) {
                    $echeance->update(['statut' => EcheanceAbonnement::STATUT_EN_ATTENTE]);
                    if ($echeance->facture) {
                        $echeance->facture->update(['statut' => Facture::STATUT_EN_ATTENTE]);
                    }
                }
            }

            return $paiement;
        });
    }
}
