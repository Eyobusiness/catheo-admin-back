<?php

namespace Tests\Feature;

use App\Models\AffectationAnimateur;
use App\Models\Animateur;
use App\Models\AnneeCatechese;
use App\Models\Classe;
use App\Models\Niveau;
use App\Models\CatecheseConfiguration;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganisationTest extends TestCase
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
     * Test de consultation des paramètres de la paroisse / catéchèse.
     */
    public function test_can_get_paroisse_configuration(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/paroisse-configuration');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'nom_paroisse' => 'Paroisse Cathédrale Saint-Paul',
                    'code_paroisse' => 'PAR-STPAUL-01',
                ],
            ]);
    }

    public function test_can_get_catechese_configuration(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/catechese-configuration');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'nom_paroisse' => 'Paroisse Cathédrale Saint-Paul',
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
                    '*' => ['id', 'libelle', 'date_debut', 'date_fin', 'statut'],
                ],
            ]);
    }

    /**
     * Test de récupération de l'année pastorale courante / active.
     */
    public function test_can_get_current_pastoral_year(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/annee-catecheses/current');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => ['id', 'libelle', 'date_debut', 'date_fin', 'statut'],
            ])
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'libelle' => '2024-2025',
                    'statut' => 'active',
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
            'statut' => 'preparation',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->patchJson('/api/v1/annee-catecheses/' . $nouvelleAnnee->uuid . '/activate');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'id' => $nouvelleAnnee->uuid,
                    'statut' => 'active',
                ],
            ]);

        // L'ancienne année active doit maintenant être clôturée
        $ancienneAnnee = AnneeCatechese::where('libelle', '2024-2025')->first();
        $this->assertEquals('cloturee', $ancienneAnnee->statut);
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

    /**
     * Test complet du CRUD pour les Sections (Création, Consultation, Recherche, Filtre, Modification, Toggle Statut, Suppression).
     */
    public function test_can_manage_sections_crud(): void
    {
        // 1. CREATE Section
        $createResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/sections', [
                'nom' => 'Section Catéchuménat Spécial',
                'code' => 'SEC-SPEC',
                'description' => 'Section dédiée aux parcours spécifiques',
                'statut' => 'actif',
                'ordre_affichage' => 10,
            ]);

        $createResponse->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'message' => 'Section créée avec succès.',
                'data' => [
                    'nom' => 'Section Catéchuménat Spécial',
                    'code' => 'SEC-SPEC',
                    'statut' => 'Actif',
                ],
            ]);

        $sectionUuid = $createResponse->json('data.id');
        $this->assertNotEmpty($sectionUuid);

        // 2. READ / SHOW Section
        $showResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/v1/sections/{$sectionUuid}");

        $showResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'id' => $sectionUuid,
                    'nom' => 'Section Catéchuménat Spécial',
                    'code' => 'SEC-SPEC',
                ],
            ]);

        // 3. SEARCH & FILTER
        $searchResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/sections?search=Catéchuménat');
        $searchResponse->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($searchResponse->json('data')));

        // 4. UPDATE Section
        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/v1/sections/{$sectionUuid}", [
                'nom' => 'Section Spéciale Modifiée',
                'code' => 'SEC-SPEC-MOD',
                'description' => 'Description mise à jour',
            ]);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Section mise à jour avec succès.',
                'data' => [
                    'nom' => 'Section Spéciale Modifiée',
                    'code' => 'SEC-SPEC-MOD',
                ],
            ]);

        // 5. TOGGLE STATUT
        $toggleResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->patchJson("/api/v1/sections/{$sectionUuid}/status");

        $toggleResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'statut' => 'Inactif',
                    'statut_code' => 'inactif',
                ],
            ]);

        // 6. DELETE WITH ATTACHED NIVEAUX PREVENTED
        $existingSectionWithNiveaux = Section::has('niveaux')->first();
        if ($existingSectionWithNiveaux) {
            $deleteBlockedResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
                ->deleteJson("/api/v1/sections/{$existingSectionWithNiveaux->uuid}");

            $deleteBlockedResponse->assertStatus(422)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Impossible de supprimer une section contenant des niveaux rattachés.',
                ]);
        }

        // 7. DELETE Section without niveaux
        $deleteResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/v1/sections/{$sectionUuid}");

        $deleteResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Section supprimée avec succès.',
            ]);

        $this->assertSoftDeleted('sections', [
            'uuid' => $sectionUuid,
        ]);
    }

    /**
     * Test complet du CRUD pour les Classes (Création, Consultation, Liste, Recherche, Filtre, Modification, Toggle Statut, Suppression).
     */
    public function test_can_manage_classes_crud(): void
    {
        $annee = AnneeCatechese::first();
        $niveau = Niveau::first();

        // 1. CREATE Classe
        $createResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/classes', [
                'annee_catechese_id' => $annee->uuid,
                'niveau_id'          => $niveau->uuid,
                'nom'                => 'Classe Sainte-Thérèse B',
                'capacite_max'       => 35,
                'statut'             => 'active',
            ]);

        $createResponse->assertStatus(201)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Classe créée avec succès.',
                'data'    => [
                    'nom'          => 'Classe Sainte-Thérèse B',
                    'capacite_max' => 35,
                    'statut'       => 'active',
                ],
            ]);

        $classeUuid = $createResponse->json('data.id');
        $this->assertNotEmpty($classeUuid);

        // 2. READ / SHOW Classe
        $showResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/v1/classes/{$classeUuid}");

        $showResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => [
                    'id'           => $classeUuid,
                    'nom'          => 'Classe Sainte-Thérèse B',
                    'capacite_max' => 35,
                ],
            ]);

        // 3. LIST, SEARCH & FILTER
        $searchResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/classes?search=Sainte-Thérèse');
        $searchResponse->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($searchResponse->json('data')));

        // 4. UPDATE Classe
        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/v1/classes/{$classeUuid}", [
                'nom'          => 'Classe Sainte-Thérèse B Modifiée',
                'capacite_max' => 40,
            ]);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Classe mise à jour avec succès.',
                'data'    => [
                    'nom'          => 'Classe Sainte-Thérèse B Modifiée',
                    'capacite_max' => 40,
                ],
            ]);

        // 5. TOGGLE STATUT
        $toggleResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->patchJson("/api/v1/classes/{$classeUuid}/status");

        $toggleResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => [
                    'statut' => 'inactive',
                ],
            ]);

        // 6. DELETE Classe
        $deleteResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/v1/classes/{$classeUuid}");

        $deleteResponse->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Classe supprimée avec succès.',
            ]);

        $this->assertSoftDeleted('classes', [
            'uuid' => $classeUuid,
        ]);
    }

    /**
     * Test complet du CRUD pour les Niveaux (Création, Consultation, Recherche, Modification, Toggle Statut, Suppression).
     */
    public function test_can_manage_niveaux_crud(): void
    {
        $section = Section::first();

        // 1. CREATE Niveau
        $createResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/niveaux', [
                'section_id'      => $section->uuid,
                'nom'             => 'Niveau Découverte Foi',
                'description'     => 'Niveau sans code',
                'statut'          => 'actif',
                'ordre_affichage' => 5,
            ]);

        $createResponse->assertStatus(201)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Niveau créé avec succès.',
                'data'    => [
                    'nom'         => 'Niveau Découverte Foi',
                    'description' => 'Niveau sans code',
                    'statut'      => 'Actif',
                ],
            ]);

        $niveauUuid = $createResponse->json('data.id');
        $this->assertNotEmpty($niveauUuid);
        $this->assertDatabaseHas('niveaux', [
            'uuid' => $niveauUuid,
            'nom' => 'Niveau Découverte Foi',
        ]);

        // 2. READ / SHOW Niveau
        $showResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/v1/niveaux/{$niveauUuid}");

        $showResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'id' => $niveauUuid,
                    'nom' => 'Niveau Découverte Foi',
                ],
            ]);

        // 3. SEARCH
        $searchResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/niveaux?search=Découverte');
        $searchResponse->assertStatus(200);
        $this->assertGreaterThanOrEqual(1, count($searchResponse->json('data')));

        // 4. UPDATE Niveau
        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/v1/niveaux/{$niveauUuid}", [
                'nom' => 'Niveau Approfondissement Foi',
                'description' => 'Description mise à jour',
            ]);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Niveau mis à jour avec succès.',
                'data' => [
                    'nom' => 'Niveau Approfondissement Foi',
                ],
            ]);

        // 5. TOGGLE STATUT
        $toggleResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->patchJson("/api/v1/niveaux/{$niveauUuid}/status");

        $toggleResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'statut' => 'Inactif',
                    'statut_code' => 'inactif',
                ],
            ]);

        // 6. DELETE WITH ATTACHED CLASSES PREVENTED
        $existingNiveauWithClasses = Niveau::has('classes')->first();
        if ($existingNiveauWithClasses) {
            $deleteBlockedResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
                ->deleteJson("/api/v1/niveaux/{$existingNiveauWithClasses->uuid}");

            $deleteBlockedResponse->assertStatus(422)
                ->assertJson([
                    'status' => 'error',
                    'message' => 'Impossible de supprimer un niveau auquel des classes sont rattachées.',
                ]);
        }

        // 7. DELETE Niveau without classes
        $deleteResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/v1/niveaux/{$niveauUuid}");

        $deleteResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Niveau supprimé avec succès.',
            ]);

        $this->assertSoftDeleted('niveaux', [
            'uuid' => $niveauUuid,
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
     * Test complet de gestion des Mouvements paroissiaux (Création, Consultation, Recherche, Modification, Toggle Statut, Suppression).
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
                    'nom'         => 'Scouts Catholiques',
                    'responsable' => 'KONAN Jean',
                    'statut'      => 'Active',
                ],
            ]);

        $mouvementId = $response->json('data.id');

        // 2. Liste & Recherche
        $listResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/mouvements?search=Scouts');
        $listResponse->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // 3. Consultation (Show)
        $showResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/v1/mouvements/{$mouvementId}");
        $showResponse->assertStatus(200)
            ->assertJsonPath('data.nom', 'Scouts Catholiques');

        // 4. Modification (Update)
        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/v1/mouvements/{$mouvementId}", [
                'nom'         => 'Scouts Catholiques Saint-Paul',
                'responsable' => 'KONAN Jean-Marc',
                'telephone'   => '+225 0102030499',
                'description' => 'Mouvement scout paroissial d\'élite',
            ]);
        $updateResponse->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Mouvement mis à jour avec succès.',
                'data'    => [
                    'nom'         => 'Scouts Catholiques Saint-Paul',
                    'responsable' => 'KONAN Jean-Marc',
                ],
            ]);

        // 5. Toggle Statut
        $toggleResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->patchJson("/api/v1/mouvements/{$mouvementId}/status");
        $toggleResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => ['statut' => 'Inactive'],
            ]);

        // 6. Suppression (Delete)
        $deleteResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/v1/mouvements/{$mouvementId}");
        $deleteResponse->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Mouvement supprimé avec succès.',
            ]);

        $this->assertSoftDeleted('mouvements', [
            'uuid' => $mouvementId,
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
                'cible_type'         => 'Catéchumènes',
                'cible_id'           => $section->uuid,
                'cible_ids'          => [$section->uuid],
                'cible_nom'          => 'Catéchumènes (Sections: Enfance)',
                'description'        => 'Journée de prière pour tous les catéchumènes de la section',
                'statut'             => 'Planifié',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Événement enregistré avec succès.',
                'data'    => [
                    'titre'      => 'Journée de Récollection Pastorale',
                    'cible_type' => 'Catéchumènes',
                    'statut'     => 'Planifié',
                ],
            ]);

        $calendrierId = $response->json('data.id');

        // 2. Liste avec filtres
        $listResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/calendriers?cible_type=Catéchumènes&search=Récollection');
        $listResponse->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // 3. Consultation (Show)
        $showResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/v1/calendriers/{$calendrierId}");
        $showResponse->assertStatus(200)
            ->assertJsonPath('data.titre', 'Journée de Récollection Pastorale');

        // 4. Mise à jour complète (PUT)
        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/v1/calendriers/{$calendrierId}", [
                'titre' => 'Grande Récollection Pastorale',
                'lieu'  => 'Cathédrale CIM',
            ]);
        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.titre', 'Grande Récollection Pastorale')
            ->assertJsonPath('data.lieu', 'Cathédrale CIM');

        // 5. Mise à jour statut (PATCH)
        $statusResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->patchJson("/api/v1/calendriers/{$calendrierId}/status", [
                'statut' => 'Réalisé',
            ]);
        $statusResponse->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => ['statut' => 'Réalisé'],
            ]);

        // 6. Suppression (DELETE)
        $deleteResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/v1/calendriers/{$calendrierId}");
        $deleteResponse->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertSoftDeleted('calendriers', [
            'uuid' => $calendrierId,
        ]);
    }

    /**
     * Test complet de gestion des Modules Trimestriels (Création, Liste, Consultation, Modification, Suppression).
     */
    public function test_can_manage_modules_trimestriels(): void
    {
        $annee = AnneeCatechese::first();

        // 1. Création (POST)
        $createResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/modules-trimestriels', [
                'annee_catechese_id' => $annee->uuid,
                'nom'                => '1er Trimestre - Foi & Découverte',
                'numero_trimestre'   => 1,
                'date_debut'         => '2026-10-01',
                'date_fin'           => '2026-12-20',
            ]);

        $createResponse->assertStatus(201)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Module trimestriel créé avec succès.',
                'data'    => [
                    'nom'              => '1er Trimestre - Foi & Découverte',
                    'numero_trimestre' => 1,
                    'date_debut'       => '2026-10-01',
                    'date_fin'         => '2026-12-20',
                ],
            ]);

        $moduleId = $createResponse->json('data.id');

        // 2. Liste & Recherche (GET)
        $listResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/v1/modules-trimestriels?annee_catechese_id={$annee->uuid}&search=Foi");
        $listResponse->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // 3. Consultation (Show GET)
        $showResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/v1/modules-trimestriels/{$moduleId}");
        $showResponse->assertStatus(200)
            ->assertJsonPath('data.numero_trimestre', 1);

        // 4. Modification (PUT / PATCH)
        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/v1/modules-trimestriels/{$moduleId}", [
                'nom'              => '1er Trimestre - Initiation et Découverte',
                'numero_trimestre' => 1,
                'date_debut'       => '2026-10-05',
                'date_fin'         => '2026-12-22',
            ]);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Module trimestriel mis à jour avec succès.',
                'data'    => [
                    'nom' => '1er Trimestre - Initiation et Découverte',
                ],
            ]);

        // 5. Toggle Statut (PATCH /status)
        $toggleResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->patchJson("/api/v1/modules-trimestriels/{$moduleId}/status");

        $toggleResponse->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'data'    => [
                    'statut' => 'termine',
                ],
            ]);

        // 6. Suppression (DELETE)
        $deleteResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/v1/modules-trimestriels/{$moduleId}");

        $deleteResponse->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Module trimestriel supprimé avec succès.',
            ]);

        $this->assertSoftDeleted('modules_trimestriels', [
            'uuid' => $moduleId,
        ]);
    }

    /**
     * Test complet de gestion des Animateurs et Authentification directe.
     */
    public function test_can_manage_animateurs_and_login(): void
    {
        // 1. Création Animateur avec mot de passe direct
        $createResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/animateurs', [
                'nom'        => 'KOUASSI',
                'prenoms'    => 'Emmanuel',
                'sexe'       => 'M',
                'telephone'  => '+225 0102030405',
                'email'      => 'emmanuel.kouassi@catheo.ci',
                'profession' => 'Ingénieur Logiciel',
                'statut'     => 'actif',
                'password'   => 'SecretPassword123',
            ]);

        $createResponse->assertStatus(201)
            ->assertJson([
                'status'  => 'success',
                'data'    => [
                    'nom'       => 'KOUASSI',
                    'prenoms'   => 'Emmanuel',
                    'sexe'      => 'M',
                    'telephone' => '+225 0102030405',
                    'email'     => 'emmanuel.kouassi@catheo.ci',
                    'statut'    => 'actif',
                ],
            ]);

        $animateurId = $createResponse->json('data.id');

        // 2. Liste & Recherche
        $listResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/animateurs?search=Emmanuel');
        $listResponse->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // 3. Consultation (Show)
        $showResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/v1/animateurs/{$animateurId}");
        $showResponse->assertStatus(200)
            ->assertJsonPath('data.nom', 'KOUASSI');

        // 4. Authentification directe de l'animateur (table animateurs)
        $loginResponse = $this->postJson('/api/v1/auth/animateurs/login', [
            'login'    => '+225 0102030405',
            'password' => 'SecretPassword123',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Connexion animateur réussie.',
                'data'    => [
                    'user_type' => 'animateur',
                    'user'      => [
                        'nom' => 'KOUASSI',
                    ],
                ],
            ]);

        // 5. Modification
        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/v1/animateurs/{$animateurId}", [
                'profession' => 'Architecte Systèmes',
            ]);
        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.profession', 'Architecte Systèmes');

        // 6. Bascule statut
        $statusResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->patchJson("/api/v1/animateurs/{$animateurId}/status", [
                'statut' => 'inactif',
            ]);
        $statusResponse->assertStatus(200)
            ->assertJsonPath('data.statut', 'inactif');

        // 7. Suppression
        $deleteResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/v1/animateurs/{$animateurId}");
        $deleteResponse->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertSoftDeleted('animateurs', [
            'uuid' => $animateurId,
        ]);
    }

    /**
     * Test complet de gestion des Affectations d'Animateurs (Création, Liste, Consultation, Modification, Suppression).
     */
    public function test_can_manage_affectations_animateurs(): void
    {
        $annee = AnneeCatechese::first();
        $classe = Classe::first();
        $animateur = Animateur::first();

        // 1. Création affectation (POST)
        $createResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/affectations-animateurs', [
                'animateur_id'       => $animateur->uuid,
                'annee_catechese_id' => $annee->uuid,
                'classe_id'          => $classe->uuid,
                'role_animateur'     => 'principal',
            ]);

        $createResponse->assertStatus(201)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Animateur affecté avec succès.',
                'data'    => [
                    'role_animateur' => 'principal',
                ],
            ]);

        $affectationId = $createResponse->json('data.id');

        // 2. Liste & Recherche (GET)
        $listResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/v1/affectations-animateurs?annee_catechese_id={$annee->uuid}&classe_id={$classe->uuid}");
        $listResponse->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // 3. Consultation (Show GET)
        $showResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/v1/affectations-animateurs/{$affectationId}");
        $showResponse->assertStatus(200)
            ->assertJsonPath('data.role_animateur', 'principal');

        // 4. Modification (PUT / PATCH)
        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/v1/affectations-animateurs/{$affectationId}", [
                'role_animateur' => 'adjoint',
            ]);
        $updateResponse->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'data'    => [
                    'role_animateur' => 'adjoint',
                ],
            ]);

        // 5. Suppression (DELETE)
        $deleteResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/v1/affectations-animateurs/{$affectationId}");
        $deleteResponse->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Affectation supprimée avec succès.',
            ]);

        $this->assertSoftDeleted('affectations_animateurs', [
            'uuid' => $affectationId,
        ]);
    }
}




