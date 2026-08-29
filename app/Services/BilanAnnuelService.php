<?php

namespace App\Services;

use App\Models\AffectationAnimateur;
use App\Models\Animateur;
use App\Models\AnneeCatechese;
use App\Models\Classe;
use App\Models\DecisionFinAnnee;
use App\Models\InscriptionAnnuelle;
use App\Models\MutationCatechumene;
use App\Models\Niveau;
use App\Models\Preinscription;
use App\Models\Presence;
use App\Models\Seance;
use App\Models\Section;
use Illuminate\Support\Facades\DB;

class BilanAnnuelService
{
    /**
     * Génère le rapport complet du Bilan Annuel de Catéchèse pour une année précise.
     */
    public function genererBilanAnnuel(int $paroisseId, AnneeCatechese $annee): array
    {
        $anneeId = $annee->id;

        // =========================================================================
        // 1. SYNTHÈSE GÉNÉRALE
        // =========================================================================
        $inscriptionsBase = InscriptionAnnuelle::where('paroisse_configuration_id', $paroisseId)
            ->where('annee_catechese_id', $anneeId)
            ->where('statut_inscription', '!=', 'annulee');

        $effectifTotal = (clone $inscriptionsBase)->distinct('catechumene_id')->count('catechumene_id');

        $catechumenesActifs = (clone $inscriptionsBase)
            ->whereHas('catechumene', fn($q) => $q->where('statut', 'actif'))
            ->distinct('catechumene_id')
            ->count('catechumene_id');

        // Réinscriptions : catéchumènes ayant au moins une inscription sur une autre année
        $reinscriptions = (clone $inscriptionsBase)
            ->whereIn('catechumene_id', function ($query) use ($paroisseId, $anneeId) {
                $query->select('catechumene_id')
                    ->from('inscriptions_annuelles')
                    ->where('paroisse_configuration_id', $paroisseId)
                    ->where('annee_catechese_id', '!=', $anneeId)
                    ->where('statut_inscription', '!=', 'annulee');
            })
            ->distinct('catechumene_id')
            ->count('catechumene_id');

        $nouveaux = max(0, $effectifTotal - $reinscriptions);

        $sectionsCount = Section::where('paroisse_configuration_id', $paroisseId)
            ->where('statut', 'actif')
            ->count();

        $niveauxCount = Niveau::where('paroisse_configuration_id', $paroisseId)
            ->where('statut', 'actif')
            ->count();

        $classesCount = Classe::where('paroisse_configuration_id', $paroisseId)
            ->where('annee_catechese_id', $anneeId)
            ->where('statut', 'actif')
            ->count();

        $animateursCount = Animateur::where('paroisse_configuration_id', $paroisseId)
            ->where('statut', 'actif')
            ->count();

        $mutationsCount = MutationCatechumene::where('paroisse_configuration_id', $paroisseId)
            ->where('annee_catechese_id', $anneeId)
            ->count();

        $synthese = [
            'effectif_total'      => $effectifTotal,
            'catechumenes_actifs' => $catechumenesActifs,
            'nouveaux'            => $nouveaux,
            'reinscriptions'      => $reinscriptions,
            'mutations'           => $mutationsCount,
            'sections'            => $sectionsCount,
            'niveaux'             => $niveauxCount,
            'classes'             => $classesCount,
            'animateurs'          => $animateursCount,
        ];

        // =========================================================================
        // 2. RÉPARTITION DES EFFECTIFS
        // =========================================================================
        // 2.1 Par Section
        $sections = Section::where('paroisse_configuration_id', $paroisseId)
            ->where('statut', 'actif')
            ->orderBy('ordre_affichage')
            ->get();

        $inscriptionsBySection = (clone $inscriptionsBase)
            ->select('section_id', DB::raw('count(DISTINCT catechumene_id) as total'))
            ->groupBy('section_id')
            ->pluck('total', 'section_id');

        $repartitionSections = [];
        foreach ($sections as $sec) {
            $count = (int) ($inscriptionsBySection[$sec->id] ?? 0);
            $repartitionSections[] = [
                'section_id'  => $sec->uuid,
                'section_nom' => $sec->nom,
                'code'        => $sec->code,
                'effectif'    => $count,
                'pourcentage' => $effectifTotal > 0 ? round(($count / $effectifTotal) * 100, 1) : 0,
            ];
        }

        // 2.2 Par Niveau
        $niveaux = Niveau::where('paroisse_configuration_id', $paroisseId)
            ->where('statut', 'actif')
            ->with('section')
            ->orderBy('ordre_affichage')
            ->get();

        $inscriptionsByNiveau = (clone $inscriptionsBase)
            ->select('niveau_id', DB::raw('count(DISTINCT catechumene_id) as total'))
            ->groupBy('niveau_id')
            ->pluck('total', 'niveau_id');

        $repartitionNiveaux = [];
        foreach ($niveaux as $niv) {
            $count = (int) ($inscriptionsByNiveau[$niv->id] ?? 0);
            $repartitionNiveaux[] = [
                'niveau_id'   => $niv->uuid,
                'niveau_nom'  => $niv->nom,
                'section_id'  => $niv->section?->uuid,
                'section_nom' => $niv->section?->nom ?? 'Non définie',
                'effectif'    => $count,
                'pourcentage' => $effectifTotal > 0 ? round(($count / $effectifTotal) * 100, 1) : 0,
            ];
        }

        // 2.3 Par Classe de Catéchèse
        $classes = Classe::where('paroisse_configuration_id', $paroisseId)
            ->where('annee_catechese_id', $anneeId)
            ->where('statut', 'actif')
            ->with(['niveau.section'])
            ->get();

        $inscriptionsByClasse = (clone $inscriptionsBase)
            ->select('classe_id', DB::raw('count(DISTINCT catechumene_id) as total'))
            ->groupBy('classe_id')
            ->pluck('total', 'classe_id');

        $repartitionClasses = [];
        foreach ($classes as $cl) {
            $count = (int) ($inscriptionsByClasse[$cl->id] ?? 0);
            $repartitionClasses[] = [
                'classe_id'     => $cl->uuid,
                'classe_nom'    => $cl->nom,
                'niveau_id'     => $cl->niveau?->uuid,
                'niveau_nom'    => $cl->niveau?->nom ?? 'Non défini',
                'section_id'    => $cl->niveau?->section?->uuid,
                'section_nom'   => $cl->niveau?->section?->nom ?? 'Non définie',
                'effectif'      => $count,
                'capacite_max'  => $cl->capacite_max,
                'taux_remplissage' => ($cl->capacite_max && $cl->capacite_max > 0) ? round(($count / $cl->capacite_max) * 100, 1) : null,
            ];
        }

        $effectifs = [
            'par_section' => $repartitionSections,
            'par_niveau'  => $repartitionNiveaux,
            'par_classe'  => $repartitionClasses,
        ];

        // =========================================================================
        // 3. COMPARAISON AVEC L'ANNÉE PRÉCÉDENTE
        // =========================================================================
        $anneePrecedente = AnneeCatechese::where('paroisse_configuration_id', $paroisseId)
            ->where('id', '!=', $anneeId)
            ->where(function ($q) use ($annee) {
                if ($annee->date_debut) {
                    $q->where('date_debut', '<', $annee->date_debut);
                } else {
                    $q->where('id', '<', $annee->id);
                }
            })
            ->orderByDesc('date_debut')
            ->orderByDesc('id')
            ->first();

        $evolution = null;
        if ($anneePrecedente) {
            $effectifPrecedent = InscriptionAnnuelle::where('paroisse_configuration_id', $paroisseId)
                ->where('annee_catechese_id', $anneePrecedente->id)
                ->where('statut_inscription', '!=', 'annulee')
                ->distinct('catechumene_id')
                ->count('catechumene_id');

            $difference = $effectifTotal - $effectifPrecedent;
            $pourcentageEvolution = $effectifPrecedent > 0
                ? round(($difference / $effectifPrecedent) * 100, 1)
                : 100.0;

            $evolution = [
                'disponible'               => true,
                'annee_actuelle_libelle'   => $annee->libelle,
                'annee_precedente_libelle' => $anneePrecedente->libelle,
                'annee_actuelle'           => $effectifTotal,
                'annee_precedente'         => $effectifPrecedent,
                'difference'               => $difference,
                'pourcentage'              => $pourcentageEvolution,
            ];
        }

        // =========================================================================
        // 4. ASSIDUITÉ ET SÉANCES
        // =========================================================================
        $seancesQuery = Seance::where('paroisse_configuration_id', $paroisseId)
            ->where('annee_catechese_id', $anneeId);

        $seancesPrevues = (clone $seancesQuery)->count();
        $seancesRealisees = (clone $seancesQuery)->where(function ($q) {
            $q->where('statut', 'effectuee')->orWhereHas('presences');
        })->count();
        $seancesAnnulees = (clone $seancesQuery)->where('statut', 'annulee')->count();
        $seancesPassees = (clone $seancesQuery)->where('date_seance', '<=', now()->toDateString())->count();

        $presencesCount = Presence::where('paroisse_configuration_id', $paroisseId)
            ->whereHas('seance', fn($q) => $q->where('annee_catechese_id', $anneeId))
            ->where('statut_presence', 'present')
            ->count();

        $absencesCount = Presence::where('paroisse_configuration_id', $paroisseId)
            ->whereHas('seance', fn($q) => $q->where('annee_catechese_id', $anneeId))
            ->where('statut_presence', 'absent')
            ->count();

        $absencesJustifiees = Presence::where('paroisse_configuration_id', $paroisseId)
            ->whereHas('seance', fn($q) => $q->where('annee_catechese_id', $anneeId))
            ->where('statut_presence', 'absent')
            ->where(function ($q) {
                $q->whereNotNull('motif_absence')->where('motif_absence', '!=', '');
            })
            ->count();

        $totalFichesPresence = $presencesCount + $absencesCount;
        $tauxPresenceMoyen = $totalFichesPresence > 0
            ? round(($presencesCount / $totalFichesPresence) * 100, 1)
            : 0.0;

        $assiduite = [
            'seances_prevues'     => $seancesPrevues,
            'seances_realisees'   => $seancesRealisees,
            'seances_annulees'    => $seancesAnnulees,
            'seances_passees'     => $seancesPassees,
            'presences'           => $presencesCount,
            'absences'            => $absencesCount,
            'absences_justifiees' => $absencesJustifiees,
            'taux_presence'       => $tauxPresenceMoyen,
        ];

        // =========================================================================
        // 5. PROGRESSION DES CATÉCHUMÈNES PAR NIVEAU
        // =========================================================================
        $decisions = DecisionFinAnnee::where('paroisse_configuration_id', $paroisseId)
            ->whereHas('inscriptionAnnuelle', fn($q) => $q->where('annee_catechese_id', $anneeId))
            ->with('inscriptionAnnuelle')
            ->get();

        $progressionParNiveau = [];
        foreach ($niveaux as $niv) {
            $inscritsNiv = (int) ($inscriptionsByNiveau[$niv->id] ?? 0);
            $decisionsNiv = $decisions->filter(fn($d) => $d->inscriptionAnnuelle?->niveau_id === $niv->id);

            $admis = $decisionsNiv->filter(fn($d) => in_array(strtolower($d->decision ?? ''), ['admis', 'passage', 'succes']))->count();
            $ajournes = $decisionsNiv->filter(fn($d) => in_array(strtolower($d->decision ?? ''), ['refuse', 'ajourne', 'redouble']))->count();
            $enAttente = max(0, $inscritsNiv - ($admis + $ajournes));

            $progressionParNiveau[] = [
                'niveau_id'         => $niv->uuid,
                'niveau_nom'        => $niv->nom,
                'section_nom'       => $niv->section?->nom ?? 'Non définie',
                'effectif_inscrit'  => $inscritsNiv,
                'admis'             => $admis,
                'ajournes'          => $ajournes,
                'en_attente'        => $enAttente,
                'taux_reussite'     => ($admis + $ajournes) > 0 ? round(($admis / ($admis + $ajournes)) * 100, 1) : null,
            ];
        }

        // =========================================================================
        // 6. BILAN DES SACREMENTS
        // =========================================================================
        // Date range de l'année pour vérifier les sacrements conférés
        $debutAnnee = $annee->date_debut ? $annee->date_debut->toDateString() : null;
        $finAnnee = $annee->date_fin ? $annee->date_fin->toDateString() : null;

        // 6.1 Baptême : 3ème année + est_baptise = false
        $inscriptionsBapteme = (clone $inscriptionsBase)
            ->whereHas('catechumene', fn($q) => $q->where('est_baptise', false))
            ->whereHas('niveau', function ($q) {
                $q->where('nom', 'like', '3%')
                  ->orWhere('nom', 'like', '%3ème%')
                  ->orWhere('nom', 'like', '%3e%')
                  ->orWhere('ordre_affichage', 3);
            })
            ->with(['catechumene'])
            ->get();

        $candidatsBapteme = $inscriptionsBapteme->unique('catechumene_id')->count();

        $baptemesRealises = $inscriptionsBapteme->filter(function ($ins) use ($debutAnnee, $finAnnee, $decisions) {
            $cat = $ins->catechumene;
            $decision = $decisions->firstWhere('inscription_annuelle_id', $ins->id);
            if ($decision && $decision->sacrement_recu) {
                return true;
            }
            if ($cat && $cat->date_bapteme) {
                $dateB = $cat->date_bapteme->toDateString();
                return (!$debutAnnee || $dateB >= $debutAnnee) && (!$finAnnee || $dateB <= $finAnnee);
            }
            return false;
        })->unique('catechumene_id')->count();

        // 6.2 Première Communion : 3ème année + est_baptise = true
        $inscriptionsCommunion = (clone $inscriptionsBase)
            ->whereHas('catechumene', fn($q) => $q->where('est_baptise', true))
            ->whereHas('niveau', function ($q) {
                $q->where('nom', 'like', '3%')
                  ->orWhere('nom', 'like', '%3ème%')
                  ->orWhere('nom', 'like', '%3e%')
                  ->orWhere('ordre_affichage', 3);
            })
            ->with(['catechumene'])
            ->get();

        $candidatsCommunion = $inscriptionsCommunion->unique('catechumene_id')->count();

        $communionsRealisees = $inscriptionsCommunion->filter(function ($ins) use ($debutAnnee, $finAnnee, $decisions) {
            $cat = $ins->catechumene;
            $decision = $decisions->firstWhere('inscription_annuelle_id', $ins->id);
            if ($decision && $decision->sacrement_recu) {
                return true;
            }
            if ($cat && $cat->date_premiere_communion) {
                $dateC = $cat->date_premiere_communion->toDateString();
                return (!$debutAnnee || $dateC >= $debutAnnee) && (!$finAnnee || $dateC <= $finAnnee);
            }
            return false;
        })->unique('catechumene_id')->count();

        // 6.3 Confirmation : Baptisé + (Adulte + 4ème année OU Autre Section + 5ème année)
        $inscriptionsConfirmation = (clone $inscriptionsBase)
            ->whereHas('catechumene', fn($q) => $q->where('est_baptise', true))
            ->where(function ($query) {
                $query->where(function ($q) {
                    $q->whereHas('section', function ($sq) {
                        $sq->where('nom', 'like', '%adulte%')->orWhere('code', 'like', '%ADULTE%');
                    })->whereHas('niveau', function ($nq) {
                        $nq->where('nom', 'like', '4%')->orWhere('nom', 'like', '%4ème%')->orWhere('ordre_affichage', 4);
                    });
                })->orWhere(function ($q) {
                    $q->whereHas('section', function ($sq) {
                        $sq->where('nom', 'not like', '%adulte%')->where('code', 'not like', '%ADULTE%');
                    })->whereHas('niveau', function ($nq) {
                        $nq->where('nom', 'like', '5%')->orWhere('nom', 'like', '%5ème%')->orWhere('ordre_affichage', 5);
                    });
                });
            })
            ->with(['catechumene'])
            ->get();

        $candidatsConfirmation = $inscriptionsConfirmation->unique('catechumene_id')->count();

        $confirmationsRealisees = $inscriptionsConfirmation->filter(function ($ins) use ($debutAnnee, $finAnnee, $decisions) {
            $cat = $ins->catechumene;
            $decision = $decisions->firstWhere('inscription_annuelle_id', $ins->id);
            if ($decision && $decision->sacrement_recu) {
                return true;
            }
            if ($cat && $cat->date_confirmation) {
                $dateConf = $cat->date_confirmation->toDateString();
                return (!$debutAnnee || $dateConf >= $debutAnnee) && (!$finAnnee || $dateConf <= $finAnnee);
            }
            return false;
        })->unique('catechumene_id')->count();

        $sacrements = [
            'bapteme' => [
                'candidats' => $candidatsBapteme,
                'realises'  => $baptemesRealises,
                'restants'  => max(0, $candidatsBapteme - $baptemesRealises),
            ],
            'premiere_communion' => [
                'candidats' => $candidatsCommunion,
                'realises'  => $communionsRealisees,
                'restants'  => max(0, $candidatsCommunion - $communionsRealisees),
            ],
            'confirmation' => [
                'candidats' => $candidatsConfirmation,
                'realises'  => $confirmationsRealisees,
                'restants'  => max(0, $candidatsConfirmation - $confirmationsRealisees),
            ],
        ];

        // =========================================================================
        // 7. PRÉINSCRIPTIONS ET RECRUTEMENT
        // =========================================================================
        $preinscriptionsQuery = Preinscription::where('paroisse_configuration_id', $paroisseId)
            ->where('annee_catechese_id', $anneeId);

        $preinscriptionsTotal = (clone $preinscriptionsQuery)->count();
        $preinscriptionsValidees = (clone $preinscriptionsQuery)->where('statut', 'validee')->count();
        $preinscriptionsRejetees = (clone $preinscriptionsQuery)->where('statut', 'rejetee')->count();
        $preinscriptionsEnAttente = (clone $preinscriptionsQuery)->where('statut', 'en_attente')->count();

        $inscriptions = [
            'preinscriptions_total'     => $preinscriptionsTotal,
            'validees'                  => $preinscriptionsValidees,
            'rejetees'                  => $preinscriptionsRejetees,
            'en_attente'                => $preinscriptionsEnAttente,
            'nouvelles_inscriptions'    => $nouveaux,
            'reinscriptions'            => $reinscriptions,
            'taux_conversion'           => $preinscriptionsTotal > 0 ? round(($preinscriptionsValidees / $preinscriptionsTotal) * 100, 1) : null,
        ];

        // =========================================================================
        // 8. MUTATIONS / TRANSFERTS
        // =========================================================================
        $mutationsQuery = MutationCatechumene::where('paroisse_configuration_id', $paroisseId)
            ->where('annee_catechese_id', $anneeId);

        $mutationsTotal = (clone $mutationsQuery)->count();
        $mutationsParStatut = (clone $mutationsQuery)
            ->select('statut', DB::raw('count(*) as total'))
            ->groupBy('statut')
            ->get()
            ->map(fn($m) => ['statut' => $m->statut, 'total' => (int) $m->total]);

        $departs = (clone $mutationsQuery)
            ->whereNotNull('paroisse_destination_nom')
            ->where('paroisse_destination_nom', '!=', '')
            ->count();

        $arrivees = (clone $mutationsQuery)
            ->whereNotNull('paroisse_origine_nom')
            ->where('paroisse_origine_nom', '!=', '')
            ->count();

        $mutations = [
            'total'      => $mutationsTotal,
            'departs'    => $departs,
            'arrivees'   => $arrivees,
            'par_statut' => $mutationsParStatut,
        ];

        // =========================================================================
        // 9. BILAN DES ANIMATEURS
        // =========================================================================
        $affectationsAnnee = AffectationAnimateur::where('paroisse_configuration_id', $paroisseId)
            ->where('annee_catechese_id', $anneeId);

        $animateursAffectes = (clone $affectationsAnnee)->distinct('animateur_id')->count('animateur_id');
        $classesAffectees = (clone $affectationsAnnee)->distinct('classe_id')->count('classe_id');

        $seancesClassesAffectees = Seance::where('paroisse_configuration_id', $paroisseId)
            ->where('annee_catechese_id', $anneeId)
            ->whereIn('classe_id', (clone $affectationsAnnee)->select('classe_id'))
            ->count();

        $animateurs = [
            'total'               => $animateursCount,
            'animateurs_affectes' => $animateursAffectes,
            'classes_affectees'   => $classesAffectees,
            'seances_encadrees'   => $seancesClassesAffectees,
            'taux_couverture'     => $classesCount > 0 ? round(($classesAffectees / $classesCount) * 100, 1) : 0,
        ];

        // =========================================================================
        // 10. POINTS D'ATTENTION & ALERTES
        // =========================================================================
        $alertes = [];

        // 10.1 Séances passées sans feuille de présence
        $seancesSansPresence = Seance::where('paroisse_configuration_id', $paroisseId)
            ->where('annee_catechese_id', $anneeId)
            ->where('date_seance', '<', now()->toDateString())
            ->doesntHave('presences')
            ->count();

        if ($seancesSansPresence > 0) {
            $alertes[] = [
                'type'    => 'seances_sans_presence',
                'niveau'  => 'warning',
                'count'   => $seancesSansPresence,
                'message' => "{$seancesSansPresence} séance(s) passée(s) n'ont pas de feuille de présence enregistrée.",
            ];
        }

        // 10.2 Préinscriptions en attente
        if ($preinscriptionsEnAttente > 0) {
            $alertes[] = [
                'type'    => 'preinscriptions_en_attente',
                'niveau'  => 'info',
                'count'   => $preinscriptionsEnAttente,
                'message' => "{$preinscriptionsEnAttente} préinscription(s) sont toujours en attente de traitement.",
            ];
        }

        // 10.3 Classes sans animateur
        $classesSansAnimateur = Classe::where('paroisse_configuration_id', $paroisseId)
            ->where('annee_catechese_id', $anneeId)
            ->where('statut', 'actif')
            ->doesntHave('affectations')
            ->count();

        if ($classesSansAnimateur > 0) {
            $alertes[] = [
                'type'    => 'classes_sans_animateur',
                'niveau'  => 'danger',
                'count'   => $classesSansAnimateur,
                'message' => "{$classesSansAnimateur} classe(s) de catéchèse n'ont aucun animateur affecté.",
            ];
        }

        // 10.4 Candidats aux sacrements restants
        $totalSacrementsRestants = $sacrements['bapteme']['restants'] + $sacrements['premiere_communion']['restants'] + $sacrements['confirmation']['restants'];
        if ($totalSacrementsRestants > 0) {
            $alertes[] = [
                'type'    => 'sacrements_en_cours',
                'niveau'  => 'info',
                'count'   => $totalSacrementsRestants,
                'message' => "{$totalSacrementsRestants} candidat(s) aux sacrements sont en cours de parcours.",
            ];
        }

        // =========================================================================
        // 11. SYNTHÈSE FINALE DE L'ANNÉE
        // =========================================================================
        $conclusion = "L'année pastorale {$annee->libelle} compte un effectif global de {$effectifTotal} catéchumène(s) répartis dans {$classesCount} classe(s) et {$sectionsCount} section(s). ";
        if ($evolution && $evolution['disponible']) {
            $signe = $evolution['difference'] >= 0 ? '+' : '';
            $conclusion .= "Par rapport à l'année précédente ({$evolution['annee_precedente_libelle']}), l'effectif a évolué de {$signe}{$evolution['pourcentage']}% ({$signe}{$evolution['difference']} catéchumène(s)). ";
        }
        $conclusion .= "Le taux de présence moyen sur l'ensemble des séances est de {$tauxPresenceMoyen}%.";

        return [
            'annee'           => [
                'id'         => $annee->uuid,
                'libelle'    => $annee->libelle,
                'date_debut' => $annee->date_debut?->toDateString(),
                'date_fin'   => $annee->date_fin?->toDateString(),
                'statut'     => $annee->statut,
            ],
            'synthese'        => $synthese,
            'effectifs'       => $effectifs,
            'evolution'       => $evolution,
            'assiduite'       => $assiduite,
            'progression'     => $progressionParNiveau,
            'sacrements'      => $sacrements,
            'inscriptions'    => $inscriptions,
            'mutations'       => $mutations,
            'animateurs'      => $animateurs,
            'alertes'         => $alertes,
            'synthese_finale' => [
                'resume'              => $conclusion,
                'taux_assiduite'      => $tauxPresenceMoyen,
                'taux_conversion'     => $inscriptions['taux_conversion'],
                'couverture_classes'  => $animateurs['taux_couverture'],
            ],
        ];
    }
}
