<?php

namespace Tests\Feature;

use App\Models\CatecheseConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpressionTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected CatecheseConfiguration $paroisse;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\InitialSetupSeeder::class);

        $this->adminUser = User::where('email', 'admin.stpaul@catheo.ci')->first();
        $this->paroisse = CatecheseConfiguration::where('code_paroisse', 'PAR-STPAUL-01')->first();
        $this->token = $this->adminUser->createToken('TestDevice')->plainTextToken;
    }

    public function test_can_get_paroisse_print_header(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/impressions/entete');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.nom', 'Paroisse Cathédrale Saint-Paul');
    }

    public function test_can_generate_fiche_de_notes_print_data(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/impressions/fiche-notes');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('document.titre', 'FICHE DE NOTES');
    }

    public function test_can_generate_suivi_sacramental_print_data(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/impressions/suivi-sacramental', [
                'sacrament' => 'Première Communion',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('document.sacrament', 'PREMIÈRE COMMUNION');
    }

    public function test_can_generate_liste_presence_print_data(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/impressions/liste-presence', [
                'jour' => 'Samedi',
                'nombre_seances' => 12,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('document.titre', 'LISTE DE PRÉSENCE');
    }

    public function test_can_generate_fiche_bilan_annuel_print_data(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/impressions/fiche-bilan-annuel');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('document.titre', 'FICHE DE BILAN ANNUEL');
    }

    public function test_can_generate_fiche_renseignement_bapteme_print_data(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/impressions/fiche-renseignement-bapteme');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('document.titre', 'FICHE DE RENSEIGNEMENTS SACREMENT DE BAPTÊME');
    }

    public function test_can_generate_fiche_renseignement_premiere_communion_print_data(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/impressions/fiche-renseignement-premiere-communion');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('document.titre', 'FICHE DE RENSEIGNEMENT PREMIERE COMMUNION');
    }

    public function test_can_generate_fiche_renseignement_confirmation_print_data(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/impressions/fiche-renseignement-confirmation');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('document.titre', 'FICHE DE RENSEIGNEMENT CONFIRMATION');
    }
}
