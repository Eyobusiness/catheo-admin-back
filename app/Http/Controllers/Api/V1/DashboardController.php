<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AffectationAnimateur;
use App\Models\Animateur;
use App\Models\AnneeCatechese;
use App\Models\AuditLog;
use App\Models\BulletinTrimestriel;
use App\Models\CaisseParoissiale;
use App\Models\Catechumene;
use App\Models\Classe;
use App\Models\Evaluation;
use App\Models\InscriptionAnnuelle;
use App\Models\Niveau;
use App\Models\Note;
use App\Models\Paiement;
use App\Models\Preinscription;
use App\Models\Presence;
use App\Models\Seance;
use App\Models\Section;
use App\Models\Tarif;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Résumé du Tableau de Bord Universel (S'adapte automatiquement au type de compte : Super Admin, Admin Paroissial, Animateur ou Parent).
     */
    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->user_type === 'super_admin' || $user->profil?->code === 'SUPER_ADMIN' || !$user->paroisse_configuration_id) {
            return $this->superAdminDashboard($request);
        }

        if ($user->user_type === 'animateur') {
            return $this->animateurDashboard($request);
        }

        if ($user->user_type === 'parent') {
            return $this->parentDashboard($request);
        }

        return $this->adminDashboard($request);
    }

    /**
     * Tableau de bord Administrateur Paroissial / Super Admin.
     */
    public function adminDashboard(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        // 1. KPI En-tête
        $totalCatechumenes = Catechumene::where('paroisse_configuration_id', $paroisseId)->where('statut', 'actif')->count();
        $totalSections = Section::where('paroisse_configuration_id', $paroisseId)->count();
        $totalClasses = Classe::where('paroisse_configuration_id', $paroisseId)->count();
        $totalAnimateurs = Animateur::where('paroisse_configuration_id', $paroisseId)->count();
        $preinscriptionsEnAttente = Preinscription::where('paroisse_configuration_id', $paroisseId)->where('statut', 'en_attente')->count();

        // 2. Répartition par Section
        $sections = Section::where('paroisse_configuration_id', $paroisseId)->get();
        $repartitionSections = [];
        foreach ($sections as $sec) {
            $count = Catechumene::where('paroisse_configuration_id', $paroisseId)
                ->whereHas('inscriptionsAnnuelles.niveau', function ($q) use ($sec) {
                    $q->where('section_id', $sec->id);
                })->count();

            $pct = $totalCatechumenes > 0 ? round(($count / $totalCatechumenes) * 100, 1) : 0;
            $repartitionSections[] = [
                'id' => $sec->uuid,
                'nom' => $sec->nom,
                'effectif' => $count,
                'pourcentage' => $pct,
            ];
        }

        // 3. Répartition par Niveau
        $niveaux = Niveau::where('paroisse_configuration_id', $paroisseId)->get();
        $repartitionNiveaux = [];
        foreach ($niveaux as $niv) {
            $count = Catechumene::where('paroisse_configuration_id', $paroisseId)
                ->whereHas('inscriptionsAnnuelles', function ($q) use ($niv) {
                    $q->where('niveau_id', $niv->id);
                })->count();

            $pct = $totalCatechumenes > 0 ? round(($count / $totalCatechumenes) * 100, 1) : 0;
            $repartitionNiveaux[] = [
                'id' => $niv->uuid,
                'nom' => $niv->nom,
                'effectif' => $count,
                'pourcentage' => $pct,
            ];
        }

        // 4. Situation Financière
        $montantEncaisse = (float) Paiement::where('paroisse_configuration_id', $paroisseId)->where('statut', 'valide')->sum('montant_total');
        $tarifMoyen = (float) Tarif::where('paroisse_configuration_id', $paroisseId)->where('type_tarif', 'inscription')->avg('montant') ?: 15000;
        $montantAttendu = $totalCatechumenes * $tarifMoyen;
        if ($montantAttendu < $montantEncaisse) {
            $montantAttendu = $montantEncaisse * 1.33;
        }
        $resteAPayer = max(0, $montantAttendu - $montantEncaisse);
        $tauxRecouvrement = $montantAttendu > 0 ? round(($montantEncaisse / $montantAttendu) * 100, 1) : 100;

        // 5. Effectifs par Classe
        $classes = Classe::where('paroisse_configuration_id', $paroisseId)->with('niveau.section')->get();
        $effectifsClasses = [];
        foreach ($classes as $cl) {
            $effectifActuel = InscriptionAnnuelle::where('classe_id', $cl->id)->count();
            $capaciteMax = $cl->capacite_max ?? 30;
            $pct = $capaciteMax > 0 ? round(($effectifActuel / $capaciteMax) * 100, 1) : 0;

            $effectifsClasses[] = [
                'id' => $cl->uuid,
                'nom' => $cl->nom,
                'section_nom' => $cl->niveau?->section?->nom ?? 'Général',
                'effectif_actuel' => $effectifActuel,
                'capacite_max' => $capaciteMax,
                'pourcentage' => $pct,
            ];
        }

        // 6. Préparation des Sacrements
        $baptemeCandidats = Catechumene::where('paroisse_configuration_id', $paroisseId)->where('est_baptise', false)->count();
        $communionCandidats = Catechumene::where('paroisse_configuration_id', $paroisseId)
            ->whereHas('inscriptionsAnnuelles.niveau', function ($q) {
                $q->where('nom', 'like', '%2%')->orWhere('nom', 'like', '%communion%');
            })->count();
        $confirmationCandidats = Catechumene::where('paroisse_configuration_id', $paroisseId)
            ->whereHas('inscriptionsAnnuelles.niveau', function ($q) {
                $q->where('nom', 'like', '%3%')->orWhere('nom', 'like', '%confirmation%');
            })->count();

        // 7. Alertes & Notifications
        $paiementsEnRetard = InscriptionAnnuelle::where('paroisse_configuration_id', $paroisseId)->where('frais_inscription_payes', false)->count();
        $catechumenesNonAffectes = InscriptionAnnuelle::where('paroisse_configuration_id', $paroisseId)->whereNull('classe_id')->count();
        $documentsManquants = Preinscription::where('paroisse_configuration_id', $paroisseId)->whereNull('photo_url')->count();

        // 8. Activités Récentes
        $activites = AuditLog::with('user')
            ->where('paroisse_configuration_id', $paroisseId)
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->uuid,
                    'action' => $log->action,
                    'entite' => $log->entite_type,
                    'description' => "{$log->action} sur {$log->entite_type}",
                    'auteur' => $log->user ? $log->user->name : 'Système',
                    'date' => $log->created_at->diffForHumans(),
                ];
            });

        return response()->json([
            'status' => 'success',
            'user_type' => 'admin',
            'data' => [
                'kpis' => [
                    'total_catechumenes' => $totalCatechumenes,
                    'total_sections' => $totalSections,
                    'total_classes' => $totalClasses,
                    'total_animateurs' => $totalAnimateurs,
                    'preinscriptions_en_attente' => $preinscriptionsEnAttente,
                ],
                'repartition_sections' => $repartitionSections,
                'repartition_niveaux' => $repartitionNiveaux,
                'situation_financiere' => [
                    'montant_attendu' => $montantAttendu,
                    'montant_encaisse' => $montantEncaisse,
                    'reste_a_payer' => $resteAPayer,
                    'taux_recouvrement' => $tauxRecouvrement,
                ],
                'effectifs_classes' => $effectifsClasses,
                'preparation_sacrements' => [
                    'bapteme_candidats' => $baptemeCandidats,
                    'premiere_communion_candidats' => $communionCandidats,
                    'confirmation_candidats' => $confirmationCandidats,
                ],
                'alertes' => [
                    'preinscriptions_non_validees' => $preinscriptionsEnAttente,
                    'paiements_en_retard' => $paiementsEnRetard,
                    'catechumenes_non_affectes' => $catechumenesNonAffectes,
                    'documents_manquants' => $documentsManquants,
                ],
                'activites_recentes' => $activites,
            ],
        ]);
    }

    /**
     * Tableau de bord Animateur / Catéchiste (Application Mobile & Web).
     */
    public function animateurDashboard(Request $request): JsonResponse
    {
        $user = $request->user()->load('animateur');
        $animateur = $user->animateur 
            ?? Animateur::where('telephone', $user->telephone)->first() 
            ?? Animateur::where('email', $user->email)->first()
            ?? Animateur::first();

        if (!$animateur) {
            return response()->json([
                'status'    => 'success',
                'user_type' => 'animateur',
                'data'      => [
                    'animateur' => [
                        'id'        => $user->uuid,
                        'nom'       => $user->nom,
                        'prenoms'   => $user->prenoms,
                        'matricule' => 'ANIM-000',
                        'telephone' => $user->telephone,
                    ],
                    'kpis' => [
                        'total_classes'       => 0,
                        'total_catechumenes'  => 0,
                        'seances_effectuees'  => 0,
                        'taux_presence_moyen' => 100,
                    ],
                    'classes'             => [],
                    'prochaine_seance'    => null,
                    'evaluations_a_saisir'=> [],
                ],
            ]);
        }

        // 1. Classes affectées
        $affectations = AffectationAnimateur::with(['classe.niveau.section', 'anneeCatechese'])
            ->where('animateur_id', $animateur->id)
            ->get();

        $classesList = [];
        $classIds = [];

        foreach ($affectations as $aff) {
            if ($aff->classe) {
                $classIds[] = $aff->classe->id;
                $effectif = InscriptionAnnuelle::where('classe_id', $aff->classe->id)->count();

                $classesList[] = [
                    'id' => $aff->classe->uuid,
                    'nom' => $aff->classe->nom,
                    'section' => $aff->classe->niveau?->section?->nom ?? 'Section',
                    'niveau' => $aff->classe->niveau?->nom ?? 'Niveau',
                    'lieu_rassemblement' => $aff->classe->lieu_rassemblement,
                    'jour_rencontre' => $aff->classe->jour_rencontre,
                    'horaire' => "{$aff->classe->heure_debut} - {$aff->classe->heure_fin}",
                    'role' => $aff->role_animateur ?? 'Titulaire',
                    'effectif' => $effectif,
                ];
            }
        }

        $totalCatechumenesSupervises = InscriptionAnnuelle::whereIn('classe_id', $classIds)->count();

        // 2. Prochaine Séance planifiée
        $prochaineSeance = Seance::whereIn('classe_id', $classIds)
            ->where('date_seance', '>=', now()->toDateString())
            ->orderBy('date_seance')
            ->with('classe')
            ->first();

        // 3. Évaluations et notes à saisir
        $evaluationsSaisir = Evaluation::whereIn('classe_id', $classIds)
            ->where('statut', 'actif')
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($ev) {
                $saisies = Note::where('evaluation_id', $ev->id)->count();
                $totalEleves = InscriptionAnnuelle::where('classe_id', $ev->classe_id)->count();
                return [
                    'id' => $ev->uuid,
                    'titre' => $ev->titre,
                    'type_eval' => $ev->type_eval,
                    'date_evaluation' => $ev->date_evaluation?->toDateString(),
                    'saisies_effectuees' => $saisies,
                    'total_eleves' => $totalEleves,
                    'complete' => $totalEleves > 0 && $saisies >= $totalEleves,
                ];
            });

        // 4. Statistiques de présence
        $seancesPasseesCount = Seance::whereIn('classe_id', $classIds)->where('date_seance', '<=', now()->toDateString())->count();
        $totalPresences = Presence::whereHas('seance', function ($q) use ($classIds) {
            $q->whereIn('classe_id', $classIds);
        })->where('statut_presence', 'present')->count();
        $totalAbsences = Presence::whereHas('seance', function ($q) use ($classIds) {
            $q->whereIn('classe_id', $classIds);
        })->where('statut_presence', 'absent')->count();
        $totalFiches = $totalPresences + $totalAbsences;
        $tauxPresenceGlobal = $totalFiches > 0 ? round(($totalPresences / $totalFiches) * 100, 1) : 100;

        return response()->json([
            'status' => 'success',
            'user_type' => 'animateur',
            'data' => [
                'animateur' => [
                    'id' => $animateur->uuid,
                    'nom' => $animateur->nom,
                    'prenoms' => $animateur->prenoms,
                    'matricule' => $animateur->matricule,
                    'telephone' => $animateur->telephone,
                ],
                'kpis' => [
                    'total_classes' => count($classesList),
                    'total_catechumenes' => $totalCatechumenesSupervises,
                    'seances_effectuees' => $seancesPasseesCount,
                    'taux_presence_moyen' => $tauxPresenceGlobal,
                ],
                'classes' => $classesList,
                'prochaine_seance' => $prochaineSeance ? [
                    'id' => $prochaineSeance->uuid,
                    'titre' => $prochaineSeance->theme ?? 'Séance de catéchèse',
                    'classe_nom' => $prochaineSeance->classe?->nom,
                    'date_seance' => $prochaineSeance->date_seance?->toDateString(),
                    'heure_debut' => $prochaineSeance->heure_debut,
                    'heure_fin' => $prochaineSeance->heure_fin,
                    'lieu' => $prochaineSeance->lieu,
                ] : null,
                'evaluations_a_saisir' => $evaluationsSaisir,
            ],
        ]);
    }

    /**
     * Tableau de bord Parent / Tuteur (Application Mobile & Web).
     */
    public function parentDashboard(Request $request): JsonResponse
    {
        $user = $request->user()->load('catechumene');
        $catechumene = $user->catechumene
            ?? Catechumene::where('code_catechumene', $user->username)
                ->orWhere('telephone_tuteur', $user->telephone)
                ->first();

        if (!$catechumene) {
            return response()->json([
                'status' => 'error',
                'message' => 'Aucun catéchumène/enfant rattaché à ce compte parent.',
            ], 404);
        }

        // 1. Inscription actuelle
        $inscription = InscriptionAnnuelle::with(['anneeCatechese', 'niveau.section', 'classe'])
            ->where('catechumene_id', $catechumene->id)
            ->latest()
            ->first();

        // 2. Dernières notes de l'enfant
        $notes = Note::with('evaluation')
            ->where('catechumene_id', $catechumene->id)
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($n) {
                return [
                    'id' => $n->uuid,
                    'evaluation_titre' => $n->evaluation?->titre ?? 'Évaluation',
                    'type_eval' => $n->evaluation?->type_eval ?? 'interrogation',
                    'note_obtenue' => (float) $n->note_obtenue,
                    'note_max' => (float) ($n->evaluation?->note_max ?? 20.0),
                    'appreciation' => $n->appreciation ?? Evaluation::calculateAppreciation($n->note_obtenue),
                    'date_evaluation' => $n->evaluation?->date_evaluation?->toDateString(),
                ];
            });

        $moyenneCalculée = $notes->count() > 0 ? round($notes->avg('note_obtenue'), 2) : null;

        // 3. Présences de l'enfant
        $presences = Presence::with('seance')
            ->where('catechumene_id', $catechumene->id)
            ->get();

        $presencesCount = $presences->where('statut_presence', 'present')->count();
        $absencesCount = $presences->where('statut_presence', 'absent')->count();
        $retardsCount = $presences->where('statut_presence', 'retard')->count();
        $totalSeancesCount = $presences->count();
        $tauxPresence = $totalSeancesCount > 0 ? round(($presencesCount / $totalSeancesCount) * 100, 1) : 100;

        // 4. Prochaine séance
        $prochaineSeance = null;
        if ($inscription && $inscription->classe_id) {
            $seance = Seance::where('classe_id', $inscription->classe_id)
                ->where('date_seance', '>=', now()->toDateString())
                ->orderBy('date_seance')
                ->first();

            if ($seance) {
                $prochaineSeance = [
                    'id' => $seance->uuid,
                    'theme' => $seance->theme ?? 'Cours de Catéchèse',
                    'date_seance' => $seance->date_seance?->toDateString(),
                    'heure_debut' => $seance->heure_debut,
                    'heure_fin' => $seance->heure_fin,
                    'lieu' => $seance->lieu ?? $inscription->classe?->lieu_rassemblement,
                ];
            }
        }

        // 5. Situation Financière / Inscription
        $montantTotalPaiements = (float) Paiement::where('catechumene_id', $catechumene->id)->where('statut', 'valide')->sum('montant_total');
        $estRegle = $inscription ? $inscription->frais_inscription_payes : false;

        return response()->json([
            'status' => 'success',
            'user_type' => 'parent',
            'data' => [
                'enfant' => [
                    'id' => $catechumene->uuid,
                    'code_catechumene' => $catechumene->code_catechumene,
                    'nom' => $catechumene->nom,
                    'prenoms' => $catechumene->prenoms,
                    'sexe' => $catechumene->sexe,
                    'date_naissance' => $catechumene->date_naissance?->toDateString(),
                    'photo_path' => $catechumene->photo_path,
                    'est_baptise' => $catechumene->est_baptise,
                ],
                'inscription' => $inscription ? [
                    'code_inscription' => $inscription->code_inscription,
                    'annee' => $inscription->anneeCatechese?->libelle,
                    'section' => $inscription->niveau?->section?->nom,
                    'niveau' => $inscription->niveau?->nom,
                    'classe' => $inscription->classe?->nom,
                    'frais_payes' => $inscription->frais_inscription_payes,
                ] : null,
                'suivi_scolaire' => [
                    'moyenne_generale' => $moyenneCalculée,
                    'appreciation_generale' => $moyenneCalculée ? Evaluation::calculateAppreciation($moyenneCalculée) : 'N/A',
                    'derniere_notes' => $notes,
                ],
                'suivi_presences' => [
                    'taux_presence' => $tauxPresence,
                    'total_seances' => $totalSeancesCount,
                    'presences' => $presencesCount,
                    'absences' => $absencesCount,
                    'retards' => $retardsCount,
                ],
                'situation_financiere' => [
                    'frais_inscription_regles' => $estRegle,
                    'montant_paye' => $montantTotalPaiements,
                ],
                'prochaine_seance' => $prochaineSeance,
            ],
        ]);
    }

    /**
     * Tableau de bord Super Admin / Concepteur SaaS (Statistiques globales de toutes les paroisses).
     */
    public function superAdminDashboard(Request $request): JsonResponse
    {
        $totalParoisses = \App\Models\ParoisseConfiguration::count();
        $totalParoissesActives = \App\Models\ParoisseConfiguration::where('statut', 'actif')->count();
        $totalCatechumenesGlobal = Catechumene::count();
        $totalAnimateursGlobal = Animateur::count();
        $totalUsersGlobal = User::count();
        $volumeFinancierGlobal = (float) Paiement::where('statut', 'valide')->sum('montant_total');

        $paroissesList = \App\Models\ParoisseConfiguration::withCount(['catechumenes', 'classes', 'users'])
            ->latest()
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->uuid,
                    'nom' => $p->nom,
                    'code_paroisse' => $p->code_paroisse,
                    'diocese' => $p->diocese,
                    'ville' => $p->ville,
                    'commune' => $p->commune,
                    'cure_nom' => $p->cure_nom,
                    'telephone' => $p->telephone,
                    'email' => $p->email,
                    'statut' => $p->statut ?? 'actif',
                    'catechumenes_count' => $p->catechumenes_count,
                    'classes_count' => $p->classes_count,
                    'users_count' => $p->users_count,
                    'created_at' => $p->created_at?->toIso8601String(),
                ];
            });

        return response()->json([
            'status' => 'success',
            'user_type' => 'super_admin',
            'data' => [
                'kpis' => [
                    'total_paroisses' => $totalParoisses,
                    'total_paroisses_actives' => $totalParoissesActives,
                    'total_catechumenes_global' => $totalCatechumenesGlobal,
                    'total_animateurs_global' => $totalAnimateursGlobal,
                    'total_utilisateurs_global' => $totalUsersGlobal,
                    'volume_financier_global' => $volumeFinancierGlobal,
                ],
                'paroisses' => $paroissesList,
            ],
        ]);
    }

    /**
     * KPI classiques.
     */
    public function kpis(Request $request): JsonResponse
    {
        return $this->summary($request);
    }

    /**
     * Statistiques d'effectifs.
     */
    public function effectifs(Request $request): JsonResponse
    {
        return $this->summary($request);
    }

    /**
     * Situation financière.
     */
    public function finances(Request $request): JsonResponse
    {
        return $this->summary($request);
    }
}
