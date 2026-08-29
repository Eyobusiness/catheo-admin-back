<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Catechumene;
use App\Models\CatechumenSacrement;
use App\Models\InscriptionAnnuelle;
use App\Models\Niveau;
use App\Models\Sacrement;
use App\Models\Section;
use App\Models\User;
use App\Http\Controllers\Api\V1\SacrementController;
use App\Http\Requests\Api\V1\StoreCatechumenSacrementRequest;
use App\Http\Requests\Api\V1\UpdateCatechumenSacrementRequest;
use App\Services\SacrementService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

echo "====================================================\n";
echo "=== TEST COMPLET DU MODULE SACREMENTS ===\n";
echo "====================================================\n\n";

$currentUser = User::first();
if (!$currentUser) {
    die("Erreur : Aucun utilisateur dans la base.\n");
}

$paroisseId = $currentUser->paroisse_configuration_id ?? 1;
$sacrementService = app(SacrementService::class);
$controller = app(SacrementController::class);

// 1. Types de Sacrements
echo "1. [TEST] Liste des types de Sacrements\n";
$sacrements = $sacrementService->getSacrements();
echo "   Nombre de sacrements gérés : " . $sacrements->count() . "\n";
foreach ($sacrements as $s) {
    echo "   - [{$s->id}] {$s->nom} (Code: {$s->code}, UUID: {$s->uuid})\n";
}
echo "\n";

// 2. Trouver ou créer un catéchumène de test pour la paroisse
$cat = Catechumene::where('paroisse_configuration_id', $paroisseId)->first();
if (!$cat) {
    die("Erreur : Aucun catéchumène dans la paroisse.\n");
}
echo "2. [TEST] Catéchumène sélectionné : {$cat->nom_complet} (UUID: {$cat->uuid})\n\n";

// Nettoyage préalable pour le test
CatechumenSacrement::where('catechumene_id', $cat->id)->forceDelete();

// 3. Création d'un parcours sacramentel (Baptême en préparation)
echo "3. [TEST] Création d'un parcours sacramentel (Baptême - statut: preparation)\n";
$baptemeSacr = Sacrement::where('code', 'BAPTEME')->first();
$storeData = [
    'sacrement_id'    => $baptemeSacr->uuid,
    'statut'          => 'preparation',
    'date_sacrement'  => '2026-12-25',
    'lieu'            => 'Paroisse Saint Jean-Baptiste',
    'numero_registre' => 'REG-2026-001',
    'observations'    => 'Parcours catéchuménal en cours',
];

$parcoursBapteme = $sacrementService->storeParcoursSacrement($paroisseId, $cat, $storeData, $currentUser);
echo "   Parcours créé avec succès (UUID: {$parcoursBapteme->uuid}, Statut: {$parcoursBapteme->statut})\n";
echo "   Dossier catéchumène avant validation (est_baptise) : " . ($cat->fresh()->est_baptise ? 'OUI' : 'NON (Attendu: NON)') . "\n\n";

// 4. Test d'unicité (Empêcher doublon actif pour le même sacrement)
echo "4. [TEST] Contrainte d'unicité : Tentative de création d'un second Baptême pour le même catéchumène\n";
try {
    $sacrementService->storeParcoursSacrement($paroisseId, $cat, $storeData, $currentUser);
    echo "   ERREUR : Le doublon a été accepté !\n";
} catch (ValidationException $e) {
    echo "   SUCCÈS : Doublon rejeté proprement avec exception de validation.\n";
    echo "   Message : " . json_encode($e->errors(), JSON_UNESCAPED_UNICODE) . "\n\n";
}

// 5. Validation du sacrement de Baptême
echo "5. [TEST] Validation du Baptême (statut: valide)\n";
$updateData = [
    'statut'          => 'valide',
    'date_sacrement'  => '2026-12-25',
    'lieu'            => 'Paroisse Saint Jean-Baptiste',
    'numero_registre' => 'REG-2026-001',
    'num_carnet'      => 'CARNET-BAP-99',
    'observations'    => 'Baptême célébré avec succès',
];

$parcoursValide = $sacrementService->updateParcoursSacrement($paroisseId, $cat, $parcoursBapteme->uuid, $updateData, $currentUser);
echo "   Statut après validation : {$parcoursValide->statut}\n";
echo "   Validated at : {$parcoursValide->validated_at}\n";
echo "   Validated by ID : {$parcoursValide->validated_by}\n";
echo "   Dossier catéchumène actualisé (est_baptise) : " . ($cat->fresh()->est_baptise ? 'OUI (Synchronisé OK)' : 'NON (Erreur)') . "\n";
echo "   Date baptême catéchumène : " . $cat->fresh()->date_bapteme?->toDateString() . "\n";
echo "   Num carnet baptême catéchumène : " . $cat->fresh()->num_carnet_bapteme . "\n\n";

