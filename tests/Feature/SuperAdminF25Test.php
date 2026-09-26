<?php

namespace Tests\Feature;

use App\Models\Abonnement;
use App\Models\ActionAuditLog;
use App\Models\CatecheseConfiguration;
use App\Models\Catechumene;
use App\Models\Formule;
use App\Models\Organisation;
use App\Models\Produit;
use App\Models\Profil;
use App\Models\User;
use Database\Seeders\OrganisationProfilSeeder;
use Database\Seeders\ProduitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuperAdminF25Test extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $normalUser;
    protected CatecheseConfiguration $paroisse;
    protected Produit $oppe;
    protected Produit $oppj;
    protected Produit $oppa;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->seed(ProduitSeeder::class);
        $this->seed(OrganisationProfilSeeder::class);

        $this->oppe = Produit::where('code', Produit::CODE_OPPE)->firstOrFail();
        $this->oppj = Produit::where('code', Produit::CODE_OPPJ)->firstOrFail();
        $this->oppa = Produit::where('code', Produit::CODE_OPPA)->firstOrFail();

        $this->paroisse = CatecheseConfiguration::create([
            'nom_paroisse'      => 'Paroisse Saint Michel Archange',
            'code_paroisse'     => 'PAR-MICHEL-' . uniqid(),
            'prefixe_matricule' => 'SM',
            'prefixe_recu'      => 'REC',
            'statut'            => 'actif',
            'ville'             => 'Abidjan',
            'diocese'           => 'Abidjan',
        ]);

        $this->superAdmin = User::create([
            'name'                      => 'Super Admin Test',
            'email'                     => 'superadmin.' . uniqid() . '@catheo.ci',
            'password'                  => Hash::make('SuperAdminPass123!'),
            'user_type'                 => 'super_admin',
            'statut'                    => 'actif',
            'paroisse_configuration_id' => null,
            'organisation_id'           => null,
        ]);

        $this->normalUser = User::create([
            'name'                      => 'Utilisateur Standard',
            'email'                     => 'user.' . uniqid() . '@catheo.ci',
            'password'                  => Hash::make('UserPass123!'),
            'user_type'                 => 'admin',
            'statut'                    => 'actif',
            'paroisse_configuration_id' => $this->paroisse->id,
            'organisation_id'           => null,
        ]);
    }

    /**
     * 1. Test création multiple automatique d'organisations pour une paroisse (Phase B & C).
     */
    public function test_batch_creation_of_organisations_for_paroisse(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Créer simultanément OPPE, OPPJ et OPPA via l'UUID de la paroisse
        $response = $this->postJson('/api/v1/super-admin/organisations', [
            'paroisse_id' => $this->paroisse->uuid,
            'produits'    => ['OPPE', 'OPPJ', 'OPPA'],
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'meta'   => [
                    'created_count' => 3,
                    'skipped_count' => 0,
                ],
            ]);

        $this->assertDatabaseHas('organisations', [
            'paroisse_configuration_id' => $this->paroisse->id,
            'type_organisation'         => 'OPPE',
        ]);
        $this->assertDatabaseHas('organisations', [
            'paroisse_configuration_id' => $this->paroisse->id,
            'type_organisation'         => 'OPPJ',
        ]);
        $this->assertDatabaseHas('organisations', [
            'paroisse_configuration_id' => $this->paroisse->id,
            'type_organisation'         => 'OPPA',
        ]);

        // Appel répété : ne doit pas créer de doublons (skipped_count = 3)
        $repeatResponse = $this->postJson('/api/v1/super-admin/organisations', [
            'paroisse_id' => $this->paroisse->uuid,
            'produits'    => ['OPPE', 'OPPJ', 'OPPA'],
        ]);

        $repeatResponse->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'meta'   => [
                    'created_count' => 0,
                    'skipped_count' => 3,
                ],
            ]);
    }

    /**
     * 2. Test bloc des organisations rattachées dans le détail paroisse (Phase B).
     */
    public function test_detail_paroisse_exposes_organisations_block(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Créer une organisation pour la paroisse
        $org = Organisation::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'produit_id'                => $this->oppe->id,
            'type_organisation'         => 'OPPE',
            'code'                      => 'OPPE-SM',
            'nom'                       => 'OPPE Saint Michel',
            'statut'                    => 'actif',
        ]);

        $response = $this->getJson("/api/v1/super-admin/paroisses/{$this->paroisse->uuid}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'id',
                    'nom_paroisse',
                    'total_organisations',
                    'organisations' => [
                        '*' => [
                            'id',
                            'uuid',
                            'type_organisation',
                            'produit_code',
                            'nom',
                            'statut',
                        ],
                    ],
                ],
            ]);

        $this->assertSame(1, $response->json('data.total_organisations'));
        $this->assertSame($org->uuid, $response->json('data.organisations.0.id'));
        $this->assertSame('OPPE', $response->json('data.organisations.0.type_organisation'));
    }

    /**
     * 3. Test CRUD complet Super Admin des Organisations (Phase C & D).
     */
    public function test_crud_organisations_by_uuid(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // A. Création unitaire
        $createResp = $this->postJson('/api/v1/super-admin/organisations', [
            'paroisse_id'       => $this->paroisse->uuid,
            'type_organisation' => 'OPPJ',
            'nom'               => 'Jeunesse Saint Michel',
            'description'       => 'Groupe des jeunes',
            'telephone'         => '+225 0102030405',
        ]);

        $createResp->assertStatus(201);
        $orgUuid = $createResp->json('data.uuid');
        $this->assertNotEmpty($orgUuid);

        // B. Détail
        $showResp = $this->getJson("/api/v1/super-admin/organisations/{$orgUuid}");
        $showResp->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => [
                    'nom'               => 'Jeunesse Saint Michel',
                    'type_organisation' => 'OPPJ',
                ],
            ]);

        // C. Mise à jour des coordonnées
        $updateResp = $this->putJson("/api/v1/super-admin/organisations/{$orgUuid}", [
            'nom'             => 'Jeunesse Saint Michel Modifiée',
            'description'     => 'Nouvelle description',
            'responsable_nom' => 'KOUASSI Yves',
        ]);

        $updateResp->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => [
                    'nom'             => 'Jeunesse Saint Michel Modifiée',
                    'responsable_nom' => 'KOUASSI Yves',
                ],
            ]);

        // D. Changement de statut (suspendu -> actif)
        $statusResp = $this->patchJson("/api/v1/super-admin/organisations/{$orgUuid}/statut", [
            'statut' => 'suspendu',
            'motif'  => 'Contrôle périodique',
        ]);

        $statusResp->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => [
                    'statut' => 'suspendu',
                ],
            ]);

        // E. Suppression (Soft Delete)
        $delResp = $this->deleteJson("/api/v1/super-admin/organisations/{$orgUuid}");
        $delResp->assertStatus(200);

        $this->assertSoftDeleted('organisations', ['uuid' => $orgUuid]);
    }

    /**
     * 4. Test Upload et public URL du logo Organisation (Phase D).
     */
    public function test_organisation_logo_upload(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $org = Organisation::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'produit_id'                => $this->oppe->id,
            'type_organisation'         => 'OPPE',
            'nom'                       => 'OPPE Logo Test',
            'statut'                    => 'actif',
        ]);

        $file = UploadedFile::fake()->create('logo_oppe.png', 100, 'image/png');

        $response = $this->putJson("/api/v1/super-admin/organisations/{$org->uuid}", [
            'logo' => $file,
        ]);

        $response->assertStatus(200);
        $org->refresh();

        $this->assertNotNull($org->logo_path);
        $this->assertNotNull($org->logo_url);
        $this->assertStringContainsString('storage/organisations/logos', $org->logo_url);
        Storage::disk('public')->assertExists('organisations/logos/' . $org->logo_path);
    }

    /**
     * 5. Test CRUD Utilisateurs Super Admin (Phase E).
     */
    public function test_super_admin_user_management(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $profil = Profil::where('code', 'SUPER_ADMIN')->first();

        // A. Création
        $createResp = $this->postJson('/api/v1/super-admin/users', [
            'name'        => 'Superviseur Plateforme',
            'email'       => 'superviseur.' . uniqid() . '@catheo.ci',
            'telephone'   => '+225 0505050505',
            'user_type'   => 'super_admin',
            'profil_id'   => $profil?->uuid,
            'statut'      => 'actif',
        ]);

        $createResp->assertStatus(201);
        $userUuid = $createResp->json('data.uuid');
        $this->assertNotEmpty($userUuid);

        // B. Liste avec filtre search
        $listResp = $this->getJson("/api/v1/super-admin/users?search=Superviseur");
        $listResp->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($listResp->json('data')));

        // C. Détail
        $showResp = $this->getJson("/api/v1/super-admin/users/{$userUuid}");
        $showResp->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => [
                    'name'      => 'Superviseur Plateforme',
                    'user_type' => 'super_admin',
                ],
            ]);

        // D. Modification
        $updateResp = $this->putJson("/api/v1/super-admin/users/{$userUuid}", [
            'name'      => 'Superviseur Modifié',
            'telephone' => '+225 0707070707',
        ]);
        $updateResp->assertStatus(200)
            ->assertJson([
                'data' => [
                    'name'      => 'Superviseur Modifié',
                    'telephone' => '+225 0707070707',
                ],
            ]);

        // E. Changement de statut
        $statusResp = $this->patchJson("/api/v1/super-admin/users/{$userUuid}/statut", [
            'statut' => 'suspendu',
        ]);
        $statusResp->assertStatus(200)
            ->assertJson([
                'data' => ['statut' => 'suspendu'],
            ]);

        // F. Réinitialisation mot de passe
        $resetResp = $this->postJson("/api/v1/super-admin/users/{$userUuid}/reset-password", [
            'password' => 'NouveauMotDePasse123!',
        ]);
        $resetResp->assertStatus(200);

        // G. Soft Delete
        $delResp = $this->deleteJson("/api/v1/super-admin/users/{$userUuid}");
        $delResp->assertStatus(200);
        $this->assertSoftDeleted('users', ['uuid' => $userUuid]);
    }

    /**
     * 6. Test Journal d'audit centralisé des actions (Phase F).
     */
    public function test_super_admin_audit_logs(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Déclencher une action qui log
        $this->postJson('/api/v1/super-admin/organisations', [
            'paroisse_id'       => $this->paroisse->uuid,
            'type_organisation' => 'OPPA',
            'nom'               => 'Adultes Saint Michel',
        ]);

        $auditResp = $this->getJson('/api/v1/super-admin/audit-logs?module=Organisation');
        $auditResp->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'id',
                        'action',
                        'module',
                        'description',
                        'utilisateur',
                    ],
                ],
            ]);

        $this->assertGreaterThanOrEqual(1, count($auditResp->json('data')));
        $firstLog = $auditResp->json('data.0');
        $this->assertSame('Organisation', $firstLog['module']);
    }

    /**
     * 7. Test Corbeille centrale (Trash), Restauration et Purge (Phase G & H).
     */
    public function test_trash_list_restore_and_force_delete(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Créer et supprimer un catéchumène
        $cat = Catechumene::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'matricule'                 => 'MAT-TRASH-01',
            'nom'                       => 'TEST_CORBEILLE',
            'prenoms'                   => 'Jean',
            'sexe'                      => 'M',
        ]);
        $cat->delete();

        // A. Consultation de la Corbeille
        $trashResp = $this->getJson('/api/v1/super-admin/trash?module=Catéchumène');
        $trashResp->assertStatus(200);

        $items = $trashResp->json('data');
        $this->assertGreaterThanOrEqual(1, count($items));
        $found = collect($items)->firstWhere('uuid', $cat->uuid);
        $this->assertNotNull($found);

        // B. Détail de l'élément dans la corbeille
        $showTrash = $this->getJson("/api/v1/super-admin/trash/{$cat->uuid}");
        $showTrash->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => [
                    'uuid'   => $cat->uuid,
                    'module' => 'Catéchumène',
                ],
            ]);

        // C. Restauration
        $restoreResp = $this->postJson("/api/v1/super-admin/trash/{$cat->uuid}/restore");
        $restoreResp->assertStatus(200);

        $cat->refresh();
        $this->assertNull($cat->deleted_at);

        // D. Re-suppression et suppression définitive (Force Delete)
        $cat->delete();
        $forceResp = $this->deleteJson("/api/v1/super-admin/trash/{$cat->uuid}/force");
        $forceResp->assertStatus(200);

        $this->assertDatabaseMissing('catechumenes', ['uuid' => $cat->uuid]);
    }

    /**
     * 8. Test de protection d'intégrité référentielle sur Force Delete d'une Paroisse.
     */
    public function test_cannot_force_delete_paroisse_with_active_dependencies(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Créer une organisation liée à la paroisse
        Organisation::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'produit_id'                => $this->oppe->id,
            'type_organisation'         => 'OPPE',
            'nom'                       => 'OPPE Dependance',
            'statut'                    => 'actif',
        ]);

        // Supprimer logiquement la paroisse
        $this->paroisse->delete();

        // Tenter de force delete la paroisse : doit échouer avec 422
        $resp = $this->deleteJson("/api/v1/super-admin/trash/{$this->paroisse->uuid}/force");
        $resp->assertStatus(422)
            ->assertJson([
                'status' => 'error',
            ]);

        // La paroisse existe toujours en soft-delete
        $this->assertSoftDeleted('paroisse_configurations', ['id' => $this->paroisse->id]);
    }

    /**
     * 9. Test de sécurité multi-tenant : un utilisateur normal ne peut pas accéder au Super Admin.
     */
    public function test_normal_user_cannot_access_super_admin(): void
    {
        Sanctum::actingAs($this->normalUser);

        $resp = $this->getJson('/api/v1/super-admin/organisations');
        $resp->assertStatus(403);

        $resp2 = $this->getJson('/api/v1/super-admin/trash');
        $resp2->assertStatus(403);

        $resp3 = $this->getJson('/api/v1/super-admin/audit-logs');
        $resp3->assertStatus(403);
    }

    /**
     * 10. Test Écart 1 & Recommandation A : Organisation indépendante (Scénario B) vs Liée (Scénario A).
     */
    public function test_independent_organisation_creation_and_modes(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Scénario B : Création d'une organisation indépendante (sans paroisse_id, independant=true)
        $respIndep = $this->postJson('/api/v1/super-admin/organisations', [
            'type_organisation' => 'OPPJ',
            'nom'               => 'OPPJ Communauté Saint Paul',
            'independant'       => true,
            'description'       => 'Mouvement de jeunesse indépendant',
        ]);

        $respIndep->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data'   => [
                    'nom'               => 'OPPJ Communauté Saint Paul',
                    'type_organisation' => 'OPPJ',
                    'mode'              => 'independant',
                ],
            ]);

        $indepUuid = $respIndep->json('data.uuid');
        $this->assertDatabaseHas('organisations', [
            'uuid'                      => $indepUuid,
            'mode'                      => 'independant',
            'paroisse_configuration_id' => null,
            'type_organisation'         => 'OPPJ',
        ]);

        // Scénario A : Organisation liée à une paroisse
        $respLiee = $this->postJson('/api/v1/super-admin/organisations', [
            'paroisse_id'       => $this->paroisse->uuid,
            'type_organisation' => 'OPPE',
            'nom'               => 'OPPE Paroissiale Saint Michel',
        ]);

        $respLiee->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data'   => [
                    'mode' => 'liee',
                ],
            ]);

        // Filtrage par mode
        $filterIndep = $this->getJson('/api/v1/super-admin/organisations?mode=independant');
        $filterIndep->assertStatus(200);
        $this->assertTrue(collect($filterIndep->json('data'))->contains('uuid', $indepUuid));

        $filterLiee = $this->getJson('/api/v1/super-admin/organisations?mode=liee');
        $filterLiee->assertStatus(200);
        $this->assertFalse(collect($filterLiee->json('data'))->contains('uuid', $indepUuid));
    }

    /**
     * 11. Test Écart 4 : Détail complet de l'organisation avec tous ses modules métiers.
     */
    public function test_organisation_detail_exposes_all_required_modules(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $org = Organisation::create([
            'mode'                      => 'independant',
            'paroisse_configuration_id' => null,
            'produit_id'                => $this->oppj->id,
            'type_organisation'         => 'OPPJ',
            'code'                      => 'OPPJ-EXP',
            'nom'                       => 'OPPJ Espace Complet',
            'responsable_nom'           => 'KONAN Serge',
            'responsable_telephone'     => '+225 0505050505',
            'responsable_email'         => 'serge@oppj.ci',
            'statut'                    => 'actif',
        ]);

        $resp = $this->getJson("/api/v1/super-admin/organisations/{$org->uuid}");

        $resp->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'nom',
                    'type_organisation',
                    'mode',
                    'informations' => [
                        'id',
                        'nom',
                        'code',
                        'type_organisation',
                        'mode',
                        'statut',
                    ],
                    'responsable' => [
                        'nom',
                        'telephone',
                        'email',
                    ],
                    'utilisateurs',
                    'statistiques' => [
                        'total_membres',
                        'total_activites',
                        'total_pelerinages',
                        'total_utilisateurs',
                        'total_operations',
                        'solde_caisse',
                    ],
                    'membres',
                    'activites',
                    'pelerinages',
                    'caisse' => [
                        'operations',
                        'solde_actuel',
                    ],
                    'abonnement',
                ],
            ]);

        $this->assertSame('independant', $resp->json('data.mode'));
        $this->assertSame('KONAN Serge', $resp->json('data.responsable.nom'));
    }

    /**
     * 12. Test Écart 3 : Formules tarifaires filtrées par contexte/produit.
     */
    public function test_formules_filtered_by_produit_and_organisation(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $catheoProduit = Produit::where('code', Produit::CODE_CATHEO)->firstOrFail();

        // Créer des formules pour CATHEO et pour OPPE
        $formuleCatheo = Formule::create([
            'produit_id'   => $catheoProduit->id,
            'code'         => 'CATHEO-STD',
            'nom'          => 'Formule Standard CATHEO',
            'periodicite'  => Formule::PERIODICITE_ANNUELLE,
            'montant'      => 50000,
            'devise'       => 'XOF',
            'est_gratuite' => false,
            'statut'       => 'actif',
        ]);

        $formuleOppe = Formule::create([
            'produit_id'   => $this->oppe->id,
            'code'         => 'OPPE-PRO',
            'nom'          => 'Formule Pro OPPE',
            'periodicite'  => Formule::PERIODICITE_ANNUELLE,
            'montant'      => 30000,
            'devise'       => 'XOF',
            'est_gratuite' => false,
            'statut'       => 'actif',
        ]);

        // A. GET /super-admin/formules?produit=OPPE
        $filterResp = $this->getJson('/api/v1/super-admin/formules?produit=OPPE');
        $filterResp->assertStatus(200);
        $oppeCodes = collect($filterResp->json('data'))->pluck('code');
        $this->assertTrue($oppeCodes->contains('OPPE-PRO'));
        $this->assertFalse($oppeCodes->contains('CATHEO-STD'));

        // B. GET /super-admin/organisations/{uuid}/formules
        $orgOppe = Organisation::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'produit_id'                => $this->oppe->id,
            'type_organisation'         => 'OPPE',
            'nom'                       => 'OPPE Sainte Famille',
            'statut'                    => 'actif',
        ]);

        $orgFormulesResp = $this->getJson("/api/v1/super-admin/organisations/{$orgOppe->uuid}/formules");
        $orgFormulesResp->assertStatus(200);
        $this->assertTrue(collect($orgFormulesResp->json('data'))->pluck('code')->contains('OPPE-PRO'));
        $this->assertFalse(collect($orgFormulesResp->json('data'))->pluck('code')->contains('CATHEO-STD'));
    }

    /**
     * 13. Test Écart 2 : Abonnements séparés Paroisse / Organisation.
     */
    public function test_separate_abonnements_for_paroisses_and_organisations(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $catheoProduit = Produit::where('code', Produit::CODE_CATHEO)->firstOrFail();

        $formuleCatheo = Formule::create([
            'produit_id'   => $catheoProduit->id,
            'code'         => 'CATHEO-PAR-' . uniqid(),
            'nom'          => 'Formule Paroisse CATHEO',
            'periodicite'  => Formule::PERIODICITE_ANNUELLE,
            'montant'      => 60000,
            'devise'       => 'XOF',
            'est_gratuite' => false,
            'statut'       => 'actif',
        ]);

        $formuleOppj = Formule::create([
            'produit_id'   => $this->oppj->id,
            'code'         => 'OPPJ-JEUNES-' . uniqid(),
            'nom'          => 'Formule OPPJ Jeunesse',
            'periodicite'  => Formule::PERIODICITE_MENSUELLE,
            'montant'      => 10000,
            'devise'       => 'XOF',
            'est_gratuite' => false,
            'statut'       => 'actif',
        ]);

        // A. Souscription d'une paroisse (CATHEO)
        $subParoisse = $this->postJson('/api/v1/super-admin/abonnements', [
            'paroisse_configuration_id' => $this->paroisse->id,
            'formule_id'                => $formuleCatheo->id,
        ]);
        $subParoisse->assertStatus(201);

        // B. Souscription d'une organisation indépendante à une formule OPPJ
        $orgOppj = Organisation::create([
            'mode'                      => 'independant',
            'paroisse_configuration_id' => null,
            'produit_id'                => $this->oppj->id,
            'type_organisation'         => 'OPPJ',
            'nom'                       => 'OPPJ Indépendant Sub',
            'statut'                    => 'actif',
        ]);

        $subOrg = $this->postJson('/api/v1/super-admin/abonnements/organisations', [
            'organisation_id' => $orgOppj->uuid,
            'formule_id'      => $formuleOppj->uuid,
            'observation'     => 'Abonnement direct organisation',
        ]);
        $subOrg->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data'   => [
                    'organisation_nom'  => 'OPPJ Indépendant Sub',
                    'organisation_type' => 'OPPJ',
                    'mode'              => 'independant',
                ],
            ]);

        // C. Protection de compatibilité : tentative de souscription d'une organisation OPPJ à une formule CATHEO
        $badSub = $this->postJson('/api/v1/super-admin/abonnements/organisations', [
            'organisation_id' => $orgOppj->uuid,
            'formule_id'      => $formuleCatheo->uuid,
        ]);
        $badSub->assertStatus(422);

        // D. Liste GET /super-admin/abonnements/paroisses (ne contient pas l'organisation)
        $listParoisses = $this->getJson('/api/v1/super-admin/abonnements/paroisses');
        $listParoisses->assertStatus(200);
        $this->assertTrue(collect($listParoisses->json('data'))->contains('paroisse_nom', $this->paroisse->nom_paroisse));
        $this->assertFalse(collect($listParoisses->json('data'))->contains('organisation_nom', 'OPPJ Indépendant Sub'));

        // E. Liste GET /super-admin/abonnements/organisations (contient l'organisation)
        $listOrgs = $this->getJson('/api/v1/super-admin/abonnements/organisations');
        $listOrgs->assertStatus(200);
        $this->assertTrue(collect($listOrgs->json('data'))->contains('organisation_nom', 'OPPJ Indépendant Sub'));
    }

    /**
     * 14. Test Recommandations B & C : Corbeille aperçu avant restauration et historique de restauration.
     */
    public function test_trash_preview_and_restoration_history(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $org = Organisation::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'produit_id'                => $this->oppe->id,
            'type_organisation'         => 'OPPE',
            'nom'                       => 'OPPE Pour Corbeille',
            'statut'                    => 'actif',
        ]);

        // Supprimer logiquement
        $delResp = $this->deleteJson("/api/v1/super-admin/organisations/{$org->uuid}");
        $delResp->assertStatus(200);

        // Recommandation C : GET /super-admin/trash/{uuid} avec aperçu avant restauration
        $previewResp = $this->getJson("/api/v1/super-admin/trash/{$org->uuid}");
        $previewResp->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'nom',
                    'module',
                    'date_suppression',
                    'supprime_par',
                    'dependances',
                    'bouton_restaurer',
                    'apercu_restauration' => [
                        'nom',
                        'module',
                        'date',
                        'supprime_par',
                        'dependances',
                        'bouton_restaurer',
                    ],
                ],
            ]);

        $this->assertSame('OPPE Pour Corbeille', $previewResp->json('data.apercu_restauration.nom'));
        $this->assertTrue($previewResp->json('data.apercu_restauration.bouton_restaurer'));

        // Recommandation B : Restauration avec traçabilité Ancien état -> Nouvel état (supprimé par / restauré par)
        $restoreResp = $this->postJson("/api/v1/super-admin/trash/{$org->uuid}/restore");
        $restoreResp->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        $this->assertNotEmpty($restoreResp->json('data.restaure_par'));

        // Vérifier l'audit log de restauration
        $auditLog = ActionAuditLog::where('action', 'restore')
            ->where(function ($q) use ($org) {
                $q->where('entite_uuid', $org->uuid)
                  ->orWhere('entite_id', $org->id);
            })
            ->latest('id')
            ->first();

        $this->assertNotNull($auditLog);
        $this->assertSame('actif', $auditLog->nouvelles_valeurs['statut'] ?? null);
        $this->assertNotEmpty($auditLog->nouvelles_valeurs['restaure_par'] ?? null);
    }
}
