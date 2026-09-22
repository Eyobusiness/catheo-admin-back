<?php

namespace App\Services\SuperAdmin;

use App\Models\Abonnement;
use App\Models\CatecheseConfiguration;
use App\Models\EcheanceAbonnement;
use App\Models\PaiementAbonnement;
use App\Models\Produit;
use Carbon\Carbon;

class SuperAdminDashboardService
{
    /**
     * Calcule tous les indicateurs consolidés du tableau de bord Super Admin.
     */
    public function getMetrics(): array
    {
        $today = now()->toDateString();

        // 1. Indicateurs Paroisses & Produits
        $totalParoisses        = CatecheseConfiguration::count();
        $paroissesActives      = CatecheseConfiguration::where('statut', 'actif')->count();
        $produitsActifs        = Produit::where('statut', 'actif')->count();

        // 2. Indicateurs Abonnements
        $abonnementsActifs     = Abonnement::where('statut', Abonnement::STATUT_ACTIF)->count();
        $abonnementsEnAttente  = Abonnement::where('statut', Abonnement::STATUT_EN_ATTENTE)->count();
        $abonnementsSuspendus  = Abonnement::where('statut', Abonnement::STATUT_SUSPENDU)->count();
        $abonnementsExpires    = Abonnement::where('statut', Abonnement::STATUT_EXPIRE)->count();
        $abonnementsResilies   = Abonnement::where('statut', Abonnement::STATUT_RESILIE)->count();

        // 3. Indicateurs Financiers (Paiements Plateforme)
        $caTotalEncaisse       = (float) PaiementAbonnement::where('statut', PaiementAbonnement::STATUT_VALIDE)->sum('montant');
        $caMoisCourant         = (float) PaiementAbonnement::where('statut', PaiementAbonnement::STATUT_VALIDE)
            ->whereYear('date_paiement', now()->year)
            ->whereMonth('date_paiement', now()->month)
            ->sum('montant');

        // 4. Échéances & Retards
        $echeancesEnRetardCount = EcheanceAbonnement::where('statut', EcheanceAbonnement::STATUT_EN_ATTENTE)
            ->where('date_echeance', '<', $today)
            ->count();

        $montantEnRetard = (float) EcheanceAbonnement::where('statut', EcheanceAbonnement::STATUT_EN_ATTENTE)
            ->where('date_echeance', '<', $today)
            ->sum('montant');

        // 5. Répartition des abonnements par produit
        $produits = Produit::all();
        $repartitionProduits = [];
        foreach ($produits as $prod) {
            $countAbo = Abonnement::whereHas('formule', function ($q) use ($prod) {
                $q->where('produit_id', $prod->id);
            })->where('statut', Abonnement::STATUT_ACTIF)->count();

            $repartitionProduits[] = [
                'produit_id'          => $prod->id,
                'produit_code'        => $prod->code,
                'produit_nom'         => $prod->nom,
                'abonnements_actifs'  => $countAbo,
            ];
        }

        // 6. Derniers paiements encaissés
        $paiementsRecents = PaiementAbonnement::with(['echeance.abonnement.paroisse', 'echeance.abonnement.formule.produit'])
            ->where('statut', PaiementAbonnement::STATUT_VALIDE)
            ->latest('date_paiement')
            ->limit(10)
            ->get()
            ->map(function ($p) {
                return [
                    'id'               => $p->uuid,
                    'reference'        => $p->reference,
                    'montant'          => (float) $p->montant,
                    'devise'           => $p->devise,
                    'mode_paiement'    => $p->mode_paiement,
                    'date_paiement'    => $p->date_paiement?->toDateString(),
                    'paroisse_nom'     => $p->echeance?->abonnement?->paroisse?->nom_paroisse ?? 'N/A',
                    'produit_code'     => $p->echeance?->abonnement?->formule?->produit?->code ?? 'N/A',
                ];
            });

        return [
            'paroisses' => [
                'total'   => $totalParoisses,
                'actives' => $paroissesActives,
            ],
            'produits_actifs' => $produitsActifs,
            'abonnements' => [
                'actifs'      => $abonnementsActifs,
                'en_attente'  => $abonnementsEnAttente,
                'suspendus'   => $abonnementsSuspendus,
                'expires'     => $abonnementsExpires,
                'resilies'    => $abonnementsResilies,
            ],
            'finances' => [
                'ca_total_encaisse'    => $caTotalEncaisse,
                'ca_mois_courant'      => $caMoisCourant,
                'echeances_en_retard'  => $echeancesEnRetardCount,
                'montant_en_retard'    => $montantEnRetard,
                'devise'               => 'XOF',
            ],
            'repartition_produits' => $repartitionProduits,
            'paiements_recents'    => $paiementsRecents,
        ];
    }
}
