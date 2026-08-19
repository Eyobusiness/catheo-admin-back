<?php

namespace Tests\Feature;

use App\Models\AnneeCatechese;
use App\Models\ParoisseConfiguration;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiviteTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected ParoisseConfiguration $paroisse;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\InitialSetupSeeder::class);

        $this->adminUser = User::where('email', 'admin.stpaul@catheo.ci')->first();
        $this->paroisse = ParoisseConfiguration::where('code_paroisse', 'PAR-STPAUL-01')->first();
        $this->token = $this->adminUser->createToken('TestDevice')->plainTextToken;
    }

    public function test_can_manage_calendrier_pastoral(): void
    {
        $annee = AnneeCatechese::where('paroisse_configuration_id', $this->paroisse->id)->first();
        $section = Section::where('paroisse_configuration_id', $this->paroisse->id)->first();

        // 1. Créer un événement dans le calendrier
        $res = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/calendriers', [
                'annee_catechese_id' => $annee->uuid,
                'titre'              => 'Messe Solennelle de Rentrée des Catéchumènes',
                'type'               => 'Célébration',
                'date'               => '2026-10-06',
                'heure_debut'        => '09:00',
                'heure_fin'          => '11:30',
                'lieu'               => 'Grande Cathédrale Saint-Paul',
                'cible_type'         => 'SECTION',
                'cible_id'           => $section->uuid,
                'description'        => 'Messe de rentrée pastorale pour tous les catéchumènes de la section',
                'statut'             => 'Planifié',
            ]);

        $res->assertStatus(201)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Événement du calendrier créé avec succès.',
                'data'    => [
                    'titre'      => 'Messe Solennelle de Rentrée des Catéchumènes',
                    'type'       => 'Célébration',
                    'statut'     => 'Planifié',
                    'cible_type' => 'SECTION',
                ],
            ]);

        $calendrierUuid = $res->json('data.id');

        // 2. Récupérer la liste des événements
        $listRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/calendriers?cible_type=SECTION');
        $listRes->assertStatus(200);

        // 3. Modifier le statut de l'événement
        $statusRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->patchJson("/api/v1/calendriers/{$calendrierUuid}/status", [
                'statut' => 'Réalisé',
            ]);

        $statusRes->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => ['statut' => 'Réalisé'],
            ]);

        // 4. Supprimer l'événement
        $delRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/v1/calendriers/{$calendrierUuid}");
        $delRes->assertStatus(200);
    }
}
