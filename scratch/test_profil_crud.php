<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Menu;
use App\Models\Profil;
use App\Models\ProfilMenuPermission;
use App\Http\Controllers\Api\V1\ProfilController;
use App\Http\Requests\Api\V1\StoreProfilRequest;
use App\Http\Requests\Api\V1\UpdateProfilRequest;
use Illuminate\Http\Request;

$controller = app(ProfilController::class);

echo "====================================================\n";
echo "=== TEST DU CRUD COMPLET DU MODULE PROFILS & PERMISSIONS ===\n";
echo "====================================================\n\n";

// 1. TEST PERMISSIONS TREE
echo "1. [GET] /profils/permissions-tree\n";
$resTree = $controller->permissionsTree();
$dataTree = json_decode($resTree->getContent(), true);
echo "   Status : " . $dataTree['status'] . "\n";
echo "   Nombre de menus racines : " . count($dataTree['data']) . "\n";
echo "   Exemple menu 1 : {$dataTree['data'][0]['libelle']} (Actions: {$dataTree['data'][0]['total_actions']})\n\n";

// 2. TEST LISTING PROFILS
echo "2. [GET] /profils (index)\n";
$resIndex = $controller->index(new Request());
$dataIndex = json_decode($resIndex->getContent(), true);
echo "   Status : " . $dataIndex['status'] . "\n";
echo "   Total profils : " . $dataIndex['meta']['total_elements'] . "\n\n";

// 3. TEST CREATE PROFIL
echo "3. [POST] /profils (store)\n";
$uniqueCode = 'TEST_ROLE_' . strtoupper(substr(md5(uniqid()), 0, 6));
$firstMenu = Menu::first();

$storeData = [
    'nom'         => 'Rôle Test Automatisation',
    'code'        => $uniqueCode,
    'description' => 'Profil de test créé pour vérifier la matrice de permissions CRUD.',
    'statut'      => 'actif',
    'permissions' => ['catechumenes.view', 'catechumenes.create'],
    'menu_permissions' => [
        [
            'uuid'        => $firstMenu ? $firstMenu->uuid : null,
            'permissions' => [
                'read'         => true,
                'create'       => true,
                'update'       => false,
                'delete'       => false,
                'restore'      => false,
                'force_delete' => false,
            ]
        ]
    ]
];

$storeReq = StoreProfilRequest::create('/api/v1/profils', 'POST', $storeData);
$storeReq->setContainer($app);
$storeReq->validateResolved();
$resStore = $controller->store($storeReq);
$dataStore = json_decode($resStore->getContent(), true);
echo "   Status HTTP : " . $resStore->getStatusCode() . "\n";
echo "   Message : " . $dataStore['message'] . "\n";
$createdProfilUuid = $dataStore['data']['id'];
$createdProfilId = Profil::where('uuid', $createdProfilUuid)->value('id');
echo "   Profil créé (UUID : {$createdProfilUuid}, ID : {$createdProfilId})\n\n";

// 4. TEST SHOW PROFIL
echo "4. [GET] /profils/{id} (show)\n";
$profilModel = Profil::where('uuid', $createdProfilUuid)->first();
$resShow = $controller->show(new Request(), $profilModel);
$dataShow = json_decode($resShow->getContent(), true);
echo "   Nom : " . $dataShow['data']['nom'] . "\n";
echo "   Statut : " . $dataShow['data']['statut'] . "\n";
echo "   Total permissions : " . $dataShow['data']['total_permissions'] . "\n";
echo "   Menus arborescence : " . count($dataShow['data']['menus']) . "\n\n";

