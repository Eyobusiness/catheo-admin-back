<?php

namespace Tests\Feature;

use App\Models\Profil;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfilTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\InitialSetupSeeder::class);

        $this->adminUser = User::where('email', 'admin@gmail.com')->first();
        $this->token = $this->adminUser->createToken('TestDevice')->plainTextToken;
    }

    /**
     * Test d'obtention de la liste des profils.
     */
    public function test_authenticated_user_can_get_profils_list(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/profils');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => ['id', 'nom', 'code', 'description', 'statut', 'statut_code', 'permissions', 'total_permissions', 'is_system'],
                ],
            ]);

        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    /**
     * Test d'obtention de l'arbre complet des permissions par menu.
     */
    public function test_authenticated_user_can_get_permissions_tree(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/profils/permissions-tree');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => ['menu', 'code', 'total_actions', 'permissions']
                ]
            ]);
    }

    /**
     * Test de création d'un profil sur-mesure.
     */
    public function test_authenticated_user_can_create_custom_profil(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/profils', [
                'nom' => 'Secrétaire Général',
                'code' => 'SECRETAIRE_GENERAL',
                'description' => 'Gestion du courrier et enregistrement des inscriptions',
                'statut' => 'actif',
                'permissions' => ['catechumenes.view', 'catechumenes.create'],
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'message' => 'Profil créé avec succès.',
                'data' => [
                    'nom' => 'Secrétaire Général',
                    'code' => 'SECRETAIRE_GENERAL',
                    'statut' => 'Actif',
                    'total_permissions' => 2,
                ],
            ]);

        $this->assertDatabaseHas('profils', ['code' => 'SECRETAIRE_GENERAL']);
    }

    /**
     * Test de bascule de statut d'un profil personnalise.
     */
    public function test_authenticated_user_can_toggle_profil_status(): void
    {
        $profil = Profil::create([
            'nom' => 'Comptable Adjoint',
            'code' => 'COMPTABLE_ADJOINT',
            'statut' => 'actif',
            'permissions' => ['finances.view'],
            'is_system' => false,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->patchJson('/api/v1/profils/' . $profil->uuid . '/status');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'statut' => 'Inactif',
                    'statut_code' => 'inactif',
                ],
            ]);

        $this->assertEquals('inactif', $profil->fresh()->statut);
    }

    /**
     * Test de consultation d'un profil via son ID public.
     */
    public function test_authenticated_user_can_get_single_profil_by_id(): void
    {
        $profil = Profil::where('code', 'ADMIN_PAROISSE')->first();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/profils/' . $profil->uuid);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'id' => $profil->uuid,
                    'code' => 'ADMIN_PAROISSE',
                ],
            ]);
    }

    /**
     * Test de suppression d'un profil système interdite (403 Forbidden).
     */
    public function test_cannot_delete_system_profil(): void
    {
        $systemProfil = Profil::where('code', 'SUPER_ADMIN')->first();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/v1/profils/' . $systemProfil->uuid);

        $response->assertStatus(403)
            ->assertJson([
                'status' => 'error',
                'message' => 'Les profils système ne peuvent pas être supprimés.',
            ]);
    }
}
