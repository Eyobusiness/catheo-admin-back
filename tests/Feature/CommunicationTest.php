<?php

namespace Tests\Feature;

use App\Models\AnneeCatechese;
use App\Models\Annonce;
use App\Models\AuditLog;
use App\Models\NotificationLog;
use App\Models\ParoisseConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationTest extends TestCase
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

    /**
     * Test de publication et de consultation des annonces ciblées.
     */
    public function test_can_create_and_list_annonces(): void
    {
        $annee = AnneeCatechese::where('libelle', '2024-2025')->first();

        // 1. Publier une annonce ciblée vers les parents
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/annonces', [
                'annee_catechese_id' => $annee->uuid,
                'titre' => 'Réunion d\'information des parents de 1ère année',
                'contenu' => 'Chers parents, une réunion aura lieu le samedi 15 octobre à 15h.',
                'cible' => 'parents',
                'date_publication' => '2024-10-01',
                'statut' => 'publiee',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'titre' => 'Réunion d\'information des parents de 1ère année',
                    'cible' => 'parents',
                ],
            ]);

        // 2. Lister les annonces de la paroisse
        $listResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/annonces?cible=parents');

        $listResponse->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    /**
     * Test de traçabilité des envois de notifications (SMS / Email).
     */
    public function test_can_log_and_retrieve_notifications(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/notifications-log', [
                'canal' => 'sms',
                'destinataire' => '+225 0707070707',
                'sujet' => 'Rappel Réunion',
                'message' => 'Rappel: Réunion des parents ce samedi à 15h.',
                'statut_envoi' => 'envoye',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'canal' => 'sms',
                    'destinataire' => '+225 0707070707',
                    'statut_envoi' => 'envoye',
                ],
            ]);

        $this->assertDatabaseHas('notifications_log', [
            'destinataire' => '+225 0707070707',
            'canal' => 'sms',
        ]);
    }

    /**
     * Test du journal d'audit de sécurité des actions utilisateurs.
     */
    public function test_can_log_and_query_audit_logs(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/audit-logs', [
                'action' => 'create',
                'entite_type' => 'Catechumene',
                'entite_id' => 1,
                'nouvelles_valeurs' => ['nom' => 'KOUASSI', 'prenoms' => 'Jean'],
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'action' => 'create',
                    'entite_type' => 'Catechumene',
                ],
            ]);

        $listResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/audit-logs?action=create');

        $listResponse->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }
}
