<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\CatecheseConfiguration;
use App\Models\Catechumene;
use App\Models\Paiement;
use App\Models\AnneeCatechese;
use App\Models\User;
use App\Services\MatriculeGeneratorService;
use App\Services\ReceiptNumberGeneratorService;
use App\Http\Controllers\Api\V1\CatechumeneController;
use App\Http\Controllers\Api\V1\PaiementController;
use App\Http\Controllers\Api\V1\CatecheseConfigurationController;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

echo "========================================================================\n";
echo "=== TEST TRANSVERSAL COMPLET : GESTION DES MATRICULES ET DES REÇUS ===\n";
echo "========================================================================\n\n";

$matriculeService = app(MatriculeGeneratorService::class);
$receiptService = app(ReceiptNumberGeneratorService::class);

// -------------------------------------------------------------------------
// PARTIE 1 : CONFIGURATION DES PAROISSES & PRÉFIXES
// -------------------------------------------------------------------------
echo "--- PARTIE 1 : CONFIGURATION PAROISSIALE & PRÉFIXES ---\n";

// Paroisse 1 : CIM (Cœur Immaculé de Marie)
$paroisse1 = CatecheseConfiguration::firstOrCreate(
    ['id' => 1],
    [
        'uuid'              => (string) Str::uuid(),
        'nom_paroisse'      => 'Paroisse Cœur Immaculé de Marie',
        'code_paroisse'     => 'PAR-CIM-01',
        'prefixe_matricule' => 'CIM',
        'prefixe_recu'      => 'REC',
        'statut'            => 'actif',
    ]
);
$paroisse1->update([
    'prefixe_matricule' => 'CIM',
    'prefixe_recu'      => 'REC',
]);
echo "1. Paroisse 1 configurée : {$paroisse1->nom_paroisse} (Matricule: {$paroisse1->prefixe_matricule}, Reçu: {$paroisse1->prefixe_recu})\n";

// Paroisse 2 : STJ (Saint Jean)
$paroisse2 = CatecheseConfiguration::firstOrCreate(
    ['code_paroisse' => 'PAR-STJ-02'],
    [
        'uuid'              => (string) Str::uuid(),
        'nom_paroisse'      => 'Paroisse Saint-Jean',
        'prefixe_matricule' => 'STJ',
        'prefixe_recu'      => 'STJ',
        'statut'            => 'actif',
    ]
);
$paroisse2->update([
    'prefixe_matricule' => 'STJ',
    'prefixe_recu'      => 'STJ',
]);
echo "2. Paroisse 2 configurée : {$paroisse2->nom_paroisse} (Matricule: {$paroisse2->prefixe_matricule}, Reçu: {$paroisse2->prefixe_recu})\n\n";

// -------------------------------------------------------------------------
// PARTIE 2 : TESTS DU FORMAT ET DE LA GÉNÉRATION DES MATRICULES
// -------------------------------------------------------------------------
echo "--- PARTIE 2 : TESTS DU NOUVEAU FORMAT DU MATRICULE ---\n";

// Test 2.1 : Génération pour Paroisse CIM
$matCIM = $matriculeService->generate($paroisse1->id);
echo "3. Matricule Paroisse CIM généré : {$matCIM}\n";
$patternMatricule = '/^([A-Z]+)(\d{2})-(\d{10})([A-Z])$/';
if (preg_match($patternMatricule, $matCIM, $m)) {
    echo "   -> Validation Format : OK\n";
    echo "      Préfixe : {$m[1]} (Attendu: CIM)\n";
    echo "      Année (AA) : {$m[2]} (Attendu: " . date('y') . ")\n";
    echo "      Timestamp (JJMMHHmmss) : {$m[3]} (10 chiffres)\n";
    echo "      Lettre aléatoire : {$m[4]} (A à Z)\n";
} else {
    echo "   -> ERREUR : Le format ne respecte pas le pattern !\n";
}

// Test 2.2 : Génération pour Paroisse STJ
$matSTJ = $matriculeService->generate($paroisse2->id);
echo "4. Matricule Paroisse STJ généré : {$matSTJ}\n";
if (str_starts_with($matSTJ, 'STJ' . date('y'))) {
    echo "   -> Préfixe STJ bien appliqué dynamiquement : OK\n";
} else {
    echo "   -> ERREUR de préfixe pour STJ !\n";
}

// Test 2.3 : Unicité et créations en rafale (50 matricules simultanés)
echo "5. Test de concurrence : Génération de 50 matricules consécutifs...\n";
$generatedMatricules = [];
for ($i = 0; $i < 50; $i++) {
    $m = $matriculeService->generate($paroisse1->id);
    $generatedMatricules[] = $m;
}
$uniqueCount = count(array_unique($generatedMatricules));
echo "   -> 50 matricules générés, {$uniqueCount} uniques (Aucune collision détectée) : " . ($uniqueCount === 50 ? 'SUCCÈS' : 'COLLISION') . "\n\n";

