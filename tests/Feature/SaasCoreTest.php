<?php

namespace Tests\Feature;

use App\Models\CatecheseConfiguration;
use App\Models\Organisation;
use App\Models\Produit;
use App\Models\Profil;
use App\Models\User;
use App\Services\SecurityContextService;
use Database\Seeders\ProduitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Tests\TestCase;

class SaasCoreTest extends TestCase
{
    use RefreshDatabase;

    protected CatecheseConfiguration $paroisse;
    protected CatecheseConfiguration $autreParoisse;
    protected Produit $produitOppe;
    protected Produit $produitOppj;
    protected Produit $produitOppa;
    protected SecurityContextService $securityService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->securityService = new SecurityContextService();

        // Créer ou récupérer les produits
        $this->seed(ProduitSeeder::class);
        $this->produitOppe = Produit::findByCode(Produit::CODE_OPPE);
        $this->produitOppj = Produit::findByCode(Produit::CODE_OPPJ);
        $this->produitOppa = Produit::findByCode(Produit::CODE_OPPA);

        // Créer deux paroisses pour les tests multi-tenants
        $this->paroisse = CatecheseConfiguration::create([
            'nom_paroisse'     => 'Paroisse Test Étape 1',
            'code_paroisse'    => 'PAR-TEST-E1-' . uniqid(),
            'prefixe_matricule'=> 'P1',
            'prefixe_recu'     => 'R1',
            'statut'           => 'actif',
        ]);

