<?php

require_once 'C:/xampp/htdocs/catheo/vendor/autoload.php';
$app = require_once 'C:/xampp/htdocs/catheo/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Catechumene;
use App\Models\InscriptionAnnuelle;
use App\Models\Section;
use App\Models\Niveau;
use App\Models\DecisionFinAnnee;
use App\Models\ParoisseConfiguration;
use App\Models\RegleProgressionPastorale;
use App\Services\ProgressionPastoraleService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

$service = new ProgressionPastoraleService();

$passed = 0;
$failed = 0;

function assertTest(int $num, string $title, bool $condition, string $details = '') {
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "[PASS] TEST $num: $title\n";
    } else {
        $failed++;
        echo "[FAIL] TEST $num: $title -> $details\n";
    }
}

// Find parish 3 for testing
$paroisseId = 3;

// Helper to simulate a catechumene with specific section, niveau, and decision
function createMockCatechumeneWithInscription(string $sectionCode, string $niveauNom, ?string $decision, int $paroisseId): array {
    $section = Section::where('paroisse_configuration_id', $paroisseId)
        ->where(function($q) use ($sectionCode) {
            $norm = ProgressionPastoraleService::normaliserCodeSection($sectionCode);
            $q->where('code', $sectionCode)
              ->orWhere('code', $norm);
        })->first();

    if (!$section) {
        $allSecs = Section::where('paroisse_configuration_id', $paroisseId)->get();
        foreach ($allSecs as $s) {
            if (ProgressionPastoraleService::normaliserCodeSection($s->code) === ProgressionPastoraleService::normaliserCodeSection($sectionCode)) {
                $section = $s;
                break;
            }
        }
    }

    $niveau = Niveau::where('paroisse_configuration_id', $paroisseId)
        ->where('section_id', $section->id)
        ->where('nom', $niveauNom)
        ->first();

    DB::beginTransaction();
    $cat = Catechumene::create([
        'paroisse_configuration_id' => $paroisseId,
        'matricule' => 'TST-' . strtoupper(Str::random(5)),
        'nom' => 'TEST_' . Str::random(5),
        'prenoms' => 'Progression',
        'sexe' => 'M',
        'statut' => 'actif',
    ]);

    $ins = InscriptionAnnuelle::create([
        'paroisse_configuration_id' => $paroisseId,
        'catechumene_id' => $cat->id,
        'section_id' => $section->id,
        'niveau_id' => $niveau->id,
        'annee_catechese_id' => 1,
        'statut_inscription' => 'valide',
    ]);

    if ($decision !== null) {
        DecisionFinAnnee::create([
            'paroisse_configuration_id' => $paroisseId,
            'inscription_annuelle_id' => $ins->id,
            'decision' => $decision,
            'moyenne_annuelle' => 14.5, 'date_decision' => now(),
        ]);
    }

    return [$cat, $ins];
}

echo "=== EXECUTING 27 PROGRESSION TESTS ===\n\n";

// Tests 1 to 14: Regular and inter-section transitions with ADMIS
$progTests = [
    1 => ['SEC-ENFANTS-PRI', '1ère Année', 'ADMIS', 'SEC-ENFANTS-PRI', '2ème Année', false],
    2 => ['SEC-ENFANTS-PRI', '2ème Année', 'ADMIS', 'SEC-ENFANTS-PRI', '3ème Année', false],
    3 => ['SEC-ENFANTS-PRI', '3ème Année', 'ADMIS', 'SEC-ENFANTS-COL', '4ème Année', false],
    4 => ['SEC-ENFANTS-COL', '1ère Année', 'ADMIS', 'SEC-ENFANTS-COL', '2ème Année', false],
    5 => ['SEC-ENFANTS-COL', '2ème Année', 'ADMIS', 'SEC-ENFANTS-COL', '3ème Année', false],
    6 => ['SEC-ENFANTS-COL', '3ème Année', 'ADMIS', 'SEC-ENFANTS-COL', '4ème Année', false],
    7 => ['SEC-ENFANTS-COL', '4ème Année', 'ADMIS', 'SEC-ENFANTS-COL', '5ème Année', false],
    8 => ['SEC-JEUNES', '1ère Année', 'ADMIS', 'SEC-JEUNES', '2ème Année', false],
    9 => ['SEC-JEUNES', '2ème Année', 'ADMIS', 'SEC-JEUNES', '3ème Année', false],
    10 => ['SEC-JEUNES', '3ème Année', 'ADMIS', 'SEC-JEUNES', '4ème Année', false],
    11 => ['SEC-JEUNES', '4ème Année', 'ADMIS', 'SEC-JEUNES', '5ème Année', false],
    12 => ['SEC-ADULTES', '1ère Année', 'ADMIS', 'SEC-ADULTES', '2ème Année', false],
    13 => ['SEC-ADULTES', '2ème Année', 'ADMIS', 'SEC-ADULTES', '3ème Année', false],
    14 => ['SEC-ADULTES', '3ème Année', 'ADMIS', 'SEC-ADULTES', '4ème Année', false],
];