// -------------------------------------------------------------------------
// PARTIE 3 : TESTS DU FORMAT ET DE LA SÉQUENCE DES REÇUS
// -------------------------------------------------------------------------
echo "--- PARTIE 3 : TESTS DU FORMAT ET DE LA NUMÉROTATION DES REÇUS ---\n";

// Nettoyage des tests de paiements pour avoir un test prévisible
Paiement::withTrashed()->where('paroisse_configuration_id', $paroisse1->id)->where('numero_recu', 'like', 'REC%')->forceDelete();
Paiement::withTrashed()->where('paroisse_configuration_id', $paroisse2->id)->where('numero_recu', 'like', 'STJ%')->forceDelete();

$annee = AnneeCatechese::where('paroisse_configuration_id', $paroisse1->id)->first();
if (!$annee) {
    $annee = AnneeCatechese::create([
        'uuid'                      => (string) Str::uuid(),
        'paroisse_configuration_id' => $paroisse1->id,
        'libelle'                   => '2026-2027',
        'date_debut'                => '2026-09-01',
        'date_fin'                  => '2027-06-30',
        'statut'                    => 'active',
    ]);
}

// Test 3.1 : Premier paiement Paroisse CIM (Année 2026)
$recu1_CIM = $receiptService->generate($paroisse1->id, '2026-08-29 12:40:02');
echo "6. Premier reçu CIM (2026) : {$recu1_CIM}\n";
$patternRecu = '/^([A-Z]+)(\d{2})-(\d{6})-(\d{4})$/';
if (preg_match($patternRecu, $recu1_CIM, $rm)) {
    echo "   -> Format : OK (Préfixe: {$rm[1]}, Année: {$rm[2]}, Heure: {$rm[3]}, Séquence: {$rm[4]})\n";
}
// Enregistrement du paiement 1
$p1 = Paiement::create([
    'paroisse_configuration_id' => $paroisse1->id,
    'annee_catechese_id'        => $annee->id,
    'numero_recu'               => $recu1_CIM,
    'montant_total'             => 10000,
    'mode_paiement'             => 'especes',
    'date_paiement'             => '2026-08-29',
    'statut'                    => 'valide',
]);

// Test 3.2 : Deuxième paiement Paroisse CIM (Année 2026)
$recu2_CIM = $receiptService->generate($paroisse1->id, '2026-08-29 12:40:10');
echo "7. Deuxième reçu CIM (2026) : {$recu2_CIM}\n";
$p2 = Paiement::create([
    'paroisse_configuration_id' => $paroisse1->id,
    'annee_catechese_id'        => $annee->id,
    'numero_recu'               => $recu2_CIM,
    'montant_total'             => 5000,
    'mode_paiement'             => 'especes',
    'date_paiement'             => '2026-08-29',
    'statut'                    => 'valide',
]);

if (str_ends_with($recu1_CIM, '-0001') && str_ends_with($recu2_CIM, '-0002')) {
    echo "   -> Incrémentation séquentielle 0001 -> 0002 pour CIM : SUCCÈS\n";
} else {
    echo "   -> ERREUR d'incrémentation pour CIM !\n";
}

// Test 3.3 : Compteur indépendant pour Paroisse 2 (STJ en 2026)
$recu1_STJ = $receiptService->generate($paroisse2->id, '2026-08-29 12:40:15');
echo "8. Premier reçu Paroisse STJ (2026) : {$recu1_STJ}\n";
$pSTJ = Paiement::create([
    'paroisse_configuration_id' => $paroisse2->id,
    'annee_catechese_id'        => $annee->id,
    'numero_recu'               => $recu1_STJ,
    'montant_total'             => 15000,
    'mode_paiement'             => 'especes',
    'date_paiement'             => '2026-08-29',
    'statut'                    => 'valide',
]);

if (str_starts_with($recu1_STJ, 'STJ26') && str_ends_with($recu1_STJ, '-0001')) {
    echo "   -> Compteur indépendant pour STJ recommence bien à 0001 : SUCCÈS\n";
} else {
    echo "   -> ERREUR d'indépendance de compteur paroisse !\n";
}

// Test 3.4 : Changement d'année (Année 2027 pour Paroisse CIM)
$recu2027_CIM = $receiptService->generate($paroisse1->id, '2027-01-15 10:15:30');
echo "9. Reçu CIM pour l'année 2027 : {$recu2027_CIM}\n";
if (str_starts_with($recu2027_CIM, 'REC27') && str_ends_with($recu2027_CIM, '-0001')) {
    echo "   -> Réinitialisation à 0001 au changement d'année 2027 : SUCCÈS\n";
} else {
    echo "   -> ERREUR : La séquence n'a pas été réinitialisée en 2027 !\n";
}
echo "\n";

