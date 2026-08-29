<?php

namespace Tests\Feature;

use App\Models\Animateur;
use App\Models\Catechumene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthMultiActorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\InitialSetupSeeder::class);
    }

    /**
     * Test de connexion Super Admin et Admin Paroissial par Email (Mot de passe: 12345678).
     */
    public function test_admin_can_login_with_email_and_default_password(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'admin.stpaul@catheo.ci',
            'password' => '12345678',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'user_type' => 'admin',
                ],
            ])
            ->assertJsonStructure([
                'data' => ['token', 'user' => ['id', 'name', 'email', 'profil']],
            ]);
    }

    /**
     * Test de connexion Animateur par numéro de téléphone pour l'application mobile (Mot de passe: 12345678).
     */
    public function test_animateur_can_login_with_phone_number_and_default_password(): void
    {
        $animateur = Animateur::first();
        $this->assertNotNull($animateur->telephone);

        $response = $this->postJson('/api/v1/auth/animateurs/login', [
            'login' => $animateur->telephone,
            'password' => '12345678',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'user_type' => 'animateur',
                ],
            ])
            ->assertJsonStructure([
                'data' => ['token', 'user' => ['id', 'nom', 'telephone']],
            ]);
    }

    /**
     * Test de connexion Parent par Matricule Catéchumène pour l'application mobile (Mot de passe: 12345678).
     */
    public function test_parent_can_login_with_catechumene_matricule_and_default_password(): void
    {
        $catechumene = Catechumene::first();
        $this->assertNotNull($catechumene);
        $catechumene->update(['password' => '12345678']);

        $response = $this->postJson('/api/v1/auth/parents/login', [
            'login' => $catechumene->matricule,
            'password' => '12345678',
        ]);


        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'user_type' => 'parent',
                ],
            ])
            ->assertJsonStructure([
                'data' => ['token', 'user' => ['id', 'nom', 'matricule']],
            ]);
    }

    /**
     * Test du tableau de bord spécifique Animateur.
     */
    public function test_animateur_can_access_own_dashboard(): void
    {
        $animateur = Animateur::first();
        $token = $animateur->createToken('MobileApp')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/dashboard/animateur');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'user_type' => 'animateur',
            ])
            ->assertJsonStructure([
                'data' => ['animateur', 'kpis', 'classes'],
            ]);
    }


    /**
     * Test du tableau de bord spécifique Parent.
     */
    public function test_parent_can_access_own_dashboard(): void
    {
        $user = User::where('user_type', 'parent')->first();
        $token = $user->createToken('MobileApp')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/dashboard/summary');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'user_type' => 'parent',
            ])
            ->assertJsonStructure([
                'data' => ['enfant', 'inscription', 'suivi_scolaire', 'suivi_presences', 'situation_financiere'],
            ]);
    }

    /**
     * Test du tableau de bord Super Admin / Concepteur SaaS (Vue globale multi-paroisses).
     */
    public function test_super_admin_can_access_global_platform_dashboard(): void
    {
        $user = User::where('email', 'admin@gmail.com')->first();
        $token = $user->createToken('AdminApp')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/dashboard/summary');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'user_type' => 'super_admin',
            ])
            ->assertJsonStructure([
                'data' => [
                    'kpis' => [
                        'total_paroisses',
                        'total_paroisses_actives',
                        'total_catechumenes_global',
                        'total_animateurs_global',
                        'total_utilisateurs_global',
                        'volume_financier_global',
                    ],
                    'paroisses',
                ],
            ]);
    }
}