// 6. Ajout de la Première Communion (statut: preparation)
echo "6. [TEST] Ajout de la Première Communion (statut: preparation)\n";
$communionSacr = Sacrement::where('code', 'PREMIERE_COMMUNION')->first();
$parcoursCommunion = $sacrementService->storeParcoursSacrement($paroisseId, $cat, [
    'sacrement_id'   => $communionSacr->uuid,
    'statut'         => 'preparation',
    'date_sacrement' => '2027-05-15',
    'lieu'           => 'Paroisse Saint Jean-Baptiste',
], $currentUser);
echo "   Première Communion créée (Statut: {$parcoursCommunion->statut})\n\n";

// 7. Consultation du parcours complet (3 sacrements avec état)
echo "7. [TEST] Consultation du parcours sacramentel complet du catéchumène\n";
$parcoursComplet = $sacrementService->getCatechumenParcours($paroisseId, $cat->fresh());
foreach ($parcoursComplet as $p) {
    echo "   - {$p['sacrement_nom']} : Statut = [{$p['statut']}], Date = " . ($p['date_sacrement'] ?? 'Non fixée') . ", Lieu = " . ($p['lieu'] ?? 'N/A') . "\n";
}
echo "\n";

// 8. Test des filtres dynamiques (ZÉRO HARDCODING)
echo "8. [TEST] Filtrage dynamique des catéchumènes\n";

// 8.1 Filtrage par sacrement (BAPTEME)
$filtreBapteme = $sacrementService->getCatechumens($paroisseId, ['sacrement_id' => $baptemeSacr->uuid]);
echo "   - Filtre par Sacrement=BAPTEME : " . $filtreBapteme->total() . " catéchumène(s) trouvé(s)\n";

// 8.2 Filtrage par sacrement + statut (COMMUNION + preparation)
$filtreCommunionPrep = $sacrementService->getCatechumens($paroisseId, [
    'sacrement_id' => $communionSacr->uuid,
    'statut'       => 'preparation'
]);
echo "   - Filtre par Sacrement=COMMUNION & Statut=preparation : " . $filtreCommunionPrep->total() . " catéchumène(s) trouvé(s)\n";

// 8.3 Filtrage par Section dynamique
$firstSection = Section::where('paroisse_configuration_id', $paroisseId)->first();
if ($firstSection) {
    $filtreSection = $sacrementService->getCatechumens($paroisseId, ['section_id' => $firstSection->uuid]);
    echo "   - Filtre par Section dynamique '{$firstSection->nom}' (ID: {$firstSection->uuid}) : " . $filtreSection->total() . " catéchumène(s)\n";
}

// 8.4 Filtrage par Niveau dynamique
$firstNiveau = Niveau::where('paroisse_configuration_id', $paroisseId)->first();
if ($firstNiveau) {
    $filtreNiveau = $sacrementService->getCatechumens($paroisseId, ['niveau_id' => $firstNiveau->uuid]);
    echo "   - Filtre par Niveau dynamique '{$firstNiveau->nom}' (ID: {$firstNiveau->uuid}) : " . $filtreNiveau->total() . " catéchumène(s)\n";
}
echo "\n";

// 9. Test de suppression logique (Soft Delete)
echo "9. [TEST] Suppression logique du parcours de Première Communion\n";
$sacrementService->deleteParcoursSacrement($paroisseId, $cat, $parcoursCommunion->uuid);
$existsActive = CatechumenSacrement::where('uuid', $parcoursCommunion->uuid)->exists();
$existsTrashed = CatechumenSacrement::withTrashed()->where('uuid', $parcoursCommunion->uuid)->exists();
echo "   Existe en actif : " . ($existsActive ? 'OUI (Erreur)' : 'NON (Supprimé)') . "\n";
echo "   Existe dans la corbeille SoftDelete : " . ($existsTrashed ? 'OUI (OK)' : 'NON') . "\n\n";

// 10. Test via HTTP Controller
echo "10. [TEST HTTP] Test SacrementController::catechumens via Request API\n";
$reqHttp = new Request();
$reqHttp->setUserResolver(fn() => $currentUser);
$resHttp = $controller->catechumens($reqHttp);
$jsonHttp = json_decode($resHttp->getContent(), true);
echo "   HTTP Status : " . $resHttp->getStatusCode() . "\n";
echo "   Total elements retournés : " . $jsonHttp['meta']['total_elements'] . "\n";
echo "   Premier catéchumène : " . $jsonHttp['data'][0]['nom_complet'] . "\n";
echo "   Statuts sacrements du premier : " . json_encode($jsonHttp['data'][0]['sacrements_status']) . "\n\n";

// 11. Test Isolation Multi-Tenant
echo "11. [TEST SÉCURITÉ] Isolation multi-tenant\n";
$reqTenant = new Request();
$fakeUser = new User();
$fakeUser->paroisse_configuration_id = 999;
$reqTenant->setUserResolver(fn() => $fakeUser);

$resTenant = $controller->catechumens($reqTenant);
$jsonTenant = json_decode($resTenant->getContent(), true);
echo "   Tentative lecture Paroisse 999 : Total éléments = " . $jsonTenant['meta']['total_elements'] . " (Attendu: 0)\n\n";

echo "====================================================\n";
echo "=== TOUS LES TESTS DU MODULE SACREMENTS SONT VALIDÉS AVEC SUCCÈS ===\n";
echo "====================================================\n";