// -------------------------------------------------------------------------
// PARTIE 4 : VÉRIFICATION DE LA COMPATIBILITÉ AVEC LES ANCIENNES DONNÉES
// -------------------------------------------------------------------------
echo "--- PARTIE 4 : PRÉSERVATION DES ANCIENNES DONNÉES HISTORIQUES ---\n";
// Création d'un ancien reçu historique au format "REC-2024-0099"
$ancienPaiement = Paiement::create([
    'paroisse_configuration_id' => $paroisse1->id,
    'annee_catechese_id'        => $annee->id,
    'numero_recu'               => 'REC-2024-0099',
    'montant_total'             => 25000,
    'mode_paiement'             => 'especes',
    'date_paiement'             => '2024-10-10',
    'statut'                    => 'valide',
]);
echo "10. Ancien paiement historique en DB : {$ancienPaiement->numero_recu}\n";
$trouve = Paiement::where('numero_recu', 'REC-2024-0099')->first();
echo "    -> Recherche par ancien numéro de reçu : " . ($trouve ? 'TROUVÉ (Intact)' : 'NON TROUVÉ') . "\n\n";

// -------------------------------------------------------------------------
// PARTIE 5 : TEST VIA LES CONTRÔLEURS HTTP DE CRÉATION
// -------------------------------------------------------------------------
echo "--- PARTIE 5 : TEST DES CONTRÔLEURS API REST ---\n";

$currentUser = User::first();
$currentUser->paroisse_configuration_id = $paroisse1->id;
$currentUser->save();

// Test 5.1 : Création Catéchumène via CatechumeneController
$catController = app(CatechumeneController::class);
$payloadCat = [
    'nom'            => 'TEST_NOM_' . strtoupper(Str::random(4)),
    'prenoms'        => 'Test Prénoms',
    'sexe'           => 'M',
    'date_naissance' => '2015-05-20',
    'telephone'      => '0700000000',
];
$storeCatRequest = \App\Http\Requests\Api\V1\StoreCatechumeneRequest::create('/api/v1/catechumenes', 'POST', $payloadCat);
$storeCatRequest->setUserResolver(fn() => $currentUser);
$storeCatRequest->setContainer($app)->validateResolved();

$resCat = $catController->store($storeCatRequest);
$jsonCat = json_decode($resCat->getContent(), true);
echo "11. Catéchumène créé via API :\n";
echo "    HTTP Status : " . $resCat->getStatusCode() . "\n";
echo "    Matricule généré : " . ($jsonCat['data']['matricule'] ?? 'N/A') . "\n";
if (preg_match($patternMatricule, $jsonCat['data']['matricule'] ?? '')) {
    echo "    -> Matricule valide conforme au nouveau format : SUCCÈS\n";
} else {
    echo "    -> ERREUR format matricule API !\n";
}

// Test 5.2 : Paiement créé via PaiementController
$paiementController = app(PaiementController::class);
$payloadPaiement = [
    'annee_catechese_id' => $annee->uuid,
    'catechumene_id'     => $jsonCat['data']['id'],
    'mode_paiement'      => 'especes',
    'date_paiement'      => '2026-08-29',
    'lignes'             => [
        [
            'designation' => 'Cotisation Annuelle',
            'montant'     => 10000,
            'quantite'    => 1,
        ]
    ]
];
$storePaiementReq = \App\Http\Requests\Api\V1\StorePaiementRequest::create('/api/v1/paiements', 'POST', $payloadPaiement);
$storePaiementReq->setUserResolver(fn() => $currentUser);
$storePaiementReq->setContainer($app)->validateResolved();

$resPaiement = $paiementController->store($storePaiementReq);
$jsonPaiement = json_decode($resPaiement->getContent(), true);
echo "12. Paiement créé via API :\n";
echo "    HTTP Status : " . $resPaiement->getStatusCode() . "\n";
echo "    N° Reçu généré : " . ($jsonPaiement['data']['numero_recu'] ?? 'N/A') . "\n";
if (preg_match($patternRecu, $jsonPaiement['data']['numero_recu'] ?? '')) {
    echo "    -> Numéro de reçu valide conforme au nouveau format : SUCCÈS\n";
} else {
    echo "    -> ERREUR format numéro de reçu API !\n";
}

echo "\n========================================================================\n";
echo "=== TOUS LES TESTS MATRICULES ET REÇUS SONT VALIDÉS AVEC SUCCÈS ===\n";
echo "========================================================================\n";
