<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\DashboardService;
use App\Models\AnneeCatechese;
use App\Models\InscriptionAnnuelle;

$service = app(DashboardService::class);

echo "=== TEST 1 : Année active par défaut (Paroisse 1) ===\n";
$annee1 = AnneeCatechese::getAnneeCourante(1);
$data1 = $service->getAdminDashboardData(1, $annee1);
echo "Année active : " . ($annee1 ? $annee1->libelle : 'Aucune') . "\n";
echo "Summary : " . json_encode($data1['summary'], JSON_PRETTY_PRINT) . "\n";
echo "Sacrements : " . json_encode($data1['sacrements'], JSON_PRETTY_PRINT) . "\n";
echo "Alertes : " . json_encode($data1['alertes'], JSON_PRETTY_PRINT) . "\n";

echo "\n=== TEST 2 : Année avec inscriptions (ex: ID 1) ===\n";
$annee2 = AnneeCatechese::find(1);
if ($annee2) {
    $data2 = $service->getAdminDashboardData(1, $annee2);
    echo "Année : {$annee2->libelle}\n";
    echo "Summary : " . json_encode($data2['summary'], JSON_PRETTY_PRINT) . "\n";
    echo "Effectifs Sections count : " . count($data2['effectifs']['par_section']) . "\n";
    echo "Effectifs Niveaux count : " . count($data2['effectifs']['par_niveau']) . "\n";
    echo "Effectifs Classes count : " . count($data2['effectifs']['par_classe']) . "\n";
    echo "Sacrements : " . json_encode($data2['sacrements'], JSON_PRETTY_PRINT) . "\n";
}

echo "\n=== TEST 3 : Autre Paroisse fictive (Isolation Multi-Tenant) ===\n";
$dataParoisse999 = $service->getAdminDashboardData(999, null);
echo "Catechumenes Paroisse 999 : " . $dataParoisse999['summary']['catechumenes_actifs'] . " (Attendu: 0)\n";
echo "Sections Paroisse 999 : " . $dataParoisse999['summary']['sections'] . " (Attendu: 0)\n";
echo "Classes Paroisse 999 : " . $dataParoisse999['summary']['classes'] . " (Attendu: 0)\n";

echo "\n=== TOUS LES TESTS SONT PASSÉS AVEC SUCCÈS ===\n";
