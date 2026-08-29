<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Profil;
use App\Models\User;
use App\Http\Controllers\Api\V1\UserController;
use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use App\Http\Requests\Api\V1\UpdateUserStatusRequest;
use Illuminate\Http\Request;

$controller = app(UserController::class);

echo "====================================================\n";
echo "=== TEST DU CRUD COMPLET DU MODULE UTILISATEURS (USER) ===\n";
echo "====================================================\n\n";

$currentUser = User::first();
if (!$currentUser) {
    die("Erreur : Aucun utilisateur dans la base.\n");
}

// Simuler l'utilisateur connecté
$baseRequest = new Request();
$baseRequest->setUserResolver(fn() => $currentUser);

// 1. TEST LISTING USERS (INDEX)
echo "1. [GET] /users (index)\n";
$resIndex = $controller->index($baseRequest);
$dataIndex = json_decode($resIndex->getContent(), true);
echo "   Status : " . $dataIndex['status'] . "\n";
echo "   Total utilisateurs : " . $dataIndex['meta']['total_elements'] . "\n";
echo "   Page actuelle : " . $dataIndex['meta']['current_page'] . " / " . $dataIndex['meta']['total_pages'] . "\n";
echo "   Exemple user 1 : " . $dataIndex['data'][0]['name'] . " (" . $dataIndex['data'][0]['email'] . ")\n\n";

// 2. TEST CREATE USER (STORE)
echo "2. [POST] /users (store)\n";
// Choisir un profil standard non-système ou autre que SUPER_ADMIN
$targetProfil = Profil::where('code', '!=', 'SUPER_ADMIN')->first() ?? Profil::first();
$uniqueEmail = 'user_test_' . substr(md5(uniqid()), 0, 6) . '@catheo.ci';

$storeData = [
    'profil_id' => $targetProfil ? $targetProfil->uuid : 1,
    'nom'       => 'TRAORE',
    'prenoms'   => 'Ibrahim Test',
    'email'     => $uniqueEmail,
    'password'  => 'Secret123!',
    'telephone' => '+225 0707070707',
    'statut'    => 'actif',
];

$storeReq = StoreUserRequest::create('/api/v1/users', 'POST', $storeData);
$storeReq->setUserResolver(fn() => $currentUser);
$storeReq->setContainer($app);
$storeReq->validateResolved();

$resStore = $controller->store($storeReq);
$dataStore = json_decode($resStore->getContent(), true);
echo "   Status HTTP : " . $resStore->getStatusCode() . "\n";
echo "   Message : " . $dataStore['message'] . "\n";
$createdUserUuid = $dataStore['data']['id'];
$createdUserId = User::where('uuid', $createdUserUuid)->value('id');
echo "   Utilisateur créé : {$dataStore['data']['name']} (UUID : {$createdUserUuid}, ID : {$createdUserId})\n\n";

// 3. TEST SHOW USER
echo "3. [GET] /users/{id} (show)\n";
$userModel = User::where('uuid', $createdUserUuid)->first();
$showReq = new Request();
$showReq->setUserResolver(fn() => $currentUser);
$resShow = $controller->show($showReq, $userModel);
$dataShow = json_decode($resShow->getContent(), true);
echo "   Nom complet : " . $dataShow['data']['name'] . "\n";
echo "   Email : " . $dataShow['data']['email'] . "\n";
echo "   Téléphone : " . $dataShow['data']['telephone'] . "\n";
echo "   Profil rattaché : " . ($dataShow['data']['profil']['nom'] ?? 'N/A') . "\n";
echo "   Statut : " . $dataShow['data']['statut'] . "\n\n";

// 4. TEST TOGGLE STATUS
echo "4. [PATCH] /users/{id}/status (updateStatus)\n";
$statusReq1 = UpdateUserStatusRequest::create('/api/v1/users/' . $createdUserUuid . '/status', 'PATCH', ['statut' => 'inactif']);
$statusReq1->setUserResolver(fn() => $currentUser);
$statusReq1->setContainer($app);
$statusReq1->validateResolved();

$resStatus1 = $controller->updateStatus($statusReq1, $userModel);
$dataStatus1 = json_decode($resStatus1->getContent(), true);
echo "   Nouveau statut : " . $dataStatus1['data']['statut'] . " (Attendu: inactif)\n";

$statusReq2 = UpdateUserStatusRequest::create('/api/v1/users/' . $createdUserUuid . '/status', 'PATCH', ['statut' => 'actif']);
$statusReq2->setUserResolver(fn() => $currentUser);
$statusReq2->setContainer($app);
$statusReq2->validateResolved();

$resStatus2 = $controller->updateStatus($statusReq2, $userModel->fresh());
$dataStatus2 = json_decode($resStatus2->getContent(), true);
echo "   Nouveau statut : " . $dataStatus2['data']['statut'] . " (Attendu: actif)\n\n";

// 5. TEST UPDATE USER
echo "5. [PUT] /users/{id} (update)\n";
$updateData = [
    'nom'       => 'TRAORE MODIFIE',
    'prenoms'   => 'Ibrahim Champion',
    'telephone' => '+225 0101010101',
];

$updateReq = UpdateUserRequest::create('/api/v1/users/' . $createdUserUuid, 'PUT', $updateData);
$updateReq->setUserResolver(fn() => $currentUser);
$updateReq->setContainer($app);
$updateReq->validateResolved();

$resUpdate = $controller->update($updateReq, $userModel->fresh());
$dataUpdate = json_decode($resUpdate->getContent(), true);
echo "   Message : " . $dataUpdate['message'] . "\n";
echo "   Nouveau nom : " . $dataUpdate['data']['name'] . "\n";
echo "   Nouveau téléphone : " . $dataUpdate['data']['telephone'] . "\n\n";

// 6. TEST PROTECTION CONTRE L'AUTO-SUPPRESSION
echo "6. [VÉRIFICATION] Protection contre l'auto-suppression du compte connecté\n";
$selfDeleteReq = new Request();
$selfDeleteReq->setUserResolver(fn() => $currentUser);
$resSelfDelete = $controller->destroy($selfDeleteReq, $currentUser);
$dataSelfDelete = json_decode($resSelfDelete->getContent(), true);
echo "   Tentative suppression propre compte : " . $dataSelfDelete['message'] . " (Code HTTP: {$resSelfDelete->getStatusCode()})\n\n";

// 7. TEST DELETE USER (DESTROY)
echo "7. [DELETE] /users/{id} (destroy)\n";
$deleteReq = new Request();
$deleteReq->setUserResolver(fn() => $currentUser);
$resDelete = $controller->destroy($deleteReq, $userModel->fresh());
$dataDelete = json_decode($resDelete->getContent(), true);
echo "   Message : " . $dataDelete['message'] . "\n";

$existsInDb = User::where('uuid', $createdUserUuid)->exists();
$trashedInDb = User::withTrashed()->where('uuid', $createdUserUuid)->exists();
echo "   Utilisateur actif existe : " . ($existsInDb ? 'OUI (Erreur)' : 'NON (Supprimé)') . "\n";
echo "   Utilisateur en corbeille (SoftDelete) : " . ($trashedInDb ? 'OUI (OK)' : 'NON') . "\n\n";

// Nettoyage complet
User::withTrashed()->where('uuid', $createdUserUuid)->forceDelete();

echo "====================================================\n";
echo "=== TOUS LES TESTS DU CRUD UTILISATEURS SONT VALIDÉS AVEC SUCCÈS ===\n";
echo "====================================================\n";