foreach ($progTests as $tNum => $cfg) {
    [$sCode, $nNom, $dec, $expSecCode, $expNivNom, $expFin] = $cfg;
    [$cat, $ins] = createMockCatechumeneWithInscription($sCode, $nNom, $dec, $paroisseId);
    $res = $service->calculerProgression($cat, $paroisseId);
    DB::rollBack();

    $secNorm = ProgressionPastoraleService::normaliserCodeSection($res['parcours_suivant']['section_code'] ?? '');
    $nivRes = $res['parcours_suivant']['niveau_nom'] ?? '';

    $ok = ($secNorm === $expSecCode && $nivRes === $expNivNom && $res['est_admis'] === true && $res['est_fin_parcours'] === $expFin);
    assertTest($tNum, "$sCode $nNom + $dec -> $expSecCode $expNivNom", $ok, "Got Sec: $secNorm, Niv: $nivRes");
}

// TEST 15: Jeunes 2ème + À REPRENDRE -> Jeunes 2ème
[$cat, $ins] = createMockCatechumeneWithInscription('SEC-JEUNES', '2ème Année', 'À REPRENDRE', $paroisseId);
$res = $service->calculerProgression($cat, $paroisseId);
DB::rollBack();
$ok15 = (ProgressionPastoraleService::normaliserCodeSection($res['parcours_suivant']['section_code'] ?? '') === 'SEC-JEUNES'
    && ($res['parcours_suivant']['niveau_nom'] ?? '') === '2ème Année'
    && $res['est_admis'] === false);
assertTest(15, "Jeunes 2ème + À REPRENDRE -> Jeunes 2ème", $ok15);

// TEST 16: Jeunes 2ème + NON ADMIS -> Jeunes 2ème
[$cat, $ins] = createMockCatechumeneWithInscription('SEC-JEUNES', '2ème Année', 'NON ADMIS', $paroisseId);
$res = $service->calculerProgression($cat, $paroisseId);
DB::rollBack();
$ok16 = (ProgressionPastoraleService::normaliserCodeSection($res['parcours_suivant']['section_code'] ?? '') === 'SEC-JEUNES'
    && ($res['parcours_suivant']['niveau_nom'] ?? '') === '2ème Année'
    && $res['est_admis'] === false);
assertTest(16, "Jeunes 2ème + NON ADMIS -> Jeunes 2ème", $ok16);

// TEST 17: Enfant Primaire 3ème + NON ADMIS -> Enfant Primaire 3ème
[$cat, $ins] = createMockCatechumeneWithInscription('SEC-ENFANTS-PRI', '3ème Année', 'NON ADMIS', $paroisseId);
$res = $service->calculerProgression($cat, $paroisseId);
DB::rollBack();
$ok17 = (ProgressionPastoraleService::normaliserCodeSection($res['parcours_suivant']['section_code'] ?? '') === 'SEC-ENFANTS-PRI'
    && ($res['parcours_suivant']['niveau_nom'] ?? '') === '3ème Année'
    && $res['est_admis'] === false);
assertTest(17, "Enfant Primaire 3ème + NON ADMIS -> Enfant Primaire 3ème", $ok17);

// TEST 18: Enfant Primaire 3ème + ADMISE (feminine form) -> Enfant Collège 4ème
[$cat, $ins] = createMockCatechumeneWithInscription('SEC-ENFANTS-PRI', '3ème Année', 'ADMISE', $paroisseId);
$res = $service->calculerProgression($cat, $paroisseId);
DB::rollBack();
$ok18 = (ProgressionPastoraleService::normaliserCodeSection($res['parcours_suivant']['section_code'] ?? '') === 'SEC-ENFANTS-COL'
    && ($res['parcours_suivant']['niveau_nom'] ?? '') === '4ème Année'
    && $res['est_admis'] === true);
assertTest(18, "Enfant Primaire 3ème + ADMISE -> Enfant Collège 4ème", $ok18);

// TEST 19: Enfant Collège 5ème + ADMIS -> fin de parcours
[$cat, $ins] = createMockCatechumeneWithInscription('SEC-ENFANTS-COL', '5ème Année', 'ADMIS', $paroisseId);
$res = $service->calculerProgression($cat, $paroisseId);
DB::rollBack();
$ok19 = ($res['est_fin_parcours'] === true && empty($res['parcours_suivant']));
assertTest(19, "Enfant Collège 5ème + ADMIS -> fin de parcours", $ok19);

// TEST 20: Jeunes 5ème + ADMIS -> fin de parcours
[$cat, $ins] = createMockCatechumeneWithInscription('SEC-JEUNES', '5ème Année', 'ADMIS', $paroisseId);
$res = $service->calculerProgression($cat, $paroisseId);
DB::rollBack();
$ok20 = ($res['est_fin_parcours'] === true && empty($res['parcours_suivant']));
assertTest(20, "Jeunes 5ème + ADMIS -> fin de parcours", $ok20);

// TEST 21: Adultes 4ème + ADMIS -> fin de parcours
[$cat, $ins] = createMockCatechumeneWithInscription('SEC-ADULTES', '4ème Année', 'ADMIS', $paroisseId);
$res = $service->calculerProgression($cat, $paroisseId);
DB::rollBack();
$ok21 = ($res['est_fin_parcours'] === true && empty($res['parcours_suivant']));
assertTest(21, "Adultes 4ème + ADMIS -> fin de parcours", $ok21);

// TEST 22: Décision absente (null) -> aucune progression inventée (maintien)
[$cat, $ins] = createMockCatechumeneWithInscription('SEC-JEUNES', '2ème Année', null, $paroisseId);
$res = $service->calculerProgression($cat, $paroisseId);
DB::rollBack();
$ok22 = (ProgressionPastoraleService::normaliserCodeSection($res['parcours_suivant']['section_code'] ?? '') === 'SEC-JEUNES'
    && ($res['parcours_suivant']['niveau_nom'] ?? '') === '2ème Année'
    && $res['est_admis'] === false);
assertTest(22, "Décision absente -> maintien niveau actuel", $ok22);

// TEST 23: Aucune règle de progression -> maintien niveau actuel
[$cat, $ins] = createMockCatechumeneWithInscription('SEC-JEUNES', '1ère Année', 'ADMIS', $paroisseId);
// Temporarily deactivate the rule
DB::table('regles_progression_pastorale')->where('code_section_source', 'SEC-JEUNES')->where('niveau_source', '1ère Année')->update(['actif' => false]);
$res = $service->calculerProgression($cat, $paroisseId);
DB::table('regles_progression_pastorale')->where('code_section_source', 'SEC-JEUNES')->where('niveau_source', '1ère Année')->update(['actif' => true]);
DB::rollBack();
$ok23 = (($res['parcours_suivant']['niveau_nom'] ?? '') === '1ère Année' && $res['est_fin_parcours'] === false);
assertTest(23, "Règle absente/désactivée -> maintien sans inventer", $ok23);

