<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\MenuController;
use App\Http\Controllers\Api\V1\ProfilController;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Controllers\Api\V1\CatecheseConfigurationController;
use App\Http\Controllers\Api\V1\ApparenceConfigurationController;
use App\Http\Controllers\Api\V1\ResponsableCatecheseController;
use App\Http\Controllers\Api\V1\SauvegardeController;
use App\Http\Controllers\Api\V1\AnneeCatecheseController;
use App\Http\Controllers\Api\V1\SectionController;
use App\Http\Controllers\Api\V1\NiveauController;
use App\Http\Controllers\Api\V1\ClasseController;
use App\Http\Controllers\Api\V1\AnimateurController;
use App\Http\Controllers\Api\V1\AffectationAnimateurController;
use App\Http\Controllers\Api\V1\ModuleTrimestrielController;
use App\Http\Controllers\Api\V1\CampagnePreinscriptionController;
use App\Http\Controllers\Api\V1\PreinscriptionController;
use App\Http\Controllers\Api\V1\CatechumeneController;
use App\Http\Controllers\Api\V1\InscriptionAnnuelleController;
use App\Http\Controllers\Api\V1\ParrainMarraineController;
use App\Http\Controllers\Api\V1\MutationCatechumeneController;
use App\Http\Controllers\Api\V1\SeanceController;
use App\Http\Controllers\Api\V1\EvaluationController;
use App\Http\Controllers\Api\V1\BulletinTrimestrielController;
use App\Http\Controllers\Api\V1\DecisionFinAnneeController;
use App\Http\Controllers\Api\V1\TarifController;
use App\Http\Controllers\Api\V1\OperationPaiementController;
use App\Http\Controllers\Api\V1\PaiementController;
use App\Http\Controllers\Api\V1\VersementController;
use App\Http\Controllers\Api\V1\CaisseParoissialeController;
use App\Http\Controllers\Api\V1\AnnonceController;
use App\Http\Controllers\Api\V1\NotificationLogController;
use App\Http\Controllers\Api\V1\SystemNotificationController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\ExportController;
use App\Http\Controllers\Api\V1\CebController;
use App\Http\Controllers\Api\V1\MouvementController;
use App\Http\Controllers\Api\V1\CalendrierController;
use App\Http\Controllers\Api\V1\ImpressionController;
use App\Http\Controllers\Api\V1\ModeleDocumentController;
use App\Http\Controllers\Api\V1\DocumentGenereController;
use App\Http\Controllers\Api\V1\SacrementController;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
*/

Route::get('/health', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Catheo API v1 is running',
        'timestamp' => now()->toIso8601String(),
    ]);
});

// Configuration publique de la paroisse & catéchèse (utilisée pour les entêtes d'impression et formulaires)
Route::get('/catechese-configuration', [CatecheseConfigurationController::class, 'show']);
Route::get('/paroisse-configuration', [CatecheseConfigurationController::class, 'show']);
Route::get('/apparence-configuration', [ApparenceConfigurationController::class, 'show']);

// Route publique de soumission et consultation de préinscription (Protégée anti-spam)
Route::get('/public/campagnes/{uuid}', [CampagnePreinscriptionController::class, 'showPublic']);
Route::get('/campagnes-preinscriptions/public/{uuid}', [CampagnePreinscriptionController::class, 'showPublic']);
Route::post('/public/preinscriptions', [PreinscriptionController::class, 'store'])->middleware('throttle:preinscription');
Route::post('/preinscriptions', [PreinscriptionController::class, 'store'])->middleware('throttle:preinscription');

// ─────────────────────────────────────────────────────────────────
// DONNÉES PUBLIQUES POUR LE FORMULAIRE DE PRÉINSCRIPTION (SANS JETON)
// ─────────────────────────────────────────────────────────────────
Route::get('/sections', [SectionController::class, 'index']);
Route::get('/sections/{section}', [SectionController::class, 'show']);
Route::get('/niveaux', [NiveauController::class, 'index']);
Route::get('/niveaux/{niveau}', [NiveauController::class, 'show']);
Route::get('/cebs', [CebController::class, 'index']);
Route::get('/cebs/{ceb}', [CebController::class, 'show']);
Route::get('/mouvements', [MouvementController::class, 'index']);
Route::get('/mouvements/{mouvement}', [MouvementController::class, 'show']);
Route::get('/campagnes-preinscriptions', [CampagnePreinscriptionController::class, 'index']);
Route::get('/campagnes-preinscriptions/{campagne}', [CampagnePreinscriptionController::class, 'show']);
Route::get('/catechumenes/matricule/{code}', [CatechumeneController::class, 'showByMatricule'])->middleware('throttle:30,1');

