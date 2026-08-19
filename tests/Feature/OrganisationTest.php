<?php

namespace Tests\Feature;

use App\Models\AnneeCatechese;
use App\Models\Classe;
use App\Models\Niveau;
use App\Models\ParoisseConfiguration;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganisationTest extends TestCase
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
     * Test de consultation des paramètres de la paroisse.
     */
    public function test_can_get_paroisse_configuration(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/paroisse-configuration');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'nom' => 'Paroisse Cathédrale Saint-Paul',
                    'code_paroisse' => 'PAR-STPAUL-01',
                ],
            ]);
    }

    /**
     * Test de la liste des années pastorales.
     */
    public function test_can_list_annee_catecheses(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/annee-catecheses');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => ['id', 'libelle', 'date_debut', 'date_fin', 'est_active', 'statut'],
                ],
            ]);
    }

    /**
     * Test d'activation d'une nouvelle année pastorale.
     */
    public function test_can_activate_pastoral_year(): void
    {
        $nouvelleAnnee = AnneeCatechese::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'libelle' => '2025-2026',
            'date_debut' => '2025-10-01',
            'date_fin' => '2026-06-30',
            'est_active' => false,
            'statut' => 'preparation',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->patchJson('/api/v1/annee-catecheses/' . $nouvelleAnnee->uuid . '/activate');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'id' => $nouvelleAnnee->uuid,
                    'est_active' => true,
                ],
            ]);

        // L'ancienne année active doit maintenant être inactive
        $ancienneAnnee = AnneeCatechese::where('libelle', '2024-2025')->first();
        $this->assertFalse($ancienneAnnee->est_active);
    }

    /**
     * Test de la liste des sections et niveaux.
     */
    public function test_can_list_sections_and_niveaux(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/sections');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => ['id', 'nom', 'code', 'niveaux'],
                ],
            ]);
    }

    public function test_can_create_classe(): void
    {
        $annee = AnneeCatechese::first();
        $niveau = Niveau::first();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/classes', [
                'annee_catechese_id' => $annee->uuid,
                'niveau_id'          => $niveau->uuid,
                'nom'                => 'Classe Sainte-Thérèse B',
                'capacite_max'       => 30,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Classe créée avec succès.',
                'data'    => [
                    'nom'          => 'Classe Sainte-Thérèse B',
                    'capacite_max' => 30,
                    'statut'       => 'active',
                ],
            ]);

        $this->assertDatabaseHas('classes', ['nom' => 'Classe Sainte-Thérèse B']);
    }

    /**
     * Test de création d'un niveau sans code ni âges minimum/maximum.
     */
    public function test_can_create_niveau_without_code_and_without_age(): void
    {
        $section = Section::first();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/niveaux', [
                'section_id'      => $section->uuid,
                'nom'             => 'Niveau Découverte Foi',
                'description'     => 'Niveau sans code ni contrainte d\'âge',
                'statut'          => 'actif',
                'duree_annees'    => 1,
                'ordre_affichage' => 5,
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Niveau créé avec succès.',
                'data'    => [
                    'nom'         => 'Niveau Découverte Foi',
                    'description' => 'Niveau sans code ni contrainte d\'âge',
                    'statut'      => 'Actif',
                ],
            ]);

        $this->assertDatabaseHas('niveaux', [
            'nom' => 'Niveau Découverte Foi',
            'code' => null,
        ]);
    }

    /**
     * Test complet de gestion des CEB (Création, Consultation, Modification, Toggle Statut).
     */
    public function test_can_manage_cebs(): void
    {
        // 1. Création
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/cebs', [
                'nom'         => 'CEB Saint-Luc',
                'responsable' => 'KOUASSI Roger',
                'telephone'   => '+225 0708091011',
                'adresse'     => 'Quartier Nord, Rue 12',
                'description' => 'Communauté de base locale',
                'statut'      => 'Active',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status'  => 'success',
                'message' => 'CEB créée avec succès.',
                'data'    => [
                    'nom'         => 'CEB Saint-Luc',
                    'responsable' => 'KOUASSI Roger',
                    'statut'      => 'Active',
                ],
            ]);

        $cebId = $response->json('data.id');

        // 2. Liste
        $listResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/cebs');
        $listResponse->assertStatus(200);

        // 3. Toggle Statut
        $toggleResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->patchJson("/api/v1/cebs/{$cebId}/status");
        $toggleResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => ['statut' => 'Inactive'],
            ]);
    }

    /**
     * Test complet de gestion des Mouvements paroissiaux.
     */
    public function test_can_manage_mouvements(): void
    {
        // 1. Création
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/mouvements', [
                'nom'         => 'Scouts Catholiques',
                'responsable' => 'KONAN Jean',
                'telephone'   => '+225 0102030405',
                'description' => 'Mouvement scout paroissial',
                'statut'      => 'Active',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Mouvement créé avec succès.',
                'data'    => [
                    'nom'    => 'Scouts Catholiques',
                    'statut' => 'Active',
                ],
            ]);

        $mouvementId = $response->json('data.id');

        // 2. Liste
        $listResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/mouvements');
        $listResponse->assertStatus(200);

        // 3. Toggle Statut
        $toggleResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->patchJson("/api/v1/mouvements/{$mouvementId}/status");
        $toggleResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => ['statut' => 'Inactive'],
            ]);
    }

    /**
     * Test complet de gestion du Calendrier pastoral.
     */
    public function test_can_manage_calendriers(): void
    {
        $annee = AnneeCatechese::first();
        $section = Section::first();

        // 1. Création événement ciblé section
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/calendriers', [
                'annee_catechese_id' => $annee->uuid,
                'titre'              => 'Journée de Récollection Pastorale',
                'type'               => 'Récollection',
                'date'               => '2026-11-20',
                'heure_debut'        => '08:30',
                'heure_fin'          => '16:00',
                'lieu'               => 'Centre d\'accueil Saint-Paul',
                'cible_type'         => 'SECTION',
                'cible_id'           => $section->uuid,
                'description'        => 'Journée de prière pour tous les catéchumènes de la section',
                'statut'             => 'Planifié',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Événement du calendrier créé avec succès.',
                'data'    => [
                    'titre'      => 'Journée de Récollection Pastorale',
                    'cible_type' => 'SECTION',
                    'cible_id'   => $section->uuid,
                    'statut'     => 'Planifié',
                ],
            ]);

        $calendrierId = $response->json('data.id');

        // 2. Liste avec filtres
        $listResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/calendriers?cible_type=SECTION');
        $listResponse->assertStatus(200);

        // 3. Mise à jour statut
        $statusResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->patchJson("/api/v1/calendriers/{$calendrierId}/status", [
                'statut' => 'Réalisé',
            ]);
        $statusResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => ['statut' => 'Réalisé'],
            ]);
    }
}