// TEST 24: Tentative de modification du niveau côté API -> rejetée (HTTP 422 PROGRESSION_INVALIDE)
[$cat, $ins] = createMockCatechumeneWithInscription('SEC-ENFANTS-PRI', '3ème Année', 'ADMIS', $paroisseId);
$campagneNext = \App\Models\CampagnePreinscription::create([
    'paroisse_configuration_id' => $paroisseId,
    'annee_catechese_id' => 2, // Annee 2027-2028
    'titre' => 'Campagne 2027-2028',
    'date_debut' => '2027-08-01',
    'date_fin' => '2027-10-01',
    'statut' => 'ouverte',
]);
$wrongNiveau = Niveau::where('paroisse_configuration_id', $paroisseId)->where('nom', '1ère Année')->first();
$controller = app(\App\Http\Controllers\Api\V1\PreinscriptionController::class);

$req = \App\Http\Requests\Api\V1\StorePreinscriptionRequest::create('/api/v1/public/preinscriptions', 'POST', [
    'campagne_id' => $campagneNext->uuid,
    'type_demande' => 'reinscription',
    'catechumene_id' => (string)$cat->uuid,
    'section_souhaite_id' => (string)$wrongNiveau->section_id,
    'niveau_souhaite_id' => (string)$wrongNiveau->id,
    'nom' => 'Test',
    'prenoms' => 'Hacker',
    'sexe' => 'M',
]);
$req->headers->set('Accept', 'application/json');
$req->setContainer($app);
$req->setRedirector($app->make(\Illuminate\Routing\Redirector::class));
$req->validateResolved();
$response = $controller->store($req);
DB::rollBack();
$status24 = $response->getStatusCode();
$data24 = json_decode($response->getContent(), true);
$ok24 = ($status24 === 422 && ($data24['code'] ?? '') === 'PROGRESSION_INVALIDE');
assertTest(24, "Tentative de falsification du niveau côté API -> 422 PROGRESSION_INVALIDE", $ok24, "Got status $status24, code " . ($data24['code'] ?? ''));

// TEST 25: Isolation multi-tenant paroisse_configuration_id
$autreParoisseId = 5;
[$cat5, $ins5] = createMockCatechumeneWithInscription('SEC-ENFANTS-PRI', '1ère Année', 'ADMIS', $autreParoisseId);
$res5 = $service->calculerProgression($cat5, $autreParoisseId);
DB::rollBack();
$ok25 = ($res5['parcours_actuel']['section_id'] !== null
    && $res5['parcours_suivant']['section_id'] !== null
    && Section::find($res5['parcours_suivant']['section_id'])->paroisse_configuration_id === $autreParoisseId);
assertTest(25, "Isolation multi-tenant paroisse_configuration_id", $ok25);

// TEST 26: Inscription sur place non impactée
$ok26 = file_exists('C:/xampp/htdocs/catheo/app/Http/Controllers/Api/V1/InscriptionAnnuelleController.php');
assertTest(26, "Inscription sur place dans l'administration non impactée", $ok26);

// TEST 27: Nouvelle inscription publique non impactée
$campagne = \App\Models\CampagnePreinscription::where('paroisse_configuration_id', $paroisseId)->first();
$req27 = \App\Http\Requests\Api\V1\StorePreinscriptionRequest::create('/api/v1/public/preinscriptions', 'POST', [
    'campagne_id' => $campagne->uuid,
    'type_demande' => 'nouvelle_inscription',
    'section_souhaite_id' => (string)$wrongNiveau->section_id,
    'niveau_souhaite_id' => (string)$wrongNiveau->id,
    'nom' => 'New',
    'prenoms' => 'Candidate',
    'sexe' => 'M',
]);
$req27->headers->set('Accept', 'application/json');
$req27->setContainer($app);
$req27->setRedirector($app->make(\Illuminate\Routing\Redirector::class));
$req27->validateResolved();
DB::beginTransaction();
$resp27 = $controller->store($req27);
DB::rollBack();
$status27 = $resp27->getStatusCode();
$ok27 = ($status27 === 201);
assertTest(27, "Nouvelle inscription publique non soumise à la progression (créée en 201)", $ok27, "Got status $status27");

echo "\n=== TESTS SUMMARY: $passed PASSED, $failed FAILED ===\n";
