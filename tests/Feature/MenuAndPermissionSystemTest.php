<?php

namespace Tests\Feature;

use App\Models\Menu;
use App\Models\CatecheseConfiguration;
use App\Models\Profil;
use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Database\Seeders\FakeDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuAndPermissionSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSetupSeeder::class);
    }

    /**
     * Teste que les 13 menus officiels sont bien insérés en base.
     */
    public function test_all_13_official_menus_are_seeded(): void
    {
        $rootMenus = Menu::roots()->get();
        $this->assertCount(13, $rootMenus);

        $expectedReferences = [
            'dashboard',
            'main_catechumenes',
            'main_presences',
            'main_evaluations',
            'main_sacrements',
            'main_finances',
            'main_communication',
            'main_impressions',
            'main_documents',
            'main_rapports',
            'main_organisation',
            'main_users_security',
            'main_settings',
        ];

        foreach ($expectedReferences as $ref) {
            $this->assertTrue(
                $rootMenus->contains('reference', $ref),
                "Le menu racine avec la référence [{$ref}] est manquant."
            );
        }
    }

    /**
     * Teste que les 7 profils réalistes sont créés avec leurs relations de permissions.
     */
    public function test_all_7_realistic_profiles_are_seeded(): void
    {
        $expectedProfiles = [
            'SUPER_ADMIN',
            'ADMIN_PAROISSE',
            'SECRETAIRE',
            'RESPONSABLE_CATECHESE',
            'ANIMATEUR',
            'COMPTABLE',
            'LECTEUR',
        ];

        foreach ($expectedProfiles as $code) {
            $profil = Profil::where('code', $code)->first();
            $this->assertNotNull($profil, "Le profil [{$code}] est introuvable.");
            $this->assertGreaterThan(0, $profil->menuPermissions()->count(), "Le profil [{$code}] n'a aucune permission liée.");
        }
    }

    /**
     * Teste l'endpoint GET /api/v1/menus pour récupérer le catalogue des menus.
     */
    public function test_authenticated_user_can_list_all_menus(): void
    {
        $user = User::where('email', 'admin.stpaul@catheo.ci')->first();

        $response = $this->actingAs($user)->getJson('/api/v1/menus');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'id',
                        'uuid',
                        'libelle',
                        'icon',
                        'path',
                        'reference',
                        'ordre',
                        'is_active',
                        'sousMenus',
                    ]
                ]
            ]);

        $data = $response->json('data');
        $this->assertCount(13, $data);
    }

    /**
     * Teste que GET /api/v1/auth/me renvoie les menus autorisés et la matrice de droits.
     */
    public function test_auth_me_returns_accessible_menus_and_permissions(): void
    {
        $superAdmin = User::where('email', 'superadmin@catheo.ci')->first();

        $response = $this->actingAs($superAdmin)->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'user' => [
                        'id',
                        'uuid',
                        'name',
                        'email',
                        'profil',
                    ],
                    'menus' => [
                        '*' => [
                            'uuid',
                            'libelle',
                            'path',
                            'reference',
                            'permissions' => [
                                'create',
                                'read',
                                'update',
                                'delete',
                                'restore',
                                'force_delete',
                            ],
                            'sousMenus',
                        ]
                    ]
                ]
            ]);

        $menus = $response->json('data.menus');
        $this->assertNotEmpty($menus);
        // Super admin possède tous les droits
        $this->assertTrue($menus[0]['permissions']['create']);
        $this->assertTrue($menus[0]['permissions']['read']);
        $this->assertTrue($menus[0]['permissions']['force_delete']);
    }

    /**
     * Teste le contrôle de permissions fines pour un profil Comptable vs Secrétaire.
     */
    public function test_granular_permissions_for_comptable_and_secretaire(): void
    {
        $comptable = User::where('email', 'comptable@catheo.ci')->first();
        $secretaire = User::where('email', 'secretaire@catheo.ci')->first();

        // Le comptable doit avoir accès aux finances
        $this->assertTrue($comptable->hasPermission('finances.view'));
        $this->assertTrue($comptable->hasPermission('finances.create'));

        // Le comptable ne doit pas pouvoir supprimer les catéchumènes
        $this->assertFalse($comptable->hasPermission('catechumenes.delete'));

        // La secrétaire doit pouvoir gérer les catéchumènes
        $this->assertTrue($secretaire->hasPermission('catechumenes.view'));
        $this->assertTrue($secretaire->hasPermission('catechumenes.create'));

        // La secrétaire ne doit pas avoir accès aux finances
        $this->assertFalse($secretaire->hasPermission('finances.view'));
    }

    /**
     * Teste l'arbre des permissions dans ProfilController::permissionsTree.
     */
    public function test_permissions_tree_endpoint(): void
    {
        $admin = User::where('email', 'admin.stpaul@catheo.ci')->first();

        $response = $this->actingAs($admin)->getJson('/api/v1/profils/permissions-tree');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'uuid',
                        'menu',
                        'reference',
                        'actions',
                        'sousMenus',
                    ]
                ]
            ]);

        $data = $response->json('data');
        $this->assertCount(13, $data);
    }

    /**
     * Teste l'exécution complète de FakeDataSeeder sans erreur relationnelle.
     */
    public function test_fake_data_seeder_executes_successfully(): void
    {
        $this->seed(FakeDataSeeder::class);

        $this->assertDatabaseHas('catechumenes', [
            'nom' => 'KOUADIO',
            'prenoms' => 'Ferdinand',
        ]);

        $this->assertDatabaseHas('classes', [
            'nom' => 'Initiation 1 - Groupe Saint-Joseph',
        ]);

        $this->assertDatabaseHas('seances', [
            'titre' => 'Dieu Créateur et Père de Miséricorde',
        ]);

        $this->assertDatabaseHas('caisse_paroissiale', [
            'libelle' => 'Encaissement des inscriptions - Semaine 1',
        ]);
    }
}
