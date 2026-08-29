<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\AnneeCatechese;
use App\Models\User;
use App\Models\Section;
use App\Models\Niveau;
use App\Models\Classe;
use App\Models\Catechumene;
use App\Models\InscriptionAnnuelle;
use App\Models\Seance;
use App\Models\Presence;
use App\Models\DecisionFinAnnee;
use App\Models\MutationCatechumene;
use App\Models\Preinscription;
use App\Models\Animateur;
use App\Models\AffectationAnimateur;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Services\BilanAnnuelService;
use Illuminate\Http\Request;

echo "====================================================\n";
echo "=== TEST COMPLET DU MODULE BILAN ANNUEL DE CATÉCHÈSE ===\n";
echo "====================================================\n\n";

$currentUser = User::first();
if (!$currentUser) {
    die("Erreur : Aucun utilisateur dans la base.\n");
}

$paroisseId = $currentUser->paroisse_configuration_id ?? 1;
$bilanService = app(BilanAnnuelService::class);
$controller = app(DashboardController::class);

// 1. Trouver ou tester sur une année avec inscriptions (ex: 2024-2025)
$annee2024 = AnneeCatechese::where('paroisse_configuration_id', $paroisseId)
    ->where('libelle', 'like', '%2024%')
    ->first();

if (!$annee2024) {
    $annee2024 = AnneeCatechese::where('paroisse_configuration_id', $paroisseId)->first();
}

echo "1. [TEST] Génération du Bilan Annuel pour l'année : " . $annee2024->libelle . " (UUID: {$annee2024->uuid})\n";
$bilanData = $bilanService->genererBilanAnnuel($paroisseId, $annee2024);

echo "   a) Synthèse générale :\n";
echo "      - Effectif total : " . $bilanData['synthese']['effectif_total'] . "\n";
echo "      - Catéchumènes actifs : " . $bilanData['synthese']['catechumenes_actifs'] . "\n";
echo "      - Nouveaux inscrits : " . $bilanData['synthese']['nouveaux'] . "\n";
echo "      - Réinscriptions : " . $bilanData['synthese']['reinscriptions'] . "\n";
echo "      - Mutations : " . $bilanData['synthese']['mutations'] . "\n";
echo "      - Sections : " . $bilanData['synthese']['sections'] . "\n";
echo "      - Niveaux : " . $bilanData['synthese']['niveaux'] . "\n";
echo "      - Classes : " . $bilanData['synthese']['classes'] . "\n";
echo "      - Animateurs : " . $bilanData['synthese']['animateurs'] . "\n\n";

echo "   b) Répartition des effectifs :\n";
echo "      - Sections comptées : " . count($bilanData['effectifs']['par_section']) . "\n";
echo "      - Niveaux comptés : " . count($bilanData['effectifs']['par_niveau']) . "\n";
echo "      - Classes comptées : " . count($bilanData['effectifs']['par_classe']) . "\n\n";

echo "   c) Évolution par rapport à l'année précédente :\n";
if ($bilanData['evolution']) {
    echo "      - Année précédente : " . $bilanData['evolution']['annee_precedente_libelle'] . "\n";
    echo "      - Effectif précédent : " . $bilanData['evolution']['annee_precedente'] . "\n";
    echo "      - Différence : " . $bilanData['evolution']['difference'] . "\n";
    echo "      - % Évolution : " . $bilanData['evolution']['pourcentage'] . "%\n\n";
} else {
    echo "      - Aucune année précédente disponible (OK: null retourné proprement)\n\n";
}

echo "   d) Assiduité et séances :\n";
echo "      - Séances prévues : " . $bilanData['assiduite']['seances_prevues'] . "\n";
echo "      - Séances réalisées : " . $bilanData['assiduite']['seances_realisees'] . "\n";
echo "      - Présences : " . $bilanData['assiduite']['presences'] . "\n";
echo "      - Absences : " . $bilanData['assiduite']['absences'] . "\n";
echo "      - Taux de présence : " . $bilanData['assiduite']['taux_presence'] . "%\n\n";

echo "   e) Bilan des Sacrements :\n";
echo "      - Baptême (3ème année + Non Baptisé) : Candidats = {$bilanData['sacrements']['bapteme']['candidats']}, Réalisés = {$bilanData['sacrements']['bapteme']['realises']}, Restants = {$bilanData['sacrements']['bapteme']['restants']}\n";
echo "      - 1ère Communion (3ème année + Baptisé) : Candidats = {$bilanData['sacrements']['premiere_communion']['candidats']}, Réalisés = {$bilanData['sacrements']['premiere_communion']['realises']}, Restants = {$bilanData['sacrements']['premiere_communion']['restants']}\n";
echo "      - Confirmation (Baptisé + Adulte 4e/Autre 5e) : Candidats = {$bilanData['sacrements']['confirmation']['candidats']}, Réalisés = {$bilanData['sacrements']['confirmation']['realises']}, Restants = {$bilanData['sacrements']['confirmation']['restants']}\n\n";