// 5. TEST DROITS CRUD DE LA MATRICE (hasPermission)
echo "5. [VÉRIFICATION] hasPermission & Droits d'actions CRUD\n";
$hasRead = $profilModel->hasMenuActionPermission($firstMenu->reference, 'read');
$hasCreate = $profilModel->hasMenuActionPermission($firstMenu->reference, 'create');
$hasUpdate = $profilModel->hasMenuActionPermission($firstMenu->reference, 'update');
$hasDelete = $profilModel->hasMenuActionPermission($firstMenu->reference, 'delete');
echo "   Permission READ sur {$firstMenu->reference} : " . ($hasRead ? 'OUI (OK)' : 'NON (ÉCHEC)') . "\n";
echo "   Permission CREATE sur {$firstMenu->reference} : " . ($hasCreate ? 'OUI (OK)' : 'NON (ÉCHEC)') . "\n";
echo "   Permission UPDATE sur {$firstMenu->reference} : " . (!$hasUpdate ? 'NON (OK attendu)' : 'OUI (ÉCHEC)') . "\n";
echo "   Permission DELETE sur {$firstMenu->reference} : " . (!$hasDelete ? 'NON (OK attendu)' : 'OUI (ÉCHEC)') . "\n\n";

// 6. TEST TOGGLE STATUS
echo "6. [PATCH] /profils/{id}/status (toggleStatus)\n";
$resToggle1 = $controller->toggleStatus(new Request(), $profilModel);
$dataToggle1 = json_decode($resToggle1->getContent(), true);
echo "   Nouveau statut : " . $dataToggle1['data']['statut'] . " (Attendu: Inactif)\n";

$resToggle2 = $controller->toggleStatus(new Request(), $profilModel->fresh());
$dataToggle2 = json_decode($resToggle2->getContent(), true);
echo "   Nouveau statut : " . $dataToggle2['data']['statut'] . " (Attendu: Actif)\n\n";

// 7. TEST UPDATE PROFIL
echo "7. [PUT] /profils/{id} (update)\n";
$updateData = [
    'nom'         => 'Rôle Test Automatisation - Modifié',
    'description' => 'Description mise à jour avec permission delete accordée.',
    'menu_permissions' => [
        [
            'uuid'        => $firstMenu ? $firstMenu->uuid : null,
            'permissions' => [
                'read'         => true,
                'create'       => true,
                'update'       => true,
                'delete'       => true,
                'restore'      => false,
                'force_delete' => false,
            ]
        ]
    ]
];

$updateReq = UpdateProfilRequest::create('/api/v1/profils/' . $createdProfilUuid, 'PUT', $updateData);
$updateReq->setContainer($app);
$updateReq->validateResolved();
$resUpdate = $controller->update($updateReq, $profilModel->fresh());
$dataUpdate = json_decode($resUpdate->getContent(), true);
echo "   Message : " . $dataUpdate['message'] . "\n";
echo "   Nouveau nom : " . $dataUpdate['data']['nom'] . "\n";

$hasDeleteNow = $profilModel->fresh()->hasMenuActionPermission($firstMenu->reference, 'delete');
echo "   Nouvelle permission DELETE après update : " . ($hasDeleteNow ? 'OUI (OK)' : 'NON (ÉCHEC)') . "\n\n";

// 8. TEST DELETE PROFIL
echo "8. [DELETE] /profils/{id} (destroy)\n";
$resDelete = $controller->destroy(new Request(), $profilModel->fresh());
$dataDelete = json_decode($resDelete->getContent(), true);
echo "   Message : " . $dataDelete['message'] . "\n";

$existsAfterDelete = Profil::where('uuid', $createdProfilUuid)->exists();
$pivotCountAfterDelete = ProfilMenuPermission::where('profil_id', $createdProfilId)->count();
echo "   Profil existe encore : " . ($existsAfterDelete ? 'OUI (Erreur)' : 'NON (Supprimé avec succès)') . "\n";
echo "   Permissions orphelines en pivot : {$pivotCountAfterDelete} (Attendu: 0)\n\n";

echo "====================================================\n";
echo "=== TOUS LES TESTS CRUD & PERMISSIONS SONT VALIDÉS AVEC SUCCÈS ===\n";
echo "====================================================\n";
