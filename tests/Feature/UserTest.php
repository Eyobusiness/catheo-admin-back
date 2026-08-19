<?php

namespace Tests\Feature;

use App\Models\ParoisseConfiguration;
use App\Models\Profil;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $paroisseAdmin;
    protected Profil $profilCatechiste;
    protected ParoisseConfiguration $paroisse;
    protected string $superAdminToken;
    protected string $paroisseAdminToken;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\InitialSetupSeeder::class);

        $this->superAdmin = User::where('email', 'admin@gmail.com')->first();
        $this->superAdminToken = $this->superAdmin->createToken('SuperAdminDevice')->plainTextToken;

        $this->paroisseAdmin = User::where('email', 'admin.stpaul@catheo.ci')->first();
        $this->paroisseAdminToken = $this->paroisseAdmin->createToken('ParoisseAdminDevice')->plainTextToken;

        $this->profilCatechiste = Profil::where('code', 'CATECHISTE')->first();
        $this->paroisse = ParoisseConfiguration::where('code_paroisse', 'PAR-STPAUL-01')->first();
    }

    /**
     * Test de récupération de la liste des utilisateurs paginée.
     */
    public function test_authenticated_user_can_list_users(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->getJson('/api/v1/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'data' => [
                        '*' => ['id', 'name', 'email', 'telephone', 'statut'],
                    ],
                ],
            ]);
    }

    /**
     * Test de création d'un utilisateur.
     */
    public function test_authenticated_user_can_create_user(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->paroisseAdminToken)
            ->postJson('/api/v1/users', [
                'name' => 'Jean-Marc Catechiste',
                'email' => 'jeanmarc@gmail.com',
                'telephone' => '+225 0707070707',
                'password' => '12345678',
                'profil_id' => $this->profilCatechiste->uuid,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'message' => 'Utilisateur créé avec succès.',
                'data' => [
                    'name' => 'Jean-Marc Catechiste',
                    'email' => 'jeanmarc@gmail.com',
                ],
            ]);

        $this->assertDatabaseHas('users', ['email' => 'jeanmarc@gmail.com']);
    }

    /**
     * Test de mise à jour du statut d'un utilisateur (PATCH).
     */
    public function test_user_status_can_be_updated(): void
    {
        $targetUser = User::where('email', 'admin.stpaul@catheo.ci')->first();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->superAdminToken)
            ->patchJson('/api/v1/users/' . $targetUser->uuid . '/status', [
                'statut' => 'suspendu',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'statut' => 'suspendu',
                ],
            ]);

        $this->assertEquals('suspendu', $targetUser->fresh()->statut);
    }

    /**
     * Test de suppression d'un utilisateur (SoftDelete).
     */
    public function test_user_can_be_deleted(): void
    {
        $targetUser = User::factory()->create([
            'uuid' => (string) \Illuminate\Support\Str::uuid(),
            'name' => 'User To Delete',
            'email' => 'todelete@gmail.com',
            'password' => bcrypt('password'),
            'profil_id' => $this->profilCatechiste->id,
            'paroisse_configuration_id' => $this->paroisse->id,
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->paroisseAdminToken)
            ->deleteJson('/api/v1/users/' . $targetUser->uuid);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Utilisateur supprimé avec succès.',
            ]);

        $this->assertSoftDeleted('users', ['id' => $targetUser->id]);
    }
}