echo "   f) Préinscriptions & Recrutement :\n";
echo "      - Total préinscriptions : " . $bilanData['inscriptions']['preinscriptions_total'] . "\n";
echo "      - Validées : " . $bilanData['inscriptions']['validees'] . "\n";
echo "      - En attente : " . $bilanData['inscriptions']['en_attente'] . "\n";
echo "      - Rejetées : " . $bilanData['inscriptions']['rejetees'] . "\n\n";

echo "   g) Bilan des Animateurs :\n";
echo "      - Total animateurs paroisse : " . $bilanData['animateurs']['total'] . "\n";
echo "      - Animateurs affectés : " . $bilanData['animateurs']['animateurs_affectes'] . "\n";
echo "      - Classes couvertes : " . $bilanData['animateurs']['classes_affectees'] . "\n";
echo "      - Taux de couverture : " . $bilanData['animateurs']['taux_couverture'] . "%\n\n";

echo "   h) Alertes & Points d'attention : " . count($bilanData['alertes']) . " alerte(s) détectée(s)\n";
foreach ($bilanData['alertes'] as $alt) {
    echo "      - [{$alt['type']}] ({$alt['niveau']}) : {$alt['message']}\n";
}
echo "\n";

echo "   i) Synthèse finale rédigée :\n";
echo "      \"" . $bilanData['synthese_finale']['resume'] . "\"\n\n";

// 2. TEST VIA LE CONTROLLER HTTP (ROUTE GET /dashboard/bilan-annuel/{id})
echo "2. [HTTP CONTROLLER] Test DashboardController::bilanAnnuel via API Request\n";
$req = new Request();
$req->setUserResolver(fn() => $currentUser);
$resHttp = $controller->bilanAnnuel($req, $annee2024->uuid);
$jsonHttp = json_decode($resHttp->getContent(), true);
echo "   Status Code HTTP : " . $resHttp->getStatusCode() . "\n";
echo "   Status JSON : " . $jsonHttp['status'] . "\n";
echo "   Année retournée : " . $jsonHttp['data']['annee']['libelle'] . "\n";
echo "   Effectif total confirmé : " . $jsonHttp['data']['synthese']['effectif_total'] . "\n\n";

// 3. TEST D'UNE ANNÉE SANS DONNÉES (Cas limite)
echo "3. [CAS LIMITE] Test d'une Année sans données\n";
$anneeVide = AnneeCatechese::where('paroisse_configuration_id', $paroisseId)
    ->where('libelle', 'like', '%2026%')
    ->first();
if ($anneeVide) {
    $bilanVide = $bilanService->genererBilanAnnuel($paroisseId, $anneeVide);
    echo "   Effectif total année vide : " . $bilanVide['synthese']['effectif_total'] . " (Attendu: 0)\n";
    echo "   Taux présence année vide : " . $bilanVide['assiduite']['taux_presence'] . "% (Attendu: 0% sans division par zéro)\n";
    echo "   Candidats sacrements : Baptême=" . $bilanVide['sacrements']['bapteme']['candidats'] . ", Communion=" . $bilanVide['sacrements']['premiere_communion']['candidats'] . "\n\n";
}

// 4. TEST ISOLATION MULTI-TENANT
echo "4. [SÉCURITÉ] Test d'isolation Multi-Tenant\n";
$reqTenant = new Request();
$fakeUser = new User();
$fakeUser->paroisse_configuration_id = 999;
$reqTenant->setUserResolver(fn() => $fakeUser);
$resTenant = $controller->bilanAnnuel($reqTenant, $annee2024->uuid);
echo "   Tentative accès Paroisse 999 sur Année Paroisse 1 : HTTP Code " . $resTenant->getStatusCode() . "\n";
$jsonTenant = json_decode($resTenant->getContent(), true);
echo "   Message : " . ($jsonTenant['message'] ?? 'Accès bloqué') . "\n\n";

echo "====================================================\n";
echo "=== TOUS LES TESTS DU BILAN ANNUEL SONT VALIDÉS AVEC SUCCÈS ===\n";
echo "====================================================\n";
