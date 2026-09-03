<?php

namespace Tests\Feature;

use App\Models\CatecheseConfiguration;
use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InactivityTimeoutTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected CatecheseConfiguration $paroisse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSetupSeeder::class);

        $this->adminUser = User::where('email', 'admin.stpaul@catheo.ci')->first();
        $this->paroisse = CatecheseConfiguration::where('code_paroisse', 'PAR-STPAUL-01')->first();
    }

    /**
     * Test : Un jeton actif (utilisé il y a moins de 10 minutes) est autorisé.
     */
    public function test_active_token_within_10_minutes_is_authenticated(): void
    {
        $token = $this->adminUser->createToken('TestDevice');
        // Simuler une dernière utilisation il y a 5 minutes
        $token->accessToken->forceFill([
            'last_used_at' => now()->subMinutes(5),
            'created_at'   => now()->subMinutes(5),
        ])->save();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200);
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }

    /**
     * Test : Un jeton inactif depuis plus de 10 minutes est rejeté (401) et supprimé de la base.
     */
    public function test_inactive_token_more_than_10_minutes_is_revoked_and_rejected(): void
    {
        $token = $this->adminUser->createToken('TestDevice');
        // Simuler une dernière utilisation il y a 11 minutes
        $token->accessToken->forceFill([
            'last_used_at' => now()->subMinutes(11),
            'created_at'   => now()->subMinutes(20),
        ])->save();

        $response = $this->withHeader('Authorization', 'Bearer ' . $token->plainTextToken)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(401);

        // Le jeton doit avoir été supprimé pour invalidation de session
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }
}
