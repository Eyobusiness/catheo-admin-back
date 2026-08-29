<?php

namespace Tests\Feature;

use App\Models\CatecheseConfiguration;
use App\Models\Profil;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\InitialSetupSeeder::class);
    }

    /**
     * Test de l'endpoint health de l'API v1.
     */
    public function test_api_health_check_returns_success(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Catheo API v1 is running',
            ]);
    }

    /**
     * Test de la connexion avec des identifiants valides.
     */
    public function test_super_admin_can_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin@gmail.com',
            'password' => '12345678',
            'device_name' => 'Angular_Test_App',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'telephone',
                        'statut',
                        'profil' => ['id', 'nom', 'code', 'permissions'],
                    ],
                ],
            ]);

        $this->assertNotNull($response->json('data.token'));
        $this->assertNotNull($response->json('data.user.id'));
        $this->assertEquals('admin@gmail.com', $response->json('data.user.email'));
    }

    public function test_paroisse_admin_can_login_with_valid_credentials(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin.stpaul@catheo.ci',
            'password' => '12345678',
            'device_name' => 'Angular_Test_App',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'paroisse' => ['id', 'nom', 'code_paroisse'],
                        'profil' => ['id', 'nom', 'code', 'permissions'],
                    ],
                ],
            ]);

        $this->assertNotNull($response->json('data.token'));
    }

    /**
     * Test de la connexion avec un mot de passe incorrect.
     */
    public function test_user_cannot_login_with_invalid_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin.stpaul@catheo.ci',
            'password' => 'BadPassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test de la déconnexion et de la révocation du token.
     */
    public function test_authenticated_user_can_logout(): void
    {
        $user = User::where('email', 'admin.stpaul@catheo.ci')->first();
        $token = $user->createToken('TestDevice')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/logout');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Déconnexion réussie.',
            ]);

        $this->assertCount(0, $user->fresh()->tokens);
    }

    /**
     * Test de la récupération de /me par l'utilisateur connecté.
     */
    public function test_authenticated_user_can_get_current_profile(): void
    {
        $user = User::where('email', 'admin.stpaul@catheo.ci')->first();
        $token = $user->createToken('TestDevice')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'user' => [
                        'email' => 'admin.stpaul@catheo.ci',
                        'paroisse' => [
                            'code_paroisse' => 'PAR-STPAUL-01',
                        ],
                        'profil' => [
                            'code' => 'ADMIN_PAROISSE',
                        ],
                    ],
                ],
            ]);
    }

    /**
     * Test du rafraîchissement (rotation) de token.
     */
    public function test_authenticated_user_can_refresh_token(): void
    {
        $user = User::where('email', 'admin.stpaul@catheo.ci')->first();
        $oldToken = $user->createToken('OldDevice')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $oldToken)
            ->postJson('/api/v1/auth/refresh');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'token',
                    'token_type',
                    'user',
                    'menus',
                ],
            ]);

        $newToken = $response->json('data.token');
        $this->assertNotEmpty($newToken);
        $this->assertNotEquals($oldToken, $newToken);
    }

    /**
     * Test du changement de mot de passe utilisateur.
     */
    public function test_authenticated_user_can_change_password(): void
    {
        $user = User::where('email', 'admin.stpaul@catheo.ci')->first();
        $token = $user->createToken('Device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/change-password', [
                'current_password'      => '12345678',
                'password'              => 'NewPassword2026!',
                'password_confirmation' => 'NewPassword2026!',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Mot de passe modifié avec succès.',
            ]);

        // Vérification de la connexion avec le nouveau mot de passe
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email'    => 'admin.stpaul@catheo.ci',
            'password' => 'NewPassword2026!',
        ]);
        $loginResponse->assertStatus(200);
    }

    public function test_user_cannot_change_password_with_incorrect_current_password(): void
    {
        $user = User::where('email', 'admin.stpaul@catheo.ci')->first();
        $token = $user->createToken('Device')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/auth/change-password', [
                'current_password'      => 'WrongPassword',
                'password'              => 'NewPassword2026!',
                'password_confirmation' => 'NewPassword2026!',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['current_password']);
    }

    /**
     * Test de la demande de réinitialisation avec code OTP 6 chiffres (Forgot Password).
     */
    public function test_user_can_request_forgot_password_otp_code(): void
    {
        \Illuminate\Support\Facades\Mail::fake();

        $response = $this->postJson('/api/v1/auth/forgot-password', [
            'email' => 'admin.stpaul@catheo.ci',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Un code de réinitialisation à 6 chiffres a été envoyé à votre adresse email.',
                'data'    => [
                    'email' => 'admin.stpaul@catheo.ci',
                    'expires_in_minutes' => 15,
                ],
            ]);

        \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\PasswordResetCodeMail::class);
        $this->assertDatabaseHas('password_reset_tokens', [
            'email' => 'admin.stpaul@catheo.ci',
        ]);
    }

    /**
     * Test de vérification du code OTP (Verify Code).
     */
    public function test_user_can_verify_valid_reset_code(): void
    {
        $code = '123456';
        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => 'admin.stpaul@catheo.ci'],
            [
                'token'      => \Illuminate\Support\Facades\Hash::make($code),
                'created_at' => now(),
            ]
        );

        $response = $this->postJson('/api/v1/auth/verify-code', [
            'email' => 'admin.stpaul@catheo.ci',
            'code'  => $code,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Code de réinitialisation valide.',
                'data'    => [
                    'email'      => 'admin.stpaul@catheo.ci',
                    'code_valid' => true,
                ],
            ]);
    }

    /**
     * Test de réinitialisation définitive du mot de passe (Reset Password + Auto-Login).
     */
    public function test_user_can_reset_password_with_valid_code_and_get_authenticated(): void
    {
        $code = '654321';
        \Illuminate\Support\Facades\DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => 'admin.stpaul@catheo.ci'],
            [
                'token'      => \Illuminate\Support\Facades\Hash::make($code),
                'created_at' => now(),
            ]
        );

        $response = $this->postJson('/api/v1/auth/reset-password', [
            'email'                 => 'admin.stpaul@catheo.ci',
            'code'                  => $code,
            'password'              => 'BrandNewPassword123!',
            'password_confirmation' => 'BrandNewPassword123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Votre mot de passe a été réinitialisé avec succès.',
            ])
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'token_type',
                    'user',
                    'menus',
                ],
            ]);

        $this->assertDatabaseMissing('password_reset_tokens', [
            'email' => 'admin.stpaul@catheo.ci',
        ]);

        // Connexion avec le nouveau mot de passe
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email'    => 'admin.stpaul@catheo.ci',
            'password' => 'BrandNewPassword123!',
        ]);
        $loginResponse->assertStatus(200);
    }
}