        $this->autreParoisse = CatecheseConfiguration::create([
            'nom_paroisse'     => 'Autre Paroisse B',
            'code_paroisse'    => 'PAR-TEST-E1B-' . uniqid(),
            'prefixe_matricule'=> 'P2',
            'prefixe_recu'     => 'R2',
            'statut'           => 'actif',
        ]);
    }

    /**
     * 1. Les 4 produits peuvent être créés par le Seeder.
     */
    public function test_seeder_creates_four_products(): void
    {
        $this->assertDatabaseHas('produits', ['code' => Produit::CODE_CATHEO]);
        $this->assertDatabaseHas('produits', ['code' => Produit::CODE_OPPE]);
        $this->assertDatabaseHas('produits', ['code' => Produit::CODE_OPPJ]);
        $this->assertDatabaseHas('produits', ['code' => Produit::CODE_OPPA]);
    }

    /**
     * 2. Le Seeder peut être exécuté plusieurs fois sans doublon (idempotence).
     */
    public function test_seeder_is_idempotent_no_duplicates(): void
    {
        $countBefore = Produit::count();

        $this->seed(ProduitSeeder::class);
        $this->seed(ProduitSeeder::class);

        $countAfter = Produit::count();
        $this->assertSame($countBefore, $countAfter);
    }

    /**
     * 3. Une organisation peut être créée pour une paroisse.
     */
    public function test_organisation_can_be_created_for_a_paroisse(): void
    {
        $org = Organisation::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'produit_id'                => $this->produitOppe->id,
            'type_organisation'         => Organisation::TYPE_OPPE,
            'code'                      => 'OPPE-TEST',
            'nom'                       => 'Enfance Sainte Thérèse',
            'statut'                    => 'actif',
        ]);

        $this->assertDatabaseHas('organisations', [
            'id'                        => $org->id,
            'paroisse_configuration_id' => $this->paroisse->id,
            'type_organisation'         => 'OPPE',
            'nom'                       => 'Enfance Sainte Thérèse',
        ]);
    }

    /**
     * 4 & 5. Une organisation possède bien un produit et une paroisse.
     */
    public function test_organisation_belongs_to_produit_and_paroisse(): void
    {
        $org = Organisation::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'produit_id'                => $this->produitOppj->id,
            'type_organisation'         => Organisation::TYPE_OPPJ,
            'nom'                       => 'Jeunesse En Marche',
        ]);

        $this->assertInstanceOf(Produit::class, $org->produit);
        $this->assertSame(Produit::CODE_OPPJ, $org->produit->code);

        $this->assertInstanceOf(CatecheseConfiguration::class, $org->paroisse);
        $this->assertSame($this->paroisse->id, $org->paroisse->id);

        $this->assertTrue($this->paroisse->organisations->contains('id', $org->id));
    }

    /**
     * 6. Deux OPPE ne peuvent pas être créées pour la même paroisse.
     */
    public function test_cannot_create_two_organisations_of_same_type_for_same_paroisse(): void
    {
        Organisation::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'produit_id'                => $this->produitOppe->id,
            'type_organisation'         => Organisation::TYPE_OPPE,
            'nom'                       => 'Première OPPE',
        ]);

        $this->expectException(InvalidArgumentException::class);

        Organisation::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'produit_id'                => $this->produitOppe->id,
            'type_organisation'         => Organisation::TYPE_OPPE,
            'nom'                       => 'Seconde OPPE (Doit échouer)',
        ]);
    }

    /**
     * 7. Une paroisse peut avoir OPPE + OPPJ + OPPA simultanément.
     */
    public function test_paroisse_can_have_oppe_oppj_oppa_simultaneously(): void
    {
        $oppe = Organisation::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'produit_id'                => $this->produitOppe->id,
            'type_organisation'         => Organisation::TYPE_OPPE,
            'nom'                       => 'Branche Enfants',
        ]);

        $oppj = Organisation::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'produit_id'                => $this->produitOppj->id,
            'type_organisation'         => Organisation::TYPE_OPPJ,
            'nom'                       => 'Branche Jeunes',
        ]);

        $oppa = Organisation::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'produit_id'                => $this->produitOppa->id,
            'type_organisation'         => Organisation::TYPE_OPPA,
            'nom'                       => 'Branche Adultes',
        ]);

        $this->assertCount(3, $this->paroisse->organisations()->get());
        $this->assertSame(Organisation::TYPE_OPPE, $oppe->type_organisation);
        $this->assertSame(Organisation::TYPE_OPPJ, $oppj->type_organisation);
        $this->assertSame(Organisation::TYPE_OPPA, $oppa->type_organisation);
    }

    /**
     * Test unicité compatible avec SoftDeletes :
     * Une organisation soft-deletee libère le slot pour une nouvelle création.
     */
    public function test_soft_deleted_organisation_allows_recreation_of_same_type(): void
    {
        $org1 = Organisation::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'produit_id'                => $this->produitOppe->id,
            'type_organisation'         => Organisation::TYPE_OPPE,
            'nom'                       => 'Ancienne OPPE',
        ]);

        // Suppression logique
        $org1->delete();
        $this->assertSoftDeleted('organisations', ['id' => $org1->id]);

        // Recréation autorisée sans duplicate error
        $org2 = Organisation::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'produit_id'                => $this->produitOppe->id,
            'type_organisation'         => Organisation::TYPE_OPPE,
            'nom'                       => 'Nouvelle OPPE Réactivée',
        ]);

        $this->assertDatabaseHas('organisations', [
            'id'  => $org2->id,
            'nom' => 'Nouvelle OPPE Réactivée',
        ]);
    }

    /**
     * 8. Un utilisateur existant continue à fonctionner avec organisation_id = NULL.
     */
    public function test_existing_user_works_with_organisation_id_null(): void
    {
        $user = User::create([
            'name'                      => 'Admin Catheo Existant',
            'email'                     => 'admin.catheo.' . uniqid() . '@test.ci',
            'password'                  => Hash::make('Secret123!'),
            'paroisse_configuration_id' => $this->paroisse->id,
            'organisation_id'           => null,
            'user_type'                 => 'admin',
            'statut'                    => 'actif',
        ]);

        $this->assertNull($user->organisation_id);
        $this->assertNull($user->organisation);
        $this->assertTrue($user->isParoisseAdmin());
        $this->assertFalse($user->isOrganisationUser());

        $context = $this->securityService->getContext($user);
        $this->assertSame($this->paroisse->id, $context['paroisse_id']);
        $this->assertNull($context['organisation_id']);
        $this->assertSame(Produit::CODE_CATHEO, $context['espace']);
    }

    /**
     * 9. Un utilisateur peut être lié à une organisation valide.
     */
    public function test_user_can_be_linked_to_valid_organisation(): void
    {
        $org = Organisation::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'produit_id'                => $this->produitOppj->id,
            'type_organisation'         => Organisation::TYPE_OPPJ,
            'nom'                       => 'OPPJ Paroissiale',
        ]);

        $user = User::create([
            'name'                      => 'Responsable OPPJ',
            'email'                     => 'resp.oppj.' . uniqid() . '@test.ci',
            'password'                  => Hash::make('Secret123!'),
            'paroisse_configuration_id' => $this->paroisse->id,
            'organisation_id'           => $org->id,
            'user_type'                 => 'admin',
            'statut'                    => 'actif',
        ]);

        $this->assertInstanceOf(Organisation::class, $user->organisation);
        $this->assertSame($org->id, $user->organisation->id);
        $this->assertTrue($user->isOrganisationUser());

        $context = $this->securityService->getContext($user);
        $this->assertSame($this->paroisse->id, $context['paroisse_id']);
        $this->assertSame($org->id, $context['organisation_id']);
        $this->assertSame('OPPJ', $context['espace']);
    }

    /**
     * 10. Une organisation appartenant à une autre paroisse ne peut pas être associée
     * à un utilisateur d'une autre paroisse (détection et rejet de cohérence).
     */
    public function test_security_context_detects_user_organisation_mismatch(): void
    {
        // Organisation créée sur paroisse B
        $orgParoisseB = Organisation::create([
            'paroisse_configuration_id' => $this->autreParoisse->id,
            'produit_id'                => $this->produitOppa->id,
            'type_organisation'         => Organisation::TYPE_OPPA,
            'nom'                       => 'OPPA Paroisse B',
        ]);

        // Utilisateur appartenant à la paroisse A mais pointant frauduleusement vers l'organisation B
        $userA = User::create([
            'name'                      => 'Utilisateur A Frauduleux',
            'email'                     => 'user.fraud.' . uniqid() . '@test.ci',
            'password'                  => Hash::make('Secret123!'),
            'paroisse_configuration_id' => $this->paroisse->id,
            'organisation_id'           => $orgParoisseB->id,
            'user_type'                 => 'admin',
            'statut'                    => 'actif',
        ]);

        $this->expectException(AccessDeniedHttpException::class);
        $this->securityService->getContext($userA);
    }

    /**
     * 11. Le Super Admin reste fonctionnel avec paroisse_configuration_id = NULL et organisation_id = NULL.
     */
    public function test_super_admin_remains_functional_with_null_tenants(): void
    {
        $superAdmin = User::create([
            'name'                      => 'Super Admin Plateforme Test',
            'email'                     => 'superadmin.' . uniqid() . '@catheo.ci',
            'password'                  => Hash::make('SuperAdmin123!'),
            'paroisse_configuration_id' => null,
            'organisation_id'           => null,
            'user_type'                 => 'super_admin',
            'statut'                    => 'actif',
        ]);

        $this->assertTrue($superAdmin->isSuperAdmin());

        $context = $this->securityService->getContext($superAdmin);
        $this->assertTrue($context['is_super_admin']);
        $this->assertNull($context['paroisse_id']);
        $this->assertNull($context['organisation_id']);
        $this->assertSame('SUPER_ADMIN', $context['espace']);
    }

    /**
     * 12. L'authentification CATHEO existante reste fonctionnelle.
     */
    public function test_catheo_authentication_remains_functional(): void
    {
        $email = 'admin.catheo.auth.' . uniqid() . '@test.ci';
        $user = User::create([
            'name'                      => 'Admin Authentification',
            'email'                     => $email,
            'password'                  => Hash::make('MotDePasse123!'),
            'paroisse_configuration_id' => $this->paroisse->id,
            'organisation_id'           => null,
            'user_type'                 => 'admin',
            'statut'                    => 'actif',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login'    => $email,
            'password' => 'MotDePasse123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Connexion réussie.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'token_type',
                    'user' => [
                        'id',
                        'email',
                    ],
                ],
            ]);
    }

    /**
     * 13. Vérification que les requêtes falsifiées Angular (injection de paroisse_configuration_id)
     * sont systématiquement ignorées pour les utilisateurs normaux.
     */
    public function test_security_context_ignores_forged_angular_parameters_for_normal_users(): void
    {
        $user = User::create([
            'name'                      => 'Utilisateur Standard',
            'email'                     => 'user.standard.' . uniqid() . '@test.ci',
            'password'                  => Hash::make('Secret123!'),
            'paroisse_configuration_id' => $this->paroisse->id,
            'organisation_id'           => null,
            'user_type'                 => 'admin',
            'statut'                    => 'actif',
        ]);

        // Simulation d'une requête Angular forgée envoyant une autre paroisse
        $request = Request::create('/api/v1/test', 'GET', [
            'paroisse_configuration_id' => $this->autreParoisse->id,
            'paroisse_id'               => $this->autreParoisse->id,
        ]);
        $request->headers->set('X-Paroisse-Id', (string) $this->autreParoisse->id);

        $context = $this->securityService->getContext($user, $request);

        // La paroisse résolue DOIT être celle de l'utilisateur, et NON celle injectée dans la requête
        $this->assertSame($this->paroisse->id, $context['paroisse_id']);
        $this->assertNotSame($this->autreParoisse->id, $context['paroisse_id']);
    }
}