// Authentification & Session Utilisateur (Protégée anti-brute force)
Route::prefix('auth')->group(function () {
    // 1. Connexion Administration & Personnel (Table users)
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('/admin/login', [AuthController::class, 'login'])->middleware('throttle:login');

    // 2. Connexion Animateurs de Catéchèse (Table animateurs)
    Route::post('/animateurs/login', [AuthController::class, 'loginAnimateur'])->middleware('throttle:login');

    // 3. Connexion Parents & Catéchumènes (Table catechumenes)
    Route::post('/parents/login', [AuthController::class, 'loginParent'])->middleware('throttle:login');
    
    // Mot de passe oublié & Réinitialisation par Code OTP 6 chiffres (Publiques avec throttle)
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:6,1');
    Route::post('/verify-code', [AuthController::class, 'verifyResetCode'])->middleware('throttle:10,1');
    Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1');
    
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
        Route::post('/logout', [AuthController::class, 'logout']);
    });
});

// Routes protégées par authentification Sanctum + Permissions CRUD
Route::middleware('auth:sanctum')->group(function () {

    // ─────────────────────────────────────────────────────────────────
    // PHASE 1 — Profils & Utilisateurs
    // Permissions : users.*, settings.manage
    // ─────────────────────────────────────────────────────────────────
    Route::get('/menus', [MenuController::class, 'index']);
    Route::get('/profils/permissions-tree', [ProfilController::class, 'permissionsTree']);
    Route::middleware('permission:settings.manage')->group(function () {
        Route::patch('/profils/{profil}/status', [ProfilController::class, 'toggleStatus']);
        Route::patch('/profils/{profil}/statut', [ProfilController::class, 'toggleStatus']);
        Route::patch('/profils/{profil}/toggle-status', [ProfilController::class, 'toggleStatus']);
        Route::apiResource('profils', ProfilController::class)->except(['index', 'show']);
    });
    Route::get('/profils', [ProfilController::class, 'index']);
    Route::get('/profils/{profil}', [ProfilController::class, 'show']);

    Route::middleware('permission:users.manage')->group(function () {
        Route::patch('/users/{user}/status', [UserController::class, 'updateStatus']);
        Route::patch('/users/{user}/statut', [UserController::class, 'updateStatus']);
        Route::patch('/users/{user}/toggle-status', [UserController::class, 'updateStatus']);
        Route::apiResource('users', UserController::class)->except(['index', 'show']);
    });
    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.manage');
    Route::get('/users/{user}', [UserController::class, 'show'])->middleware('permission:users.manage');

    // ─────────────────────────────────────────────────────────────────
    // PHASE 2 — Paramètres Catéchèse, Paroisse, Apparence & Sauvegardes
    // Permissions : settings.manage
    // ─────────────────────────────────────────────────────────────────
    Route::put('/catechese-configuration', [CatecheseConfigurationController::class, 'update'])
        ->middleware('permission:settings.manage');
    Route::post('/catechese-configuration', [CatecheseConfigurationController::class, 'update'])
        ->middleware('permission:settings.manage');

    Route::put('/paroisse-configuration', [CatecheseConfigurationController::class, 'update'])
        ->middleware('permission:settings.manage');
    Route::post('/paroisse-configuration', [CatecheseConfigurationController::class, 'update'])
        ->middleware('permission:settings.manage');
    Route::middleware('permission:settings.manage')->group(function () {
        Route::put('/apparence-configuration', [ApparenceConfigurationController::class, 'update']);
        Route::post('/apparence-configuration/reset', [ApparenceConfigurationController::class, 'reset']);
    });

    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('/sauvegardes/{sauvegarde}/download', [SauvegardeController::class, 'download']);
        Route::post('/sauvegardes/{sauvegarde}/restaurer', [SauvegardeController::class, 'restaurer']);
        Route::apiResource('sauvegardes', SauvegardeController::class)->except(['update']);
        Route::apiResource('responsables-catechese', ResponsableCatecheseController::class)
            ->parameters(['responsables-catechese' => 'responsable']);
    });

    // ─────────────────────────────────────────────────────────────────
    // PHASE 3 — Organisation Pastorale & Calendrier
    // Permissions : organisation.view | organisation.create | organisation.edit | organisation.delete
    // ─────────────────────────────────────────────────────────────────

    // Lecture
    Route::middleware('permission:organisation.view')->group(function () {
        Route::get('/annee-catecheses', [AnneeCatecheseController::class, 'index']);
        Route::get('/annee-catecheses/current', [AnneeCatecheseController::class, 'current']);
        Route::get('/annee-catecheses/{annee}', [AnneeCatecheseController::class, 'show']);
        Route::get('/classes', [ClasseController::class, 'index']);
        Route::get('/classes/{classe}', [ClasseController::class, 'show']);
        Route::get('/calendriers', [CalendrierController::class, 'index']);
        Route::get('/calendriers/{calendrier}', [CalendrierController::class, 'show']);
        Route::get('/animateurs', [AnimateurController::class, 'index']);
        Route::get('/animateurs/{animateur}', [AnimateurController::class, 'show']);
        Route::get('/affectations-animateurs', [AffectationAnimateurController::class, 'index']);
        Route::get('/affectations-animateurs/{affectation}', [AffectationAnimateurController::class, 'show']);
        Route::get('/modules-trimestriels', [ModuleTrimestrielController::class, 'index']);
        Route::get('/modules-trimestriels/{module}', [ModuleTrimestrielController::class, 'show']);
    });

    // Création
    Route::middleware('permission:organisation.create')->group(function () {
        Route::post('/annee-catecheses', [AnneeCatecheseController::class, 'store']);
        Route::post('/sections', [SectionController::class, 'store']);
        Route::post('/niveaux', [NiveauController::class, 'store']);
        Route::post('/classes', [ClasseController::class, 'store']);
        Route::post('/cebs', [CebController::class, 'store']);
        Route::post('/mouvements', [MouvementController::class, 'store']);
        Route::post('/calendriers', [CalendrierController::class, 'store']);
        Route::post('/animateurs', [AnimateurController::class, 'store']);
        Route::post('/affectations-animateurs', [AffectationAnimateurController::class, 'store']);
        Route::post('/modules-trimestriels', [ModuleTrimestrielController::class, 'store']);
    });

    // Modification
    Route::middleware('permission:organisation.edit')->group(function () {
        Route::patch('/annee-catecheses/{annee}/activate', [AnneeCatecheseController::class, 'activate']);
        Route::put('/annee-catecheses/{annee}', [AnneeCatecheseController::class, 'update']);
        Route::patch('/annee-catecheses/{annee}', [AnneeCatecheseController::class, 'update']);
        Route::patch('/sections/{section}/status', [SectionController::class, 'toggleStatus']);
        Route::put('/sections/{section}', [SectionController::class, 'update']);
        Route::patch('/sections/{section}', [SectionController::class, 'update']);
        Route::patch('/niveaux/{niveau}/status', [NiveauController::class, 'toggleStatus']);
        Route::put('/niveaux/{niveau}', [NiveauController::class, 'update']);
        Route::patch('/niveaux/{niveau}', [NiveauController::class, 'update']);
        Route::patch('/classes/{classe}/status', [ClasseController::class, 'toggleStatus']);
        Route::put('/classes/{classe}', [ClasseController::class, 'update']);
        Route::patch('/classes/{classe}', [ClasseController::class, 'update']);
        Route::patch('/cebs/{ceb}/status', [CebController::class, 'toggleStatus']);
        Route::put('/cebs/{ceb}', [CebController::class, 'update']);
        Route::patch('/cebs/{ceb}', [CebController::class, 'update']);
        Route::patch('/mouvements/{mouvement}/status', [MouvementController::class, 'toggleStatus']);
        Route::put('/mouvements/{mouvement}', [MouvementController::class, 'update']);
        Route::patch('/mouvements/{mouvement}', [MouvementController::class, 'update']);
        Route::patch('/calendriers/{calendrier}/status', [CalendrierController::class, 'updateStatus']);
        Route::patch('/calendriers/{calendrier}/statut', [CalendrierController::class, 'updateStatus']);
        Route::put('/calendriers/{calendrier}', [CalendrierController::class, 'update']);
        Route::patch('/calendriers/{calendrier}', [CalendrierController::class, 'update']);
        Route::patch('/animateurs/{animateur}/status', [AnimateurController::class, 'updateStatus']);
        Route::put('/animateurs/{animateur}', [AnimateurController::class, 'update']);
        Route::patch('/animateurs/{animateur}', [AnimateurController::class, 'update']);
        Route::put('/affectations-animateurs/{affectation}', [AffectationAnimateurController::class, 'update']);
        Route::patch('/affectations-animateurs/{affectation}', [AffectationAnimateurController::class, 'update']);
        Route::patch('/modules-trimestriels/{module}/status', [ModuleTrimestrielController::class, 'toggleStatus']);
        Route::put('/modules-trimestriels/{module}', [ModuleTrimestrielController::class, 'update']);
        Route::patch('/modules-trimestriels/{module}', [ModuleTrimestrielController::class, 'update']);
    });

    // Suppression
    Route::middleware('permission:organisation.delete')->group(function () {
        Route::delete('/annee-catecheses/{annee}', [AnneeCatecheseController::class, 'destroy']);
        Route::delete('/sections/{section}', [SectionController::class, 'destroy']);
        Route::delete('/niveaux/{niveau}', [NiveauController::class, 'destroy']);
        Route::delete('/classes/{classe}', [ClasseController::class, 'destroy']);
        Route::delete('/cebs/{ceb}', [CebController::class, 'destroy']);
        Route::delete('/mouvements/{mouvement}', [MouvementController::class, 'destroy']);
        Route::delete('/calendriers/{calendrier}', [CalendrierController::class, 'destroy']);
        Route::delete('/animateurs/{animateur}', [AnimateurController::class, 'destroy']);
        Route::delete('/affectations-animateurs/{affectation}', [AffectationAnimateurController::class, 'destroy']);
        Route::delete('/modules-trimestriels/{module}', [ModuleTrimestrielController::class, 'destroy']);
    });

    // ─────────────────────────────────────────────────────────────────
    // PHASE 4 — Catéchumènes & Inscriptions
    // Permissions : catechumenes.view | create | edit | delete
    // ─────────────────────────────────────────────────────────────────
    Route::middleware('permission:catechumenes.view')->group(function () {
        Route::get('/preinscriptions', [PreinscriptionController::class, 'index']);
        Route::get('/preinscriptions/{preinscription}', [PreinscriptionController::class, 'show']);
        Route::get('/catechumenes', [CatechumeneController::class, 'index']);
        Route::get('/catechumenes/{catechumene}/fiche-impression', [CatechumeneController::class, 'ficheImpression']);
        Route::get('/catechumenes/{catechumene}/pdf', [CatechumeneController::class, 'ficheImpression']);
        Route::get('/catechumenes/{catechumene}', [CatechumeneController::class, 'show']);
        Route::get('/inscriptions-annuelles', [InscriptionAnnuelleController::class, 'index']);
        Route::get('/inscriptions-annuelles/{inscription}', [InscriptionAnnuelleController::class, 'show']);
        Route::apiResource('parrains-marraines', ParrainMarraineController::class)->only(['index', 'show']);
        Route::apiResource('mutations-catechumenes', MutationCatechumeneController::class)->only(['index', 'show']);
    });

    Route::middleware('permission:catechumenes.create')->group(function () {
        Route::apiResource('campagnes-preinscriptions', CampagnePreinscriptionController::class)
            ->parameters(['campagnes-preinscriptions' => 'campagne'])
            ->only(['store']);
        Route::post('/catechumenes', [CatechumeneController::class, 'store']);
        Route::post('/preinscriptions/{preinscription}/valider', [PreinscriptionController::class, 'valider']);
        Route::post('/preinscriptions/{preinscription}/rejeter', [PreinscriptionController::class, 'rejeter']);
        Route::post('/inscriptions-annuelles', [InscriptionAnnuelleController::class, 'store']);
        Route::post('/inscriptions-annuelles/affecter', [InscriptionAnnuelleController::class, 'affecter']);
        Route::post('/inscriptions-annuelles/affectations', [InscriptionAnnuelleController::class, 'affecter']);
        Route::apiResource('parrains-marraines', ParrainMarraineController::class)->only(['store']);
        Route::apiResource('mutations-catechumenes', MutationCatechumeneController::class)->only(['store']);
    });

    Route::middleware('permission:catechumenes.edit')->group(function () {
        Route::patch('/campagnes-preinscriptions/{campagne}/status', [CampagnePreinscriptionController::class, 'updateStatus']);
        Route::patch('/campagnes-preinscriptions/{campagne}/statut', [CampagnePreinscriptionController::class, 'updateStatus']);
        Route::put('/campagnes-preinscriptions/{campagne}', [CampagnePreinscriptionController::class, 'update']);
        Route::patch('/campagnes-preinscriptions/{campagne}', [CampagnePreinscriptionController::class, 'update']);
        Route::apiResource('campagnes-preinscriptions', CampagnePreinscriptionController::class)
            ->parameters(['campagnes-preinscriptions' => 'campagne'])
            ->only(['update']);

        Route::put('/preinscriptions/{preinscription}', [PreinscriptionController::class, 'update']);
        Route::patch('/preinscriptions/{preinscription}', [PreinscriptionController::class, 'update']);
        Route::patch('/preinscriptions/{preinscription}/statut', [PreinscriptionController::class, 'updateStatus']);
        Route::patch('/preinscriptions/{preinscription}/status', [PreinscriptionController::class, 'updateStatus']);

        Route::put('/catechumenes/{catechumene}', [CatechumeneController::class, 'update']);
        Route::patch('/catechumenes/{catechumene}', [CatechumeneController::class, 'update']);
        Route::put('/inscriptions-annuelles/{inscription}', [InscriptionAnnuelleController::class, 'update']);
        Route::patch('/inscriptions-annuelles/{inscription}', [InscriptionAnnuelleController::class, 'update']);
        Route::post('/inscriptions-annuelles/{inscription}/affecter', [InscriptionAnnuelleController::class, 'update']);
        Route::apiResource('parrains-marraines', ParrainMarraineController::class)->only(['update']);

        Route::put('/mutations-catechumenes/{mutation}', [MutationCatechumeneController::class, 'update']);
        Route::patch('/mutations-catechumenes/{mutation}', [MutationCatechumeneController::class, 'update']);
        Route::patch('/mutations-catechumenes/{mutation}/statut', [MutationCatechumeneController::class, 'updateStatus']);
        Route::patch('/mutations-catechumenes/{mutation}/status', [MutationCatechumeneController::class, 'updateStatus']);
    });

    Route::middleware('permission:catechumenes.delete')->group(function () {
        Route::apiResource('campagnes-preinscriptions', CampagnePreinscriptionController::class)
            ->parameters(['campagnes-preinscriptions' => 'campagne'])
            ->only(['destroy']);
        Route::delete('/preinscriptions/{preinscription}', [PreinscriptionController::class, 'destroy']);
        Route::delete('/catechumenes/{catechumene}', [CatechumeneController::class, 'destroy']);
        Route::delete('/inscriptions-annuelles/{inscription}', [InscriptionAnnuelleController::class, 'destroy']);
        Route::apiResource('parrains-marraines', ParrainMarraineController::class)->only(['destroy']);
        Route::delete('/mutations-catechumenes/{mutation}', [MutationCatechumeneController::class, 'destroy']);
        Route::apiResource('mutations-catechumenes', MutationCatechumeneController::class)->only(['destroy']);
    });

    // ─────────────────────────────────────────────────────────────────
    // MODULE SACREMENTS (Baptême, 1ère Communion, Confirmation)
    // ─────────────────────────────────────────────────────────────────
    Route::middleware('permission:catechumenes.view')->group(function () {
        Route::get('/sacrements', [SacrementController::class, 'index']);
        Route::get('/sacrements/exceptions', [SacrementController::class, 'indexExceptions']);
        Route::get('/sacrements/exceptions/{exception}', [SacrementController::class, 'showException']);
        Route::get('/sacrements/catechumens', [SacrementController::class, 'catechumens']);
        Route::get('/sacrements/catechumenes', [SacrementController::class, 'catechumens']);
        Route::get('/sacrements/candidats/bapteme', [SacrementController::class, 'candidatsBapteme']);
        Route::get('/sacrements/candidats/premiere-communion', [SacrementController::class, 'candidatsPremiereCommunion']);
        Route::get('/sacrements/candidats/confirmation', [SacrementController::class, 'candidatsConfirmation']);
        Route::get('/catechumenes/{catechumene}/sacrements', [SacrementController::class, 'parcours']);
        Route::get('/catechumens/{catechumene}/sacrements', [SacrementController::class, 'parcours']);
        Route::get('/catechumenes/{catechumene}/sacrements/{sacrement}', [SacrementController::class, 'showParcours']);
        Route::get('/catechumens/{catechumene}/sacrements/{sacrement}', [SacrementController::class, 'showParcours']);
        Route::get('/sacrements/{sacrement}', [SacrementController::class, 'show']);
    });

    Route::middleware('permission:catechumenes.create')->group(function () {
        Route::post('/catechumenes/{catechumene}/sacrements', [SacrementController::class, 'storeParcours']);
        Route::post('/catechumens/{catechumene}/sacrements', [SacrementController::class, 'storeParcours']);
        Route::post('/sacrements/exceptions', [SacrementController::class, 'storeException']);
    });

    Route::middleware('permission:catechumenes.edit')->group(function () {
        Route::put('/catechumenes/{catechumene}/sacrements/{sacrement}', [SacrementController::class, 'updateParcours']);
        Route::put('/catechumens/{catechumene}/sacrements/{sacrement}', [SacrementController::class, 'updateParcours']);
        Route::patch('/catechumenes/{catechumene}/sacrements/{sacrement}', [SacrementController::class, 'updateParcours']);
        Route::patch('/catechumens/{catechumene}/sacrements/{sacrement}', [SacrementController::class, 'updateParcours']);
        Route::put('/sacrements/exceptions/{exception}', [SacrementController::class, 'updateException']);
        Route::patch('/sacrements/exceptions/{exception}', [SacrementController::class, 'updateException']);
    });

    Route::middleware('permission:catechumenes.delete')->group(function () {
        Route::delete('/catechumenes/{catechumene}/sacrements/{sacrement}', [SacrementController::class, 'destroyParcours']);
        Route::delete('/catechumens/{catechumene}/sacrements/{sacrement}', [SacrementController::class, 'destroyParcours']);
        Route::delete('/sacrements/exceptions/{exception}', [SacrementController::class, 'destroyException']);
    });

    // ─────────────────────────────────────────────────────────────────
    // PHASE 5 — Séances, Présences & Évaluations
    // Permissions : presences.manage | evaluations.view/create/edit/delete
    // ─────────────────────────────────────────────────────────────────
    Route::middleware('permission:presences.manage')->group(function () {
        Route::get('/seances/{seance}/presences', [SeanceController::class, 'getPresences']);
        Route::post('/seances/{seance}/presences', [SeanceController::class, 'presences']);
        Route::patch('/seances/{seance}/status', [SeanceController::class, 'updateStatus']);
        Route::patch('/seances/{seance}/statut', [SeanceController::class, 'updateStatus']);
        Route::apiResource('seances', SeanceController::class);
    });

    Route::middleware('permission:evaluations.view')->group(function () {
        Route::get('/evaluations', [EvaluationController::class, 'index']);
        Route::get('/evaluations/classes/{classe}/moyennes', [EvaluationController::class, 'classeMoyennes']);
        Route::get('/evaluations/catechumenes/{catechumene}/synthese', [EvaluationController::class, 'catechumeneSynthese']);
        Route::get('/classes/{classe}/moyennes-evaluations', [EvaluationController::class, 'classeMoyennes']);
        Route::get('/catechumenes/{catechumene}/synthese-evaluations', [EvaluationController::class, 'catechumeneSynthese']);
        Route::get('/evaluations/{evaluation}', [EvaluationController::class, 'show']);
        Route::get('/evaluations/{evaluation}/notes-grid', [EvaluationController::class, 'notesGrid']);
        Route::get('/evaluations/{evaluation}/notes', [EvaluationController::class, 'getNotes']);
        Route::apiResource('bulletins-trimestriels', BulletinTrimestrielController::class)->only(['index', 'show']);
        Route::post('/bulletins-trimestriels/calculer', [BulletinTrimestrielController::class, 'calculer']);
        Route::apiResource('decisions-fin-annee', DecisionFinAnneeController::class)->only(['index', 'show']);
    });

    Route::middleware('permission:evaluations.manage')->group(function () {
        Route::patch('/evaluations/{evaluation}/status', [EvaluationController::class, 'toggleStatus']);
        Route::patch('/evaluations/{evaluation}/statut', [EvaluationController::class, 'toggleStatus']);
        Route::post('/evaluations/{evaluation}/notes', [EvaluationController::class, 'notes']);
        Route::post('/evaluations/{evaluation}/simuler', [EvaluationController::class, 'simuler']);
        Route::apiResource('evaluations', EvaluationController::class)->except(['index', 'show']);
        Route::apiResource('decisions-fin-annee', DecisionFinAnneeController::class)->except(['index', 'show']);
    });

    // ─────────────────────────────────────────────────────────────────
    // PHASE 6 — Finances
    // Permissions : finances.view | finances.create | finances.edit | finances.delete
    // ─────────────────────────────────────────────────────────────────
    Route::middleware('permission:finances.view')->group(function () {
        Route::get('/paiements', [PaiementController::class, 'index']);
        Route::get('/paiements/{uuid}/recu', [PaiementController::class, 'recu']);
        Route::get('/paiements/{uuid}/recu-impression', [PaiementController::class, 'recu']);
        Route::get('/paiements/{uuid}/pdf', [PaiementController::class, 'recu']);
        Route::get('/paiements/{uuid}/recu-pdf', [PaiementController::class, 'recu']);
        Route::get('/paiements/{uuid}', [PaiementController::class, 'show']);
        Route::get('/tarifs', [TarifController::class, 'index']);
        Route::get('/tarifs/{tarif}', [TarifController::class, 'show']);
        Route::apiResource('caisse-paroissiale', CaisseParoissialeController::class)->only(['index']);
        Route::apiResource('versements', VersementController::class)->only(['index', 'show']);
        Route::apiResource('versements-cure', VersementController::class)->only(['index', 'show']);
        Route::get('/operations-paiements', [OperationPaiementController::class, 'index']);
        Route::get('/operations-paiements/{operation}', [OperationPaiementController::class, 'show']);
    });

    Route::middleware('permission:finances.create')->group(function () {
        Route::post('/paiements', [PaiementController::class, 'store']);
        Route::post('/tarifs', [TarifController::class, 'store']);
        Route::post('/tarifs/{tarif}/generer-operations', [OperationPaiementController::class, 'genererParTarif']);
        Route::post('/operations-paiements/generer-par-tarif', [OperationPaiementController::class, 'genererParTarif']);
        Route::post('/inscriptions-annuelles/{inscription}/generer-operation-paiement', [OperationPaiementController::class, 'genererParInscription']);
        Route::post('/operations-paiements/{operation}/payer', [OperationPaiementController::class, 'payer']);
        Route::post('/operations-paiements', [OperationPaiementController::class, 'store']);
        Route::apiResource('caisse-paroissiale', CaisseParoissialeController::class)->only(['store']);
        Route::apiResource('versements', VersementController::class)->only(['store']);
        Route::apiResource('versements-cure', VersementController::class)->only(['store']);
    });

    Route::middleware('permission:finances.edit')->group(function () {
        Route::put('/tarifs/{tarif}', [TarifController::class, 'update']);
        Route::patch('/tarifs/{tarif}', [TarifController::class, 'update']);
        Route::patch('/tarifs/{tarif}/toggle-status', [TarifController::class, 'toggleStatus']);
        Route::patch('/tarifs/{tarif}/statut', [TarifController::class, 'toggleStatus']);
        Route::patch('/tarifs/{tarif}/status', [TarifController::class, 'toggleStatus']);
        Route::put('/operations-paiements/{operation}', [OperationPaiementController::class, 'update']);
        Route::patch('/operations-paiements/{operation}', [OperationPaiementController::class, 'update']);
        Route::apiResource('versements', VersementController::class)->only(['update']);
        Route::apiResource('versements-cure', VersementController::class)->only(['update']);
    });

    Route::middleware('permission:finances.delete')->group(function () {
        Route::post('/paiements/{uuid}/rembourser', [PaiementController::class, 'rembourser']);
        Route::post('/caisse-paroissiale/{uuid}/rembourser', [CaisseParoissialeController::class, 'rembourser']);
        Route::apiResource('caisse-paroissiale', CaisseParoissialeController::class)->only(['destroy']);
        Route::delete('/tarifs/{tarif}', [TarifController::class, 'destroy']);
        Route::delete('/operations-paiements/{operation}', [OperationPaiementController::class, 'destroy']);
        Route::apiResource('versements', VersementController::class)->only(['destroy']);
        Route::apiResource('versements-cure', VersementController::class)->only(['destroy']);
    });

    // ─────────────────────────────────────────────────────────────────
    // PHASE 7 — Communication, Notifications & Audit
    // ─────────────────────────────────────────────────────────────────
    Route::get('/system-notifications', [SystemNotificationController::class, 'index']);
    Route::get('/system-notifications/unread-count', [SystemNotificationController::class, 'unreadCount']);
    Route::get('/system-notifications/alerts-summary', [SystemNotificationController::class, 'alertsSummary']);
    Route::post('/system-notifications/mark-all-read', [SystemNotificationController::class, 'markAllAsRead']);
    Route::delete('/system-notifications/clear-read', [SystemNotificationController::class, 'clearRead']);
    Route::patch('/system-notifications/{id}/read', [SystemNotificationController::class, 'markAsRead']);
    Route::delete('/system-notifications/{id}', [SystemNotificationController::class, 'destroy']);

    Route::get('/notifications', [AnnonceController::class, 'mesNotifications']);
    Route::get('/notifications/unread-count', [AnnonceController::class, 'unreadCount']);
    Route::post('/notifications/marquer-toutes-lues', [AnnonceController::class, 'marquerToutesLues']);
    Route::post('/notifications/{annonce}/marquer-lue', [AnnonceController::class, 'marquerLue']);
    Route::get('/annonces/mes-notifications', [AnnonceController::class, 'mesNotifications']);
    Route::post('/annonces/{annonce}/diffuser', [AnnonceController::class, 'diffuser']);
    Route::patch('/annonces/{annonce}/statut', [AnnonceController::class, 'updateStatus']);
    Route::patch('/annonces/{annonce}/status', [AnnonceController::class, 'updateStatus']);
    Route::apiResource('annonces', AnnonceController::class);
    Route::apiResource('notifications-log', NotificationLogController::class)->only(['index', 'store', 'show']);
    Route::apiResource('audit-logs', AuditLogController::class)->only(['index', 'store', 'show']);

    // ─────────────────────────────────────────────────────────────────
    // PHASE 8 — Tableaux de Bord, Impressions & Exportations
    // ─────────────────────────────────────────────────────────────────
    Route::middleware('permission:dashboard.view')->group(function () {
        Route::prefix('dashboard')->group(function () {
            Route::get('/summary', [DashboardController::class, 'summary']);
            Route::get('/super-admin', [DashboardController::class, 'superAdminDashboard']);
            Route::get('/animateur', [DashboardController::class, 'animateurDashboard']);
            Route::get('/parent', [DashboardController::class, 'parentDashboard']);
            Route::get('/kpis', [DashboardController::class, 'kpis']);
            Route::get('/effectifs', [DashboardController::class, 'effectifs']);
            Route::get('/finances', [DashboardController::class, 'finances']);
            Route::get('/bilan-annuel/{anneeCatecheseId?}', [DashboardController::class, 'bilanAnnuel']);
            Route::get('/rapport-annuel/pdf', [DashboardController::class, 'rapportAnnuelPdf']);
        });
        Route::get('/bilan-annuel/{anneeCatecheseId?}', [DashboardController::class, 'bilanAnnuel']);
        Route::get('/rapports/annuel/pdf', [DashboardController::class, 'rapportAnnuelPdf']);
    });

    Route::prefix('impressions')->group(function () {
        Route::match(['get', 'post'], '/entete', [ImpressionController::class, 'entete']);
        Route::match(['get', 'post'], '/fiche-notes', [ImpressionController::class, 'ficheNotes']);
        Route::match(['get', 'post'], '/fiche-notes/pdf', [ImpressionController::class, 'ficheNotes']);
        Route::match(['get', 'post'], '/fiche-presences', [ImpressionController::class, 'fichePresences']);
        Route::match(['get', 'post'], '/fiche-presences/pdf', [ImpressionController::class, 'fichePresences']);
        Route::match(['get', 'post'], '/liste-presence', [ImpressionController::class, 'listePresence']);
        Route::match(['get', 'post'], '/liste-presence/pdf', [ImpressionController::class, 'listePresence']);
        Route::match(['get', 'post'], '/liste-catechumenes', [ImpressionController::class, 'listeCatechumenes']);
        Route::match(['get', 'post'], '/liste-catechumenes/pdf', [ImpressionController::class, 'listeCatechumenes']);
        Route::match(['get', 'post'], '/suivi-sacramental', [ImpressionController::class, 'suiviSacramental']);
        Route::match(['get', 'post'], '/suivi-sacramental/pdf', [ImpressionController::class, 'suiviSacramental']);
        Route::match(['get', 'post'], '/fiche-bilan-annuel', [ImpressionController::class, 'ficheBilanAnnuel']);
        Route::match(['get', 'post'], '/fiche-bilan-annuel/pdf', [ImpressionController::class, 'ficheBilanAnnuel']);
        Route::match(['get', 'post'], '/fiche-renseignement-bapteme', [ImpressionController::class, 'ficheRenseignementBapteme']);
        Route::match(['get', 'post'], '/fiche-renseignement-bapteme/pdf', [ImpressionController::class, 'ficheRenseignementBapteme']);
        Route::match(['get', 'post'], '/fiche-renseignement-premiere-communion', [ImpressionController::class, 'ficheRenseignementPremiereCommunion']);
        Route::match(['get', 'post'], '/fiche-renseignement-premiere-communion/pdf', [ImpressionController::class, 'ficheRenseignementPremiereCommunion']);
        Route::match(['get', 'post'], '/fiche-renseignement-confirmation', [ImpressionController::class, 'ficheRenseignementConfirmation']);
        Route::match(['get', 'post'], '/fiche-renseignement-confirmation/pdf', [ImpressionController::class, 'ficheRenseignementConfirmation']);
    });

    Route::prefix('exports')->group(function () {
        Route::post('/catechumenes', [ExportController::class, 'catechumenes']);
        Route::post('/presences', [ExportController::class, 'presences']);
        Route::post('/finances', [ExportController::class, 'finances']);
    });

    // ─────────────────────────────────────────────────────────────────
    // PHASE 9 — Documents Officiels (Modèles & Génération)
    // ─────────────────────────────────────────────────────────────────
    Route::get('/modeles-documents/variables-systeme', [ModeleDocumentController::class, 'variablesSysteme']);
    Route::patch('/modeles-documents/{modele}/toggle-status', [ModeleDocumentController::class, 'toggleStatus']);
    Route::post('/modeles-documents/{modele}/generer', [DocumentGenereController::class, 'store']);
    Route::post('/documents-generes/masse', [DocumentGenereController::class, 'genererMasse']);
    Route::get('/documents-generes/{document}/print-data', [DocumentGenereController::class, 'printData']);
    Route::get('/documents-generes/{document}/pdf', [DocumentGenereController::class, 'printData']);
    Route::apiResource('modeles-documents', ModeleDocumentController::class);
    Route::apiResource('documents-generes', DocumentGenereController::class)->except(['update']);
});

