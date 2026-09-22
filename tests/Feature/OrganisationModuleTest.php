<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\AnneeCatechese;
use App\Models\CatecheseConfiguration;
use App\Models\Catechumene;
use App\Models\Classe;
use App\Models\InscriptionAnnuelle;
use App\Models\Membre;
use App\Models\Niveau;
use App\Models\Organisation;
use App\Models\Produit;
use App\Models\Profil;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\OrganisationProfilSeeder;
use Database\Seeders\ProduitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganisationModuleTest extends TestCase
{
    use RefreshDatabase;

    protected CatecheseConfiguration $paroisseA;
    protected CatecheseConfiguration $paroisseB;
    protected Produit $produitOppe;
    protected Produit $produitOppj;
    protected Produit $produitOppa;

    protected Organisation $oppeA;
    protected Organisation $oppjA;
    protected Organisation $oppaA;
    protected Organisation $oppeB;

    protected User $userOppeA;
    protected User $userOppjA;
    protected User $userOppaA;
    protected User $userOppeB;
    protected User $superAdmin;

    protected AnneeCatechese $anneeCouranteA;
    protected AnneeCatechese $anneePasseeA;

    protected Section $secEnfPri;
    protected Section $secEnfCol;
    protected Section $secJeunes;
    protected Section $secAdultes;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Seed des produits et profils
        $this->seed(ProduitSeeder::class);
        $this->seed(OrganisationProfilSeeder::class);

        $this->produitOppe = Produit::where('code', Produit::CODE_OPPE)->firstOrFail();
        $this->produitOppj = Produit::where('code', Produit::CODE_OPPJ)->firstOrFail();
        $this->produitOppa = Produit::where('code', Produit::CODE_OPPA)->firstOrFail();

        // 2. Paroisses
        $this->paroisseA = CatecheseConfiguration::create([
            'nom_paroisse'      => 'Paroisse Sainte Trinité A',
            'code_paroisse'     => 'PAR-TRIN-A-' . uniqid(),
            'prefixe_matricule' => 'TA',
            'prefixe_recu'      => 'REC',
            'statut'            => 'actif',
            'ville'             => 'Abidjan',
            'diocese'           => 'Abidjan',
        ]);

        $this->paroisseB = CatecheseConfiguration::create([
            'nom_paroisse'      => 'Paroisse Saint Esprit B',
            'code_paroisse'     => 'PAR-ESP-B-' . uniqid(),
            'prefixe_matricule' => 'EB',
            'prefixe_recu'      => 'REC',
            'statut'            => 'actif',
            'ville'             => 'Yamoussoukro',
            'diocese'           => 'Yamoussoukro',
        ]);

        // 3. Organisations
        $this->oppeA = Organisation::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'produit_id'                => $this->produitOppe->id,
            'type_organisation'         => Organisation::TYPE_OPPE,
            'code'                      => 'OPPE-TRIN-A',
            'nom'                       => 'OPPE Sainte Trinité',
            'statut'                    => 'actif',
        ]);

        $this->oppjA = Organisation::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'produit_id'                => $this->produitOppj->id,
            'type_organisation'         => Organisation::TYPE_OPPJ,
            'code'                      => 'OPPJ-TRIN-A',
            'nom'                       => 'OPPJ Sainte Trinité',
            'statut'                    => 'actif',
        ]);

        $this->oppaA = Organisation::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'produit_id'                => $this->produitOppa->id,
            'type_organisation'         => Organisation::TYPE_OPPA,
            'code'                      => 'OPPA-TRIN-A',
            'nom'                       => 'OPPA Sainte Trinité',
            'statut'                    => 'actif',
        ]);

        $this->oppeB = Organisation::create([
            'paroisse_configuration_id' => $this->paroisseB->id,
            'produit_id'                => $this->produitOppe->id,
            'type_organisation'         => Organisation::TYPE_OPPE,
            'code'                      => 'OPPE-ESP-B',
            'nom'                       => 'OPPE Saint Esprit',
            'statut'                    => 'actif',
        ]);

        // 4. Utilisateurs d'organisation
        $profilRespOppe = Profil::where('code', 'RESPONSABLE_OPPE')->first();
        $this->userOppeA = User::create([
            'name'                      => 'Responsable OPPE A',
            'email'                     => 'oppe.a.' . uniqid() . '@test.ci',
            'password'                  => Hash::make('Secret123!'),
            'paroisse_configuration_id' => $this->paroisseA->id,
            'organisation_id'           => $this->oppeA->id,
            'profil_id'                 => $profilRespOppe?->id,
            'user_type'                 => 'admin',
            'statut'                    => 'actif',
        ]);

        $profilRespOppj = Profil::where('code', 'RESPONSABLE_OPPJ')->first();
        $this->userOppjA = User::create([
            'name'                      => 'Responsable OPPJ A',
            'email'                     => 'oppj.a.' . uniqid() . '@test.ci',
            'password'                  => Hash::make('Secret123!'),
            'paroisse_configuration_id' => $this->paroisseA->id,
            'organisation_id'           => $this->oppjA->id,
            'profil_id'                 => $profilRespOppj?->id,
            'user_type'                 => 'admin',
            'statut'                    => 'actif',
        ]);

        $profilRespOppa = Profil::where('code', 'RESPONSABLE_OPPA')->first();
        $this->userOppaA = User::create([
            'name'                      => 'Responsable OPPA A',
            'email'                     => 'oppa.a.' . uniqid() . '@test.ci',
            'password'                  => Hash::make('Secret123!'),
            'paroisse_configuration_id' => $this->paroisseA->id,
            'organisation_id'           => $this->oppaA->id,
            'profil_id'                 => $profilRespOppa?->id,
            'user_type'                 => 'admin',
            'statut'                    => 'actif',
        ]);

        $this->userOppeB = User::create([
            'name'                      => 'Responsable OPPE B',
            'email'                     => 'oppe.b.' . uniqid() . '@test.ci',
            'password'                  => Hash::make('Secret123!'),
            'paroisse_configuration_id' => $this->paroisseB->id,
            'organisation_id'           => $this->oppeB->id,
            'user_type'                 => 'admin',
            'statut'                    => 'actif',
        ]);

        // 5. Super Admin
        $this->superAdmin = User::create([
            'name'                      => 'Super Admin Test',
            'email'                     => 'superadmin.' . uniqid() . '@catheo.ci',
            'password'                  => Hash::make('SuperAdminPass123!'),
            'user_type'                 => 'super_admin',
            'statut'                    => 'actif',
            'paroisse_configuration_id' => null,
            'organisation_id'           => null,
        ]);

        // 6. Années pastorales pour Paroisse A
        $this->anneePasseeA = AnneeCatechese::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'libelle'                   => '2024-2025',
            'date_debut'                => '2024-09-01',
            'date_fin'                  => '2025-06-30',
            'statut'                    => 'cloturee',
        ]);

        $this->anneeCouranteA = AnneeCatechese::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'libelle'                   => '2025-2026',
            'date_debut'                => '2025-09-01',
            'date_fin'                  => '2026-06-30',
            'statut'                    => 'active',
        ]);

        // 7. Sections selon les codes stricts officiels
        $this->secEnfPri = Section::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'code'                      => 'SEC-ENFANTS-PRI',
            'nom'                       => 'Enfant Primaire',
            'statut'                    => 'actif',
        ]);

        $this->secEnfCol = Section::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'code'                      => 'SEC-ENFANTS-COL',
            'nom'                       => 'Enfant Collège',
            'statut'                    => 'actif',
        ]);

        $this->secJeunes = Section::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'code'                      => 'SEC-JEUNES',
            'nom'                       => 'Jeunes',
            'statut'                    => 'actif',
        ]);

        $this->secAdultes = Section::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'code'                      => 'SEC-ADULTES',
            'nom'                       => 'Adultes',
            'statut'                    => 'actif',
        ]);
    }

    // =========================================================================
    // 1. AUDIT DE LA ROUTE DUPLIQUÉE /super-admin/paiements (CORRECTION ÉTAPE 2)
    // =========================================================================

    public function test_super_admin_paiements_duplicate_route_is_removed(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // L'ancienne route alias n'existe plus
        $respDuplicate = $this->getJson('/api/v1/super-admin/paiements');
        $respDuplicate->assertStatus(404);

        // La route officielle canonique fonctionne parfaitement
        $respOfficial = $this->getJson('/api/v1/super-admin/paiements-abonnement');
        $respOfficial->assertStatus(200);
    }

    // =========================================================================
    // 2. CONTEXTE & SÉCURITÉ DE L'ORGANISATION
    // =========================================================================

    public function test_unauthenticated_user_cannot_access_organisation_routes(): void
    {
        $resp = $this->getJson('/api/v1/organisation/context');
        $resp->assertStatus(401);
    }

    public function test_user_without_organisation_context_is_denied(): void
    {
        $userSansOrg = User::create([
            'name'                      => 'User Sans Org',
            'email'                     => 'no.org.' . uniqid() . '@test.ci',
            'password'                  => Hash::make('Pass123!'),
            'paroisse_configuration_id' => $this->paroisseA->id,
            'organisation_id'           => null,
            'user_type'                 => 'utilisateur',
            'statut'                    => 'actif',
        ]);

        Sanctum::actingAs($userSansOrg);

        $resp = $this->getJson('/api/v1/organisation/context');
        $resp->assertStatus(403)
            ->assertJson([
                'status'  => 'error',
                'message' => 'Accès refusé. Aucun contexte d\'organisation valide n\'a été détecté pour votre compte.',
            ]);
    }

    public function test_organisation_user_retrieves_certified_context(): void
    {
        Sanctum::actingAs($this->userOppeA);

        $resp = $this->getJson('/api/v1/organisation/context');
        $resp->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => [
                    'type_organisation' => 'OPPE',
                    'code'              => 'OPPE-TRIN-A',
                    'nom'               => 'OPPE Sainte Trinité',
                    'produit_code'      => 'OPPE',
                ],
            ]);
    }

    // =========================================================================
    // 3. CRUD MEMBRES
    // =========================================================================

    public function test_membre_crud_operations_and_soft_delete(): void
    {
        Sanctum::actingAs($this->userOppeA);

        // 1. Création
        $storeResp = $this->postJson('/api/v1/organisation/membres', [
            'nom'            => 'KOUASSI',
            'prenoms'        => 'Jean-Philippe',
            'sexe'           => 'M',
            'date_naissance' => '2016-04-12',
            'telephone'      => '0701020304',
            'email'          => 'jp.kouassi@test.ci',
            'fonction'       => 'Membre',
            'statut'         => 'actif',
        ]);

        $storeResp->assertStatus(201);
        $membreId = $storeResp->json('data.id');
        $membreIdInterne = $storeResp->json('data.id_interne');

        $this->assertDatabaseHas('membres', [
            'id'              => $membreIdInterne,
            'organisation_id' => $this->oppeA->id,
            'nom'             => 'KOUASSI',
        ]);

        // 2. Consultation
        $showResp = $this->getJson("/api/v1/organisation/membres/{$membreId}");
        $showResp->assertStatus(200)
            ->assertJson([
                'data' => [
                    'nom_complet' => 'KOUASSI Jean-Philippe',
                    'statut'      => 'actif',
                ],
            ]);

        // 3. Modification
        $updateResp = $this->putJson("/api/v1/organisation/membres/{$membreId}", [
            'fonction' => 'Délégué Jeune Pousse',
        ]);
        $updateResp->assertStatus(200);
        $this->assertSame('Délégué Jeune Pousse', Membre::find($membreIdInterne)->fonction);

        // 4. Suppression logique (SoftDelete)
        $deleteResp = $this->deleteJson("/api/v1/organisation/membres/{$membreId}");
        $deleteResp->assertStatus(200);

        $this->assertSoftDeleted('membres', ['id' => $membreIdInterne]);
    }

    // =========================================================================
    // 4. ISOLATION MULTI-TENANT (ORGANISATIONS & PAROISSES)
    // =========================================================================

    public function test_strict_isolation_between_organisations_and_parishes(): void
    {
        // Créer un membre dans OPPE A
        $membreA = Membre::create([
            'organisation_id' => $this->oppeA->id,
            'nom'             => 'MEMBRE_A',
            'prenoms'         => 'Unique A',
            'sexe'            => 'M',
        ]);

        // Créer un membre dans OPPE B (autre paroisse)
        $membreB = Membre::create([
            'organisation_id' => $this->oppeB->id,
            'nom'             => 'MEMBRE_B',
            'prenoms'         => 'Unique B',
            'sexe'            => 'F',
        ]);

        // 1. L'utilisateur d'OPPE A ne doit pas voir le membre d'OPPE B
        Sanctum::actingAs($this->userOppeA);
        $listA = $this->getJson('/api/v1/organisation/membres')->json('data');
        $idsA = array_column($listA, 'id_interne');
        $this->assertContains($membreA->id, $idsA);
        $this->assertNotContains($membreB->id, $idsA);

        // Tentative d'accès IDOR direct par OPPE A sur le membre B -> 404
        $idorResp = $this->getJson("/api/v1/organisation/membres/{$membreB->uuid}");
        $idorResp->assertStatus(404);

        // 2. L'utilisateur d'OPPJ A (même paroisse mais produit distinct) ne doit pas voir le membre d'OPPE A
        Sanctum::actingAs($this->userOppjA);
        $listOppj = $this->getJson('/api/v1/organisation/membres')->json('data');
        $idsOppj = array_column($listOppj, 'id_interne');
        $this->assertNotContains($membreA->id, $idsOppj);

        $idorCrossProd = $this->getJson("/api/v1/organisation/membres/{$membreA->uuid}");
        $idorCrossProd->assertStatus(404);
    }

    // =========================================================================
    // 5. ACTIVITÉS & RÈGLE STRICTE DU RESPONSABLE INTERNE
    // =========================================================================

    public function test_activite_requires_responsable_to_belong_to_same_organisation(): void
    {
        Sanctum::actingAs($this->userOppeA);

        $membreInterne = Membre::create([
            'organisation_id' => $this->oppeA->id,
            'nom'             => 'INTERNE',
            'prenoms'         => 'Valide',
            'sexe'            => 'M',
        ]);

        $membreExterne = Membre::create([
            'organisation_id' => $this->oppeB->id,
            'nom'             => 'EXTERNE',
            'prenoms'         => 'Invalide',
            'sexe'            => 'F',
        ]);

        // 1. Création avec responsable interne -> Succès
        $validResp = $this->postJson('/api/v1/organisation/activites', [
            'titre'          => 'Kermesse des Petits Enfants',
            'date_debut'     => '2026-11-15 09:00:00',
            'responsable_id' => $membreInterne->id,
            'statut'         => 'planifiee',
        ]);
        $validResp->assertStatus(201);
        $this->assertSame('Kermesse des Petits Enfants', $validResp->json('data.titre'));

        // 2. Création avec responsable externe -> Refus 422
        $invalidResp = $this->postJson('/api/v1/organisation/activites', [
            'titre'          => 'Activité Frauduleuse',
            'date_debut'     => '2026-12-01 10:00:00',
            'responsable_id' => $membreExterne->id,
        ]);
        $invalidResp->assertStatus(422)
            ->assertJson([
                'status'  => 'error',
                'message' => 'Le responsable désigné doit obligatoirement être un membre de la même organisation.',
            ]);
    }

    // =========================================================================
    // 6. UTILISATEURS D'ORGANISATION & PROVISIONNEMENT SUPER ADMIN
    // =========================================================================

    public function test_organisation_user_creation_and_status_toggle(): void
    {
        Sanctum::actingAs($this->userOppeA);

        $storeUserResp = $this->postJson('/api/v1/organisation/users', [
            'name'      => 'Animateur Petit Samaritain',
            'email'     => 'animateur.' . uniqid() . '@test.ci',
            'password'  => 'AnimateurPass123!',
            'user_type' => 'utilisateur',
        ]);

        $storeUserResp->assertStatus(201);
        $userId = $storeUserResp->json('data.id');
        $userInterneId = $storeUserResp->json('data.id_interne');

        // Vérification de la liaison automatique et infalsifiable
        $userEnBase = User::find($userInterneId);
        $this->assertEquals($this->oppeA->id, $userEnBase->organisation_id);
        $this->assertEquals($this->paroisseA->id, $userEnBase->paroisse_configuration_id);

        // Toggle status
        $toggleResp = $this->patchJson("/api/v1/organisation/users/{$userId}/toggle-status");
        $toggleResp->assertStatus(200);
        $this->assertSame('inactif', $userEnBase->fresh()->statut);
    }

    public function test_super_admin_can_provision_first_organisation_responsable(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $resp = $this->postJson("/api/v1/super-admin/organisations/{$this->oppaA->id}/responsable", [
            'name'      => 'Docteur Kouamé - Responsable OPPA',
            'email'     => 'resp.oppa.' . uniqid() . '@test.ci',
            'telephone' => '0505050505',
            'password'  => 'ResponsableOppa123!',
        ]);

        $resp->assertStatus(201);
        $newUserId = $resp->json('data.id_interne');

        $newUser = User::find($newUserId);
        $this->assertSame($this->oppaA->id, $newUser->organisation_id);
        $this->assertSame($this->paroisseA->id, $newUser->paroisse_configuration_id);
        $this->assertSame('Docteur Kouamé - Responsable OPPA', $this->oppaA->fresh()->responsable_nom);
    }

    // =========================================================================
    // 7. INTÉGRATION POPULATION CATHEO PAR CODES DE SECTION STRICTS
    // =========================================================================

    public function test_catheo_population_integration_by_strict_section_codes(): void
    {
        // Créer les niveaux associés aux sections
        $nivPri = Niveau::create(['paroisse_configuration_id' => $this->paroisseA->id, 'section_id' => $this->secEnfPri->id, 'nom' => '1ère Année Primaire']);
        $nivCol = Niveau::create(['paroisse_configuration_id' => $this->paroisseA->id, 'section_id' => $this->secEnfCol->id, 'nom' => '1ère Année Collège']);
        $nivJeu = Niveau::create(['paroisse_configuration_id' => $this->paroisseA->id, 'section_id' => $this->secJeunes->id, 'nom' => '1ère Année Jeunes']);
        $nivAdu = Niveau::create(['paroisse_configuration_id' => $this->paroisseA->id, 'section_id' => $this->secAdultes->id, 'nom' => '1ère Année Adultes']);

        // Créer 4 catéchumènes inscrits dans l'année courante 2025-2026
        $catEnfPri = Catechumene::create(['paroisse_configuration_id' => $this->paroisseA->id, 'matricule' => 'MAT-PRI-01', 'nom' => 'ENFANT_PRI', 'prenoms' => 'A', 'sexe' => 'M']);
        $catEnfCol = Catechumene::create(['paroisse_configuration_id' => $this->paroisseA->id, 'matricule' => 'MAT-COL-02', 'nom' => 'ENFANT_COL', 'prenoms' => 'B', 'sexe' => 'F']);
        $catJeunes = Catechumene::create(['paroisse_configuration_id' => $this->paroisseA->id, 'matricule' => 'MAT-JEU-03', 'nom' => 'JEUNE', 'prenoms' => 'C', 'sexe' => 'M']);
        $catAdults = Catechumene::create(['paroisse_configuration_id' => $this->paroisseA->id, 'matricule' => 'MAT-ADU-04', 'nom' => 'ADULTE', 'prenoms' => 'D', 'sexe' => 'F']);

        // Inscription Année Courante
        InscriptionAnnuelle::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'catechumene_id'            => $catEnfPri->id,
            'annee_catechese_id'        => $this->anneeCouranteA->id,
            'section_id'                => $this->secEnfPri->id,
            'niveau_id'                 => $nivPri->id,
            'code_inscription'          => 'INS-001',
        ]);

        InscriptionAnnuelle::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'catechumene_id'            => $catEnfCol->id,
            'annee_catechese_id'        => $this->anneeCouranteA->id,
            'section_id'                => $this->secEnfCol->id,
            'niveau_id'                 => $nivCol->id,
            'code_inscription'          => 'INS-002',
        ]);

        InscriptionAnnuelle::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'catechumene_id'            => $catJeunes->id,
            'annee_catechese_id'        => $this->anneeCouranteA->id,
            'section_id'                => $this->secJeunes->id,
            'niveau_id'                 => $nivJeu->id,
            'code_inscription'          => 'INS-003',
        ]);

        InscriptionAnnuelle::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'catechumene_id'            => $catAdults->id,
            'annee_catechese_id'        => $this->anneeCouranteA->id,
            'section_id'                => $this->secAdultes->id,
            'niveau_id'                 => $nivAdu->id,
            'code_inscription'          => 'INS-004',
        ]);

        // Inscription dans une ANNÉE PASSÉE (2024-2025) -> Ne doit JAMAIS apparaître
        $catPasse = Catechumene::create(['paroisse_configuration_id' => $this->paroisseA->id, 'matricule' => 'MAT-OLD-99', 'nom' => 'ANCIEN_ENFANT', 'prenoms' => 'Z', 'sexe' => 'M']);
        InscriptionAnnuelle::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'catechumene_id'            => $catPasse->id,
            'annee_catechese_id'        => $this->anneePasseeA->id,
            'section_id'                => $this->secEnfPri->id,
            'niveau_id'                 => $nivPri->id,
            'code_inscription'          => 'INS-OLD-001',
        ]);

        // 1. Test OPPE : doit voir SEC-ENFANTS-PRI et SEC-ENFANTS-COL uniquement
        Sanctum::actingAs($this->userOppeA);
        $respOppe = $this->getJson('/api/v1/organisation/catheo/population');
        $respOppe->assertStatus(200);

        $nomsOppe = array_column(array_column($respOppe->json('data'), 'catechumene'), 'nom');
        $this->assertContains('ENFANT_PRI', $nomsOppe);
        $this->assertContains('ENFANT_COL', $nomsOppe);
        $this->assertNotContains('JEUNE', $nomsOppe);
        $this->assertNotContains('ADULTE', $nomsOppe);
        $this->assertNotContains('ANCIEN_ENFANT', $nomsOppe); // Année passée exclue !

        // 2. Test OPPJ : doit voir uniquement SEC-JEUNES
        Sanctum::actingAs($this->userOppjA);
        $respOppj = $this->getJson('/api/v1/organisation/catheo/population');
        $respOppj->assertStatus(200);

        $nomsOppj = array_column(array_column($respOppj->json('data'), 'catechumene'), 'nom');
        $this->assertContains('JEUNE', $nomsOppj);
        $this->assertNotContains('ENFANT_PRI', $nomsOppj);
        $this->assertNotContains('ENFANT_COL', $nomsOppj);
        $this->assertNotContains('ADULTE', $nomsOppj);

        // 3. Test OPPA : doit voir uniquement SEC-ADULTES
        Sanctum::actingAs($this->userOppaA);
        $respOppa = $this->getJson('/api/v1/organisation/catheo/population');
        $respOppa->assertStatus(200);

        $nomsOppa = array_column(array_column($respOppa->json('data'), 'catechumene'), 'nom');
        $this->assertContains('ADULTE', $nomsOppa);
        $this->assertNotContains('ENFANT_PRI', $nomsOppa);
        $this->assertNotContains('ENFANT_COL', $nomsOppa);
        $this->assertNotContains('JEUNE', $nomsOppa);
    }
}
