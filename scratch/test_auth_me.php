<?php

require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\Animateur;
use App\Models\Catechumene;
use App\Models\AnneeCatechese;
use App\Http\Resources\Api\V1\UserResource;
use App\Http\Resources\Api\V1\AnimateurResource;
use App\Http\Resources\Api\V1\CatechumeneResource;
use App\Http\Resources\Api\V1\AnneeCatecheseResource;

echo "=== 1. TEST AUTH/ME POUR USER (ADMIN) ===\n";
$user = User::with(['paroisse', 'profil'])->first();
if ($user) {
    $annee = AnneeCatechese::getAnneeCourante($user->paroisse_configuration_id);
    $output = [
        'status' => 'success',
        'data'   => [
            'user_type'      => 'admin',
            'user'           => (new UserResource($user))->toArray(request()),
            'annee_courante' => $annee ? (new AnneeCatecheseResource($annee))->toArray(request()) : null,
            'menus_count'    => count($user->getAccessibleMenus()),
        ]
    ];
    echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
} else {
    echo "Aucun User admin trouvé.\n";
}

echo "\n=== 2. TEST AUTH/ME POUR ANIMATEUR ===\n";
$anim = Animateur::with(['paroisse', 'affectations'])->first();
if ($anim) {
    $annee = AnneeCatechese::getAnneeCourante($anim->paroisse_configuration_id);
    $output = [
        'status' => 'success',
        'data'   => [
            'user_type'      => 'animateur',
            'user'           => (new AnimateurResource($anim))->toArray(request()),
            'annee_courante' => $annee ? (new AnneeCatecheseResource($annee))->toArray(request()) : null,
            'menus_count'    => count($anim->getAccessibleMenus()),
        ]
    ];
    echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}

echo "\n=== 3. TEST AUTH/ME POUR PARENT / CATECHUMENE ===\n";
$cat = Catechumene::with(['paroisse', 'ceb', 'inscriptionsAnnuelles'])->first();
if ($cat) {
    $annee = AnneeCatechese::getAnneeCourante($cat->paroisse_configuration_id);
    $output = [
        'status' => 'success',
        'data'   => [
            'user_type'      => 'parent',
            'user'           => (new CatechumeneResource($cat))->toArray(request()),
            'annee_courante' => $annee ? (new AnneeCatecheseResource($annee))->toArray(request()) : null,
            'menus_count'    => count($cat->getAccessibleMenus()),
        ]
    ];
    echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
}
