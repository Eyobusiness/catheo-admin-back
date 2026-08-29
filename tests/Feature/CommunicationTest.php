<?php

namespace Tests\Feature;

use App\Models\AffectationAnimateur;
use App\Models\Animateur;
use App\Models\AnneeCatechese;
use App\Models\Annonce;
use App\Models\AuditLog;
use App\Models\Catechumene;
use App\Models\CatecheseConfiguration;
use App\Models\Classe;
use App\Models\InscriptionAnnuelle;
use App\Models\Niveau;
use App\Models\NotificationLog;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunicationTest extends TestCase
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

    /**
     * Test de publication et de consultation des annonces ciblées.
     */
    public function test_can_create_and_list_annonces(): void
    {
        $annee = AnneeCatechese::where('libelle', '2024-2025')->first();
        $classe = Classe::first();

        // 1. Publier une annonce ciblée vers une classe spécifique
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/annonces', [
                'annee_catechese_id' => $annee->uuid,
                'titre'              => 'Rappel Catéchèse pour la classe St Joseph',
                'contenu'            => 'Chers catéchistes et parents, n\'oubliez pas les carnets.',
                'cible_type'         => 'CLASSE',
                'cible_id'           => $classe->uuid,
                'canal'              => 'in_app',
                'date_diffusion'     => '2026-09-01',
                'priorite'           => 'haute',
                'statut'             => 'publiee',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data'   => [
                    'titre'      => 'Rappel Catéchèse pour la classe St Joseph',
                    'cible_type' => 'CLASSE',
                    'priorite'   => 'haute',
                ],
            ]);

        // 2. Lister les annonces de la paroisse
        $listResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/annonces?cible_type=CLASSE');

        $listResponse->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    /**
     * Test de mise à jour, changement de statut et suppression.
     */
    public function test_can_update_and_delete_annonce(): void
    {
        $annee = AnneeCatechese::first();
        $annonce = Annonce::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'annee_catechese_id'        => $annee->id,
            'titre'                     => 'Annonce initiale',
            'contenu'                   => 'Contenu initial',
            'cible_type'                => 'TOUS',
            'statut'                    => 'brouillon',
        ]);

        // Update
        $updateResp = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/v1/annonces/{$annonce->uuid}", [
                'titre'   => 'Annonce modifiée',
                'contenu' => 'Nouveau contenu',
                'statut'  => 'publiee',
            ]);

        $updateResp->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => [
                    'titre'  => 'Annonce modifiée',
                    'statut' => 'publiee',
                ],
            ]);

        // Toggle Status
        $statusResp = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->patchJson("/api/v1/annonces/{$annonce->uuid}/statut", [
                'statut' => 'archivee',
            ]);
        $statusResp->assertStatus(200);

        // Delete
        $deleteResp = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/v1/annonces/{$annonce->uuid}");
        $deleteResp->assertStatus(200);

        $this->assertSoftDeleted('annonces', ['id' => $annonce->id]);
    }

    /**
     * Test de diffusion immédiate d'une annonce.
     */
    public function test_can_diffuser_annonce(): void
    {
        $annee = AnneeCatechese::first();
        $annonce = Annonce::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'annee_catechese_id'        => $annee->id,
            'titre'                     => 'Alerte Messe des Rameaux',
            'contenu'                   => 'Rendez-vous à 08h.',
            'cible_type'                => 'TOUS',
            'canal'                     => 'sms',
            'statut'                    => 'brouillon',
        ]);

        $diffuseResp = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/annonces/{$annonce->uuid}/diffuser");

        $diffuseResp->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => [
                    'statut' => 'envoyee',
                ],
            ]);

        $this->assertDatabaseHas('notifications_log', [
            'canal' => 'sms',
        ]);
    }

    /**
     * Test de distribution ciblée pour le compte Animateur.
     */
    public function test_targeted_notifications_for_animateur(): void
    {
        $annee = AnneeCatechese::first();
        $classes = Classe::take(2)->get();
        $classe1 = $classes[0];
        $classe2 = $classes[1];

        // Animateur affecté à classe1
        $animateur = Animateur::first();
        $animateur->update(['paroisse_configuration_id' => $this->paroisse->id]);
        AffectationAnimateur::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'animateur_id'              => $animateur->id,
            'classe_id'                 => $classe1->id,
            'annee_catechese_id'        => $annee->id,
            'role_animateur'            => 'principal',
        ]);

        $animateurToken = $animateur->createToken('AnimTest')->plainTextToken;

        // Annonce 1 : Ciblée Tous (doit être reçue)
        Annonce::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'annee_catechese_id'        => $annee->id,
            'titre'                     => 'Annonce Générale',
            'contenu'                   => 'Pour tous',
            'cible_type'                => 'TOUS',
            'statut'                    => 'publiee',
        ]);

        // Annonce 2 : Ciblée Animateurs (doit être reçue)
        Annonce::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'annee_catechese_id'        => $annee->id,
            'titre'                     => 'Réunion Pédagogique Catéchistes',
            'contenu'                   => 'Formation samedi',
            'cible_type'                => 'ANIMATEURS',
            'statut'                    => 'publiee',
        ]);

        // Annonce 3 : Ciblée classe1 (doit être reçue)
        Annonce::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'annee_catechese_id'        => $annee->id,
            'titre'                     => 'Message Classe 1',
            'contenu'                   => 'Spécifique classe 1',
            'cible_type'                => 'CLASSE',
            'cible_id'                  => (string) $classe1->id,
            'classe_id'                 => $classe1->id,
            'statut'                    => 'publiee',
        ]);

        // Annonce 4 : Ciblée classe2 (NON reçue par cet animateur)
        Annonce::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'annee_catechese_id'        => $annee->id,
            'titre'                     => 'Message Classe 2',
            'contenu'                   => 'Spécifique classe 2',
            'cible_type'                => 'CLASSE',
            'cible_id'                  => (string) $classe2->id,
            'classe_id'                 => $classe2->id,
            'statut'                    => 'publiee',
        ]);

        $feedResp = $this->withHeader('Authorization', 'Bearer ' . $animateurToken)
            ->getJson('/api/v1/notifications');

        $feedResp->assertStatus(200);
        $data = $feedResp->json('data');

        // Doit contenir Annonce 1, 2, 3 mais PAS Annonce 4
        $titles = collect($data)->pluck('titre')->all();
        $this->assertContains('Annonce Générale', $titles);
        $this->assertContains('Réunion Pédagogique Catéchistes', $titles);
        $this->assertContains('Message Classe 1', $titles);
        $this->assertNotContains('Message Classe 2', $titles);
    }

    /**
     * Test de distribution ciblée pour le compte Parent / Catéchumène.
     */
    public function test_targeted_notifications_for_parent(): void
    {
        $annee = AnneeCatechese::first();
        $classes = Classe::with('niveau.section')->take(2)->get();
        $classe1 = $classes[0];
        $classe2 = $classes[1];

        // Catéchumène inscrit en classe 1
        $cat = Catechumene::first();
        $cat->update(['paroisse_configuration_id' => $this->paroisse->id]);

        InscriptionAnnuelle::updateOrCreate(
            [
                'paroisse_configuration_id' => $this->paroisse->id,
                'catechumene_id'            => $cat->id,
                'annee_catechese_id'        => $annee->id,
            ],
            [
                'section_id'                => $classe1->niveau?->section_id ?? 1,
                'niveau_id'                 => $classe1->niveau_id,
                'classe_id'                 => $classe1->id,
                'statut_inscription'        => 'valide',
                'frais_inscription_payes'   => true,
                'date_inscription'          => now()->toDateString(),
            ]
        );

        $parentToken = $cat->createToken('ParentTest')->plainTextToken;

        // Annonce 1 : Ciblée Parents (reçue)
        Annonce::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'annee_catechese_id'        => $annee->id,
            'titre'                     => 'Message aux Parents',
            'contenu'                   => 'Info pastorale',
            'cible_type'                => 'PARENTS',
            'statut'                    => 'publiee',
        ]);

        // Annonce 2 : Ciblée Classe 1 (reçue)
        Annonce::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'annee_catechese_id'        => $annee->id,
            'titre'                     => 'Devoir Classe 1',
            'contenu'                   => 'Réciter le Credo',
            'cible_type'                => 'CLASSE',
            'cible_id'                  => (string) $classe1->id,
            'classe_id'                 => $classe1->id,
            'statut'                    => 'publiee',
        ]);

        // Annonce 3 : Ciblée Classe 2 (NON reçue)
        Annonce::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'annee_catechese_id'        => $annee->id,
            'titre'                     => 'Devoir Classe 2',
            'contenu'                   => 'Autre devoir',
            'cible_type'                => 'CLASSE',
            'cible_id'                  => (string) $classe2->id,
            'classe_id'                 => $classe2->id,
            'statut'                    => 'publiee',
        ]);

        $feedResp = $this->withHeader('Authorization', 'Bearer ' . $parentToken)
            ->getJson('/api/v1/notifications');

        $feedResp->assertStatus(200);
        $titles = collect($feedResp->json('data'))->pluck('titre')->all();

        $this->assertContains('Message aux Parents', $titles);
        $this->assertContains('Devoir Classe 1', $titles);
        $this->assertNotContains('Devoir Classe 2', $titles);
    }

    /**
     * Test de marquage lu / non lu et compteur de badge.
     */
    public function test_notification_read_tracking_and_unread_count(): void
    {
        $annee = AnneeCatechese::first();
        $annonce = Annonce::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'annee_catechese_id'        => $annee->id,
            'titre'                     => 'Notification Test Lecture',
            'contenu'                   => 'Veuillez lire ce message.',
            'cible_type'                => 'TOUS',
            'statut'                    => 'publiee',
        ]);

        // Compteur initial non lu
        $countResp = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/notifications/unread-count');
        $countResp->assertStatus(200)
            ->assertJson(['status' => 'success', 'unread_count' => 1]);

        // Marquer comme lue
        $readResp = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/notifications/{$annonce->uuid}/marquer-lue");
        $readResp->assertStatus(200);

        // Compteur après lecture = 0
        $countResp2 = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/notifications/unread-count');
        $countResp2->assertStatus(200)
            ->assertJson(['status' => 'success', 'unread_count' => 0]);
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
