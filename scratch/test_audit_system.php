<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\ResponsableCatechese;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

echo "=== TEST DU SYSTEME D'AUDIT GLOBAL & RESPONSABLE CATECHESE ===\n";

// 1. Setup Mock Users
$userA = User::first();
if (!$userA) {
    echo "Creating test user...\n";
    $userA = User::create([
        'uuid' => (string) Str::uuid(),
        'nom' => 'Kouadio',
        'prenoms' => 'Ferdinand',
        'email' => 'admin_test@catheo.ci',
        'password' => bcrypt('password123'),
        'statut' => 'actif',
    ]);
}

$userB = new User(['uuid' => (string) Str::uuid(), 'nom' => 'UserB', 'prenoms' => 'Audit']);
$userC = new User(['uuid' => (string) Str::uuid(), 'nom' => 'UserC', 'prenoms' => 'Audit']);
$userD = new User(['uuid' => (string) Str::uuid(), 'nom' => 'UserD', 'prenoms' => 'Audit']);

// ----------------------------------------------------
// TEST 1 : CREATION PAR USER A
// ----------------------------------------------------
Auth::setUser($userA);

$responsable = ResponsableCatechese::create([
    'paroisse_configuration_id' => $userA->paroisse_configuration_id ?? 1,
    'nom_prenoms' => 'Abbé Marc KOUAME',
    'fonction' => 'Vicaire Paroissial',
    'telephone' => '+225 0102030405',
    'statut' => 'actif',
]);

echo "\n[1. Creation par User A]\n";
echo "  created_by : " . ($responsable->created_by ?? 'NULL') . " (Attendu: {$userA->uuid})\n";
echo "  updated_by : " . ($responsable->updated_by ?? 'NULL') . " (Attendu: NULL)\n";
echo "  deleted_by : " . ($responsable->deleted_by ?? 'NULL') . " (Attendu: NULL)\n";
echo "  deleted_at : " . ($responsable->deleted_at ?? 'NULL') . " (Attendu: NULL)\n";
assert($responsable->created_by === $userA->uuid, "Erreur created_by creation");

// ----------------------------------------------------
// TEST 2 : MODIFICATION PAR USER B
// ----------------------------------------------------
Auth::setUser($userB);

$responsable->update([
    'fonction' => 'Aumônier des Jeunes et Catéchèse',
]);
$responsable->refresh();

echo "\n[2. Modification par User B]\n";
echo "  created_by : " . ($responsable->created_by ?? 'NULL') . " (Attendu: {$userA->uuid})\n";
echo "  updated_by : " . ($responsable->updated_by ?? 'NULL') . " (Attendu: {$userB->uuid})\n";
echo "  deleted_by : " . ($responsable->deleted_by ?? 'NULL') . " (Attendu: NULL)\n";
echo "  deleted_at : " . ($responsable->deleted_at ?? 'NULL') . " (Attendu: NULL)\n";
assert($responsable->created_by === $userA->uuid, "Erreur created_by persistance");
assert($responsable->updated_by === $userB->uuid, "Erreur updated_by modification");

// ----------------------------------------------------
// TEST 3 : SUPPRESSION (SOFT DELETE) PAR USER C
// ----------------------------------------------------
Auth::setUser($userC);

$responsable->delete();
$trashed = ResponsableCatechese::withTrashed()->find($responsable->id);

echo "\n[3. Suppression (Soft Delete) par User C]\n";
echo "  created_by : " . ($trashed->created_by ?? 'NULL') . " (Attendu: {$userA->uuid})\n";
echo "  updated_by : " . ($trashed->updated_by ?? 'NULL') . " (Attendu: {$userB->uuid})\n";
echo "  deleted_by : " . ($trashed->deleted_by ?? 'NULL') . " (Attendu: {$userC->uuid})\n";
echo "  deleted_at : " . ($trashed->deleted_at ?? 'NULL') . " (Attendu: Date non nulle)\n";
assert($trashed->deleted_by === $userC->uuid, "Erreur deleted_by soft delete");
assert($trashed->deleted_at !== null, "Erreur deleted_at soft delete");

// ----------------------------------------------------
// TEST 4 : RESTAURATION PAR USER D
// ----------------------------------------------------
Auth::setUser($userD);

$trashed->restore();
$restored = ResponsableCatechese::find($responsable->id);

echo "\n[4. Restauration par User D]\n";
echo "  created_by : " . ($restored->created_by ?? 'NULL') . " (Attendu: {$userA->uuid})\n";
echo "  updated_by : " . ($restored->updated_by ?? 'NULL') . " (Attendu: {$userD->uuid})\n";
echo "  deleted_by : " . ($restored->deleted_by ?? 'NULL') . " (Attendu: NULL)\n";
echo "  deleted_at : " . ($restored->deleted_at ?? 'NULL') . " (Attendu: NULL)\n";
assert($restored->created_by === $userA->uuid, "Erreur created_by apres restauration");
assert($restored->updated_by === $userD->uuid, "Erreur updated_by apres restauration");
assert($restored->deleted_by === null, "Erreur deleted_by reset apres restauration");
assert($restored->deleted_at === null, "Erreur deleted_at reset apres restauration");

// ----------------------------------------------------
// TEST 5 : TEST DES RELATIONS ELOQUENT
// ----------------------------------------------------
echo "\n[5. Test des relations Eloquent createdBy / updatedBy]\n";
$creator = $restored->createdBy;
echo "  createdBy relation: " . ($creator ? "OK -> {$creator->nom} {$creator->prenoms}" : "User non trouvé (UUID mocké)") . "\n";

echo "\nTOUS LES TESTS D'AUDIT ONT REUSSI AVEC SUCCES A 100% !\n";

// Nettoyage
$restored->forceDelete();
