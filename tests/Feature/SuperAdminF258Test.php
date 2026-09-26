<?php

namespace Tests\Feature;

use App\Models\CatecheseConfiguration;
use App\Models\Organisation;
use App\Models\Produit;
use App\Models\User;
use Database\Seeders\OrganisationProfilSeeder;
use Database\Seeders\ProduitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * F25.8 — Tests de création autonome des Paroisses et Organisations
 *
 * Tous les anciens tests F25 (14/14) doivent rester verts en parallèle.
 */
class SuperAdminF258Test extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;
    private User $normalUser;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seed(ProduitSeeder::class);
        $this->seed(OrganisationProfilSeeder::class);

        $this->superAdmin = User::create([
            'name'      => 'Super Admin F258',
            'email'     => 'superadmin258.' . uniqid() . '@catheo.ci',
            'password'  => Hash::make('SuperAdminPass123!'),
            'user_type' => 'super_admin',
            'statut'    => 'actif',
        ]);

        $this->normalUser = User::create([
            'name'      => 'Normal User F258',
            'email'     => 'normaluser258.' . uniqid() . '@catheo.ci',
            'password'  => Hash::make('NormalUserPass123!'),
            'user_type' => 'admin',
            'statut'    => 'actif',
        ]);
    }

    // =========================================================
    // PHASE B — Création directe d'une Paroisse
    // =========================================================

    public function test_super_admin_can_create_paroisse_directly(): void
    {
        $response = $this->actingAs($this->superAdmin)->postJson('/api/v1/super-admin/paroisses', [
            'nom_paroisse'  => 'Paroisse Saint Michel Archange',
            'code_paroisse' => 'STMICF258',
            'diocese'       => 'Abidjan',
            'doyenne'       => 'Cocody',
            'ville'         => 'Abidjan',
            'commune'       => 'Cocody',
            'telephone'     => '+2250102030405',
            'email'         => 'saintmichel258@catheo.ci',
            'adresse'       => 'Angré',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('status', 'success')
                 ->assertJsonPath('data.nom_paroisse', 'Paroisse Saint Michel Archange')
                 ->assertJsonPath('data.statut', 'cree');

        $this->assertDatabaseHas('paroisse_configurations', [
            'code_paroisse'     => 'STMICF258',
            'prefixe_matricule' => 'STMICF258',
            'prefixe_recu'      => 'STMICF258-R',
            'statut'            => 'cree',
        ]);
    }

    public function test_paroisse_creation_requires_mandatory_fields(): void
    {
        $response = $this->actingAs($this->superAdmin)->postJson('/api/v1/super-admin/paroisses', [
            'nom_paroisse' => 'Paroisse Sans Code',
            // code_paroisse and diocese missing
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['code_paroisse', 'diocese']);
    }

    public function test_paroisse_code_must_be_unique(): void
    {
        CatecheseConfiguration::create([
            'nom_paroisse'      => 'Existing Paroisse',
            'code_paroisse'     => 'EXIST258',
            'prefixe_matricule' => 'EXIST258',
            'prefixe_recu'      => 'EXIST258-R',
            'diocese'           => 'Abidjan',
            'statut'            => 'cree',
        ]);

        $response = $this->actingAs($this->superAdmin)->postJson('/api/v1/super-admin/paroisses', [
            'nom_paroisse'  => 'Another Paroisse',
            'code_paroisse' => 'EXIST258',   // duplicate
            'diocese'       => 'Bouaké',
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['code_paroisse']);
    }

    // =========================================================
    // PHASE C — Nouvelle paroisse apparaît dans la liste
    // =========================================================

    public function test_new_paroisse_appears_immediately_in_list(): void
    {
        $this->actingAs($this->superAdmin)->postJson('/api/v1/super-admin/paroisses', [
            'nom_paroisse'  => 'Paroisse Sainte Famille F258',
            'code_paroisse' => 'STFAMF258',
            'diocese'       => 'Bouaké',
        ])->assertStatus(201);

        $list = $this->actingAs($this->superAdmin)->getJson('/api/v1/super-admin/paroisses');
        $list->assertStatus(200)
             ->assertJsonPath('status', 'success');

        $noms = collect($list->json('data'))->pluck('nom_paroisse');
        $this->assertContains('Paroisse Sainte Famille F258', $noms->toArray());
    }

    // =========================================================
    // PHASE D — Création directe d'une Organisation
    // =========================================================

    public function test_can_create_oppe_organisation_liee_a_paroisse(): void
    {
        $paroisse = CatecheseConfiguration::create([
            'nom_paroisse'      => 'Paroisse Test F258 Linked',
            'code_paroisse'     => 'TFLNK258',
            'prefixe_matricule' => 'TFLNK258',
            'prefixe_recu'      => 'TFLNK258-R',
            'diocese'           => 'Abidjan',
            'statut'            => 'actif',
        ]);

        $response = $this->actingAs($this->superAdmin)->postJson('/api/v1/super-admin/organisations', [
            'type_organisation' => 'OPPE',
            'nom'               => 'OPPE Saint Michel F258',
            'paroisse_id'       => $paroisse->uuid,
            'description'       => 'Organisation des parents',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('organisations', [
            'type_organisation'         => 'OPPE',
            'nom'                       => 'OPPE Saint Michel F258',
            'paroisse_configuration_id' => $paroisse->id,
            'mode'                      => 'liee',
        ]);
    }

    public function test_can_create_oppj_organisation_independante(): void
    {
        $response = $this->actingAs($this->superAdmin)->postJson('/api/v1/super-admin/organisations', [
            'type_organisation' => 'OPPJ',
            'nom'               => 'OPPJ Communauté Saint Paul F258',
            'independant'       => true,
            'description'       => 'Organisation indépendante',
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('organisations', [
            'type_organisation' => 'OPPJ',
            'nom'               => 'OPPJ Communauté Saint Paul F258',
            'mode'              => 'independant',
        ]);
    }

    public function test_independent_organisations_are_not_blocked_by_doublon_rule(): void
    {
        $paroisse = CatecheseConfiguration::create([
            'nom_paroisse'      => 'Paroisse Doublon F258',
            'code_paroisse'     => 'DBL258',
            'prefixe_matricule' => 'DBL258',
            'prefixe_recu'      => 'DBL258-R',
            'diocese'           => 'Abidjan',
            'statut'            => 'actif',
        ]);

        // Première OPPE liée à une paroisse
        $this->actingAs($this->superAdmin)->postJson('/api/v1/super-admin/organisations', [
            'type_organisation' => 'OPPE',
            'nom'               => 'OPPE Liée Doublon F258',
            'paroisse_id'       => $paroisse->uuid,
        ])->assertStatus(201);

        // OPPE indépendante — ne doit PAS être bloquée par la règle doublon
        $response = $this->actingAs($this->superAdmin)->postJson('/api/v1/super-admin/organisations', [
            'type_organisation' => 'OPPE',
            'nom'               => 'OPPE Indépendante Libre F258',
            'independant'       => true,
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('status', 'success');
    }

    // =========================================================
    // PHASE E — Modification du rattachement paroisse
    // =========================================================

    public function test_can_change_organisation_paroisse(): void
    {
        $paroisse1 = CatecheseConfiguration::create([
            'nom_paroisse'      => 'Paroisse A F258',
            'code_paroisse'     => 'PA258',
            'prefixe_matricule' => 'PA258',
            'prefixe_recu'      => 'PA258-R',
            'diocese'           => 'Abidjan',
            'statut'            => 'actif',
        ]);

        $paroisse2 = CatecheseConfiguration::create([
            'nom_paroisse'      => 'Paroisse B F258',
            'code_paroisse'     => 'PB258',
            'prefixe_matricule' => 'PB258',
            'prefixe_recu'      => 'PB258-R',
            'diocese'           => 'Bouaké',
            'statut'            => 'actif',
        ]);

        $produit = Produit::where('code', 'OPPE')->firstOrFail();

        $org = Organisation::create([
            'mode'                      => 'liee',
            'paroisse_configuration_id' => $paroisse1->id,
            'produit_id'                => $produit->id,
            'type_organisation'         => 'OPPE',
            'code'                      => 'OPPE-PA258',
            'nom'                       => 'OPPE Paroisse A F258',
            'statut'                    => 'actif',
        ]);

        $response = $this->actingAs($this->superAdmin)->putJson("/api/v1/super-admin/organisations/{$org->uuid}", [
            'paroisse_id' => $paroisse2->uuid,
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('organisations', [
            'id'                        => $org->id,
            'paroisse_configuration_id' => $paroisse2->id,
        ]);
    }

    // =========================================================
    // PHASE G — Audit
    // =========================================================

    public function test_paroisse_creation_is_audited(): void
    {
        $this->actingAs($this->superAdmin)->postJson('/api/v1/super-admin/paroisses', [
            'nom_paroisse'  => 'Paroisse Audit F258',
            'code_paroisse' => 'AUDT258',
            'diocese'       => 'Abidjan',
        ])->assertStatus(201);

        $auditLogs = $this->actingAs($this->superAdmin)
                          ->getJson('/api/v1/super-admin/audit-logs?module=Paroisse');
        $auditLogs->assertStatus(200);

        $logs = collect($auditLogs->json('data'));
        $createLog = $logs->first(fn($l) => ($l['action'] ?? '') === 'create'
            && str_contains($l['description'] ?? '', 'AUDT258'));
        $this->assertNotNull($createLog, 'Aucun log de création de paroisse trouvé.');
    }

    public function test_organisation_creation_is_audited(): void
    {
        $this->actingAs($this->superAdmin)->postJson('/api/v1/super-admin/organisations', [
            'type_organisation' => 'OPPA',
            'nom'               => 'OPPA Audit F258',
            'independant'       => true,
        ])->assertStatus(201);

        $auditLogs = $this->actingAs($this->superAdmin)
                          ->getJson('/api/v1/super-admin/audit-logs?module=Organisation');
        $auditLogs->assertStatus(200);

        $logs = collect($auditLogs->json('data'));
        $this->assertTrue(
            $logs->contains(fn($l) => ($l['action'] ?? '') === 'create'
                && str_contains($l['description'] ?? '', 'OPPA Audit F258')),
            'Log de création organisation non trouvé.'
        );
    }

    // =========================================================
    // PHASE I — Mise à jour paroisse
    // =========================================================

    public function test_super_admin_can_update_paroisse(): void
    {
        $paroisse = CatecheseConfiguration::create([
            'nom_paroisse'      => 'Paroisse Originale F258',
            'code_paroisse'     => 'ORIG258',
            'prefixe_matricule' => 'ORIG258',
            'prefixe_recu'      => 'ORIG258-R',
            'diocese'           => 'Abidjan',
            'statut'            => 'cree',
        ]);

        $response = $this->actingAs($this->superAdmin)->putJson("/api/v1/super-admin/paroisses/{$paroisse->uuid}", [
            'nom_paroisse' => 'Paroisse Modifiée F258',
            'cure_nom'     => 'Père Jean Dupont',
            'ville'        => 'Abidjan',
        ]);

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success');

        $this->assertDatabaseHas('paroisse_configurations', [
            'id'           => $paroisse->id,
            'nom_paroisse' => 'Paroisse Modifiée F258',
            'cure_nom'     => 'Père Jean Dupont',
        ]);
    }

    public function test_cannot_create_paroisse_without_super_admin_role(): void
    {
        $response = $this->actingAs($this->normalUser)->postJson('/api/v1/super-admin/paroisses', [
            'nom_paroisse'  => 'Paroisse Non Autorisée',
            'code_paroisse' => 'NAUTH258',
            'diocese'       => 'Abidjan',
        ]);

        $response->assertStatus(403);
    }

    // =========================================================
    // PHASE I — Logo upload organisation indépendante
    // =========================================================

    public function test_can_upload_logo_when_creating_independent_organisation(): void
    {
        $response = $this->actingAs($this->superAdmin)->postJson('/api/v1/super-admin/organisations', [
            'type_organisation' => 'OPPE',
            'nom'               => 'OPPE Logo F258',
            'independant'       => true,
            'logo'              => UploadedFile::fake()->create('logo.png', 10, 'image/png'),
        ]);

        $response->assertStatus(201)
                 ->assertJsonPath('status', 'success');

        $org = Organisation::where('nom', 'OPPE Logo F258')->first();
        $this->assertNotNull($org);
        $this->assertNotNull($org->logo_path, 'Logo path should be set after upload.');
        Storage::disk('public')->assertExists("organisations/logos/{$org->logo_path}");
    }

    // =========================================================
    // SOFT DELETE PAROISSE
    // =========================================================

    public function test_cannot_soft_delete_paroisse_with_active_organisations(): void
    {
        $paroisse = CatecheseConfiguration::create([
            'nom_paroisse'      => 'Paroisse Avec Orgs F258',
            'code_paroisse'     => 'AORGS258',
            'prefixe_matricule' => 'AORGS258',
            'prefixe_recu'      => 'AORGS258-R',
            'diocese'           => 'Abidjan',
            'statut'            => 'actif',
        ]);

        $produit = Produit::where('code', 'OPPE')->firstOrFail();
        Organisation::create([
            'mode'                      => 'liee',
            'paroisse_configuration_id' => $paroisse->id,
            'produit_id'                => $produit->id,
            'type_organisation'         => 'OPPE',
            'code'                      => 'OPPE-AORGS258',
            'nom'                       => 'OPPE Active F258',
            'statut'                    => 'actif',
        ]);

        $response = $this->actingAs($this->superAdmin)
                         ->deleteJson("/api/v1/super-admin/paroisses/{$paroisse->uuid}");

        $response->assertStatus(422)
                 ->assertJsonPath('status', 'error');
    }

    public function test_can_soft_delete_paroisse_without_organisations(): void
    {
        $paroisse = CatecheseConfiguration::create([
            'nom_paroisse'      => 'Paroisse Vide F258',
            'code_paroisse'     => 'VIDE258',
            'prefixe_matricule' => 'VIDE258',
            'prefixe_recu'      => 'VIDE258-R',
            'diocese'           => 'Abidjan',
            'statut'            => 'cree',
        ]);

        $response = $this->actingAs($this->superAdmin)
                         ->deleteJson("/api/v1/super-admin/paroisses/{$paroisse->uuid}");

        $response->assertStatus(200)
                 ->assertJsonPath('status', 'success');

        $this->assertSoftDeleted('paroisse_configurations', ['id' => $paroisse->id]);
    }
}