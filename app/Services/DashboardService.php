<?php

namespace App\Services;

use App\Models\Animateur;
use App\Models\AnneeCatechese;
use App\Models\Classe;
use App\Models\InscriptionAnnuelle;
use App\Models\Niveau;
use App\Models\OperationPaiement;
use App\Models\Paiement;
use App\Models\Preinscription;
use App\Models\Seance;
use App\Models\Section;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    /**
     * Obtenir les données consolidées du Tableau de Bord Pastoral & Administratif (Sans finances).
     */
    public function getAdminDashboardData(int $paroisseId, ?AnneeCatechese $annee = null): array
    {
        $anneeId = $annee?->id;

        // 1. Synthèse générale (KPIs)
        $catechumenesActifs = $anneeId ? InscriptionAnnuelle::where('paroisse_configuration_id', $paroisseId)
            ->where('annee_catechese_id', $anneeId)
            ->where('statut_inscription', '!=', 'annulee')
            ->whereHas('catechumene', fn($q) => $q->where('statut', 'actif'))
            ->distinct('catechumene_id')
            ->count('catechumene_id') : 0;

        $sectionsCount = Section::where('paroisse_configuration_id', $paroisseId)
            ->where('statut', 'actif')
            ->count();

        $classesCount = Classe::where('paroisse_configuration_id', $paroisseId)
            ->when($anneeId, function ($q) use ($anneeId) {
                $q->where(function ($sub) use ($anneeId) {
                    $sub->where('annee_catechese_id', $anneeId)
                        ->orWhereNull('annee_catechese_id');
                });
            })
            ->whereIn('statut', ['actif', 'active'])
            ->count();

        $animateursCount = Animateur::where('paroisse_configuration_id', $paroisseId)
            ->where('statut', 'actif')
            ->count();

        $preinscriptionsEnAttente = Preinscription::where('paroisse_configuration_id', $paroisseId)
            ->where('statut', 'en_attente')
            ->count();

        // 2. Répartition par Section
        $sections = Section::where('paroisse_configuration_id', $paroisseId)
            ->where('statut', 'actif')
            ->orderBy('ordre_affichage')
            ->get();

        $inscriptionsBySection = $anneeId ? InscriptionAnnuelle::where('paroisse_configuration_id', $paroisseId)
            ->where('annee_catechese_id', $anneeId)
            ->where('statut_inscription', '!=', 'annulee')
            ->select('section_id', DB::raw('count(DISTINCT catechumene_id) as total'))
            ->groupBy('section_id')
            ->pluck('total', 'section_id') : collect();

        $repartitionSections = [];
        foreach ($sections as $sec) {
            $effectif = (int) ($inscriptionsBySection[$sec->id] ?? 0);
            $repartitionSections[] = [
                'section_id'  => $sec->uuid,
                'section_nom' => $sec->nom,
                'code'        => $sec->code,
                'effectif'    => $effectif,
            ];
        }

        // 3. Répartition par Niveau de Catéchèse
        $niveaux = Niveau::where('paroisse_configuration_id', $paroisseId)
            ->where('statut', 'actif')
            ->with('section')
            ->orderBy('ordre_affichage')
            ->get();

        $inscriptionsByNiveau = $anneeId ? InscriptionAnnuelle::where('paroisse_configuration_id', $paroisseId)
            ->where('annee_catechese_id', $anneeId)
            ->where('statut_inscription', '!=', 'annulee')
            ->select('niveau_id', DB::raw('count(DISTINCT catechumene_id) as total'))
            ->groupBy('niveau_id')
            ->pluck('total', 'niveau_id') : collect();

        $repartitionNiveaux = [];
        foreach ($niveaux as $niv) {
            $effectif = (int) ($inscriptionsByNiveau[$niv->id] ?? 0);
            $repartitionNiveaux[] = [
                'niveau_id'   => $niv->uuid,
                'niveau_nom'  => $niv->nom,
                'section_id'  => $niv->section?->uuid,
                'section_nom' => $niv->section?->nom ?? 'Non définie',
                'effectif'    => $effectif,
            ];
        }

        // 4. Répartition par Classe de Catéchèse
        $classesQuery = Classe::where('paroisse_configuration_id', $paroisseId)
            ->whereIn('statut', ['actif', 'active'])
            ->with(['niveau.section']);

        if ($anneeId) {
            $classesQuery->where(function ($q) use ($anneeId) {
                $q->where('annee_catechese_id', $anneeId)
                  ->orWhereNull('annee_catechese_id');
            });
        }

        $classes = $classesQuery->get();

        $inscriptionsByClasse = $anneeId ? InscriptionAnnuelle::where('paroisse_configuration_id', $paroisseId)
            ->where('annee_catechese_id', $anneeId)
            ->where('statut_inscription', '!=', 'annulee')
            ->select('classe_id', DB::raw('count(DISTINCT catechumene_id) as total'))
            ->groupBy('classe_id')
            ->pluck('total', 'classe_id') : collect();

        $repartitionClasses = [];
        foreach ($classes as $cl) {
            $effectif = (int) ($inscriptionsByClasse[$cl->id] ?? 0);
            $capacite = $cl->capacite_max ? (int) $cl->capacite_max : 30;
            $pourcentage = $capacite > 0 ? min(100, (int) round(($effectif / $capacite) * 100)) : 0;
            $repartitionClasses[] = [
                'classe_id'    => $cl->uuid,
                'classe_nom'   => $cl->nom,
                'niveau_id'    => $cl->niveau?->uuid,
                'niveau_nom'   => $cl->niveau?->nom ?? 'Non d�fini',
                'section_id'   => $cl->niveau?->section?->uuid,
                'section_nom'  => $cl->niveau?->section?->nom ?? 'Non d�finie',
                'effectif'     => $effectif,
                'capacite_max' => $capacite,
                'pourcentage'  => $pourcentage,
            ];
        }

        // 5. Candidats aux Sacrements
        // 5.1 En attente de Baptême (Année active + Inscription + 3ème année + est_baptise = false)
        $candidatsBapteme = $anneeId ? InscriptionAnnuelle::where('paroisse_configuration_id', $paroisseId)
            ->where('annee_catechese_id', $anneeId)
            ->where('statut_inscription', '!=', 'annulee')
            ->whereHas('catechumene', fn($q) => $q->where('est_baptise', false)->where('statut', 'actif'))
            ->whereHas('niveau', function ($q) {
                $q->where('nom', 'like', '3%')
                  ->orWhere('nom', 'like', '%3ème%')
                  ->orWhere('nom', 'like', '%3e%')
                  ->orWhere('ordre_affichage', 3);
            })
            ->distinct('catechumene_id')
            ->count('catechumene_id') : 0;

        // 5.2 Première Communion (Année active + Inscription + 3ème année + est_baptise = true)
        $candidatsPremiereCommunion = $anneeId ? InscriptionAnnuelle::where('paroisse_configuration_id', $paroisseId)
            ->where('annee_catechese_id', $anneeId)
            ->where('statut_inscription', '!=', 'annulee')
            ->whereHas('catechumene', fn($q) => $q->where('est_baptise', true)->where('statut', 'actif'))
            ->whereHas('niveau', function ($q) {
                $q->where('nom', 'like', '3%')
                  ->orWhere('nom', 'like', '%3ème%')
                  ->orWhere('nom', 'like', '%3e%')
                  ->orWhere('ordre_affichage', 3);
            })
            ->distinct('catechumene_id')
            ->count('catechumene_id') : 0;

        // 5.3 Confirmation (Année active + Inscription + est_baptise = true + [Adulte & 4ème année OU Non-Adulte & 5ème année])
        $candidatsConfirmation = $anneeId ? InscriptionAnnuelle::where('paroisse_configuration_id', $paroisseId)
            ->where('annee_catechese_id', $anneeId)
            ->where('statut_inscription', '!=', 'annulee')
            ->whereHas('catechumene', fn($q) => $q->where('est_baptise', true)->where('statut', 'actif'))
            ->where(function ($query) {
                $query->where(function ($q1) {
                    $q1->whereHas('section', function ($qs) {
                        $qs->where('code', 'like', '%ADULTE%')
                           ->orWhere('nom', 'like', '%adulte%');
                    })->whereHas('niveau', function ($qn) {
                        $qn->where('nom', 'like', '4%')
                           ->orWhere('nom', 'like', '%4ème%')
                           ->orWhere('nom', 'like', '%4e%')
                           ->orWhere('ordre_affichage', 4);
                    });
                })->orWhere(function ($q2) {
                    $q2->whereHas('section', function ($qs) {
                        $qs->where('code', 'not like', '%ADULTE%')
                           ->where('nom', 'not like', '%adulte%');
                    })->whereHas('niveau', function ($qn) {
                        $qn->where('nom', 'like', '5%')
                           ->orWhere('nom', 'like', '%5ème%')
                           ->orWhere('nom', 'like', '%5e%')
                           ->orWhere('ordre_affichage', 5);
                    });
                });
            })
            ->distinct('catechumene_id')
            ->count('catechumene_id') : 0;

        // 6. Alertes Pastorales & Administratives
        // 6.1 Nouvelles préinscriptions
        $preinscriptionsCount = Preinscription::where('paroisse_configuration_id', $paroisseId)
            ->where('statut', 'en_attente')
            ->count();

        $preinscriptionsMsg = match(true) {
            $preinscriptionsCount === 0 => "Aucune préinscription en attente.",
            $preinscriptionsCount === 1 => "1 dossier de préinscription en attente de validation.",
            default                     => "{$preinscriptionsCount} dossiers de préinscription en attente de validation.",
        };

        // 6.2 Appels non effectués (Séances passées sans feuille de présence)
        $seancesSansPresencesQuery = Seance::where('paroisse_configuration_id', $paroisseId)
            ->whereDate('date_seance', '<=', now()->toDateString())
            ->whereDoesntHave('presences');

        if ($anneeId) {
            $seancesSansPresencesQuery->where('annee_catechese_id', $anneeId);
        }

        $appelsNonFaitsCount = $seancesSansPresencesQuery->count();

        $appelsMsg = match(true) {
            $appelsNonFaitsCount === 0 => "Tous les appels des séances passées ont été effectués.",
            $appelsNonFaitsCount === 1 => "1 séance passée n'a pas encore de feuille de présence enregistrée.",
            default                    => "{$appelsNonFaitsCount} séances passées n'ont pas encore de feuille de présence enregistrée.",
        };

        return [
            'annee_active' => $annee ? [
                'id'         => $annee->id,
                'uuid'       => $annee->uuid,
                'libelle'    => $annee->libelle,
                'date_debut' => $annee->date_debut?->toDateString(),
                'date_fin'   => $annee->date_fin?->toDateString(),
                'statut'     => $annee->statut,
            ] : null,

            'summary' => [
                'catechumenes_actifs'       => $catechumenesActifs,
                'sections'                  => $sectionsCount,
                'classes'                   => $classesCount,
                'animateurs'                => $animateursCount,
                'preinscriptions_en_attente'=> $preinscriptionsEnAttente,
            ],

            'effectifs' => [
                'par_section' => $repartitionSections,
                'par_niveau'  => $repartitionNiveaux,
                'par_classe'  => $repartitionClasses,
            ],

            'sacrements' => [
                'bapteme'            => $candidatsBapteme,
                'premiere_communion' => $candidatsPremiereCommunion,
                'confirmation'       => $candidatsConfirmation,
            ],

            'alertes' => [
                'nouvelles_preinscriptions' => [
                    'type'    => 'preinscriptions',
                    'count'   => $preinscriptionsCount,
                    'message' => $preinscriptionsMsg,
                ],
                'appels_non_effectues' => [
                    'type'    => 'appels_non_effectues',
                    'count'   => $appelsNonFaitsCount,
                    'message' => $appelsMsg,
                ],
            ],

            // Rétrocompatibilité frontend si nécessaire
            'kpis' => [
                'total_catechumenes'        => $catechumenesActifs,
                'total_sections'            => $sectionsCount,
                'total_classes'             => $classesCount,
                'total_animateurs'          => $animateursCount,
                'preinscriptions_en_attente'=> $preinscriptionsEnAttente,
            ],
            'repartition_sections'   => $repartitionSections,
            'repartition_niveaux'    => $repartitionNiveaux,
            'effectifs_classes'      => $repartitionClasses,
            'preparation_sacrements' => [
                'bapteme_candidats'            => $candidatsBapteme,
                'premiere_communion_candidats' => $candidatsPremiereCommunion,
                'confirmation_candidats'       => $candidatsConfirmation,
            ],
        ];
    }

    /**
     * Situation financière dédiée (accessible uniquement via /dashboard/finances).
     */
    public function getFinancesDashboardData(int $paroisseId, ?AnneeCatechese $annee = null): array
    {
        $anneeId = $annee?->id;

        $montantEncaisseQuery = Paiement::where('paroisse_configuration_id', $paroisseId)->where('statut', 'valide');
        $operationsQuery = OperationPaiement::where('paroisse_configuration_id', $paroisseId);

        if ($anneeId) {
            $montantEncaisseQuery->where('annee_catechese_id', $anneeId);
            $operationsQuery->where('annee_catechese_id', $anneeId);
        }

        $montantEncaisse = (float) $montantEncaisseQuery->sum('montant_total');
        $montantAttendu = (float) $operationsQuery->sum('montant');
        
        if ($montantAttendu <= 0 && $montantEncaisse > 0) {
            $montantAttendu = $montantEncaisse;
        }

        $resteAPayer = max(0, $montantAttendu - $montantEncaisse);
        $tauxRecouvrement = $montantAttendu > 0 ? round(($montantEncaisse / $montantAttendu) * 100, 1) : 100;

        return [
            'annee_active' => $annee ? [
                'id'      => $annee->id,
                'uuid'    => $annee->uuid,
                'libelle' => $annee->libelle,
            ] : null,
            'situation_financiere' => [
                'montant_attendu'   => $montantAttendu,
                'montant_encaisse'  => $montantEncaisse,
                'reste_a_payer'     => $resteAPayer,
                'taux_recouvrement' => $tauxRecouvrement,
            ],
        ];
    }
}
