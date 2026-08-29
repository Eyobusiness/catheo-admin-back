<?php

namespace Tests\Feature;

use App\Models\AnneeCatechese;
use App\Models\Catechumene;
use App\Models\Classe;
use App\Models\Evaluation;
use App\Models\InscriptionAnnuelle;
use App\Models\ModuleTrimestriel;
use App\Models\Niveau;
use App\Models\CatecheseConfiguration;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluationTest extends TestCase
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
     * Test de planification d'une séance et de l'appel par lot des présences.
     */
    public function test_can_plan_seance_and_record_batch_presences(): void
    {
        $annee = AnneeCatechese::where('libelle', '2024-2025')->first() ?? AnneeCatechese::first();
        $classe = Classe::first();
        $module = ModuleTrimestriel::where('numero_trimestre', 1)->first() ?? ModuleTrimestriel::first();

        // 1. Création d'un catéchumène inscrit dans la classe
        $catechumene = Catechumene::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'matricule' => 'CAT-2024-0001',
            'nom' => 'KOUAME',
            'prenoms' => 'Jean-Marc',
            'sexe' => 'M',
            'date_naissance' => '2015-02-10',
            'statut' => 'actif',
        ]);

        $inscription = InscriptionAnnuelle::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'catechumene_id' => $catechumene->id,
            'annee_catechese_id' => $annee->id,
            'niveau_id' => $classe->niveau_id,
            'classe_id' => $classe->id,
            'date_inscription' => now()->toDateString(),
            'statut_inscription' => 'valide',
        ]);

        // 2. Planifier la séance (POST)
        $seanceResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/seances', [
                'annee_catechese_id' => $annee->uuid,
                'classe_id' => $classe->uuid,
                'titre' => 'Séance 1 : La création du monde',
                'date_seance' => '2024-10-05',
                'heure_debut' => '09:00',
                'heure_fin' => '11:00',
            ]);

        $seanceResponse->assertStatus(201);
        $seanceUuid = $seanceResponse->json('data.id');

        // 3. Saisie des présences en lot (POST)
        $presenceResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/seances/{$seanceUuid}/presences", [
                'presences' => [
                    [
                        'catechumene_id' => $catechumene->uuid,
                        'statut_presence' => 'present',
                        'remarque' => 'Participation active',
                    ]
                ]
            ]);

        $presenceResponse->assertStatus(200)
            ->assertJsonPath('data.statut', 'effectuee');
        $this->assertDatabaseHas('presences', ['statut_presence' => 'present']);

        // 4. Consultation des détails & présences (GET)
        $showResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/v1/seances/{$seanceUuid}");
        $showResponse->assertStatus(200)
            ->assertJsonPath('data.titre', 'Séance 1 : La création du monde')
            ->assertJsonPath('data.total_presents', 1);

        // 5. Mise à jour de la séance (PUT)
        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/v1/seances/{$seanceUuid}", [
                'titre' => 'Séance 1 : La création et la Genèse',
            ]);
        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.titre', 'Séance 1 : La création et la Genèse');

        // 6. Suppression de la séance (DELETE)
        $deleteResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/v1/seances/{$seanceUuid}");
        $deleteResponse->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertSoftDeleted('seances', ['uuid' => $seanceUuid]);
    }

    /**
     * Test de création d'une évaluation, saisie des notes en lot et calcul du bulletin trimestriel.
     */
    public function test_can_create_eval_record_notes_and_calculate_bulletin(): void
    {
        $annee = AnneeCatechese::where('libelle', '2024-2025')->first() ?? AnneeCatechese::first();
        $classe = Classe::first();
        $module = ModuleTrimestriel::where('numero_trimestre', 1)->first() ?? ModuleTrimestriel::first();

        // 1. Inscrire 2 catéchumènes dans la classe
        $cat1 = Catechumene::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'matricule' => 'CAT-2024-0010',
            'nom' => 'BAMBA',
            'prenoms' => 'Awa',
            'sexe' => 'F',
            'date_naissance' => '2015-06-12',
            'statut' => 'actif',
        ]);

        $cat2 = Catechumene::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'matricule' => 'CAT-2024-0011',
            'nom' => 'COULIBALY',
            'prenoms' => 'Sékou',
            'sexe' => 'M',
            'date_naissance' => '2015-09-18',
            'statut' => 'actif',
        ]);

        $insc1 = InscriptionAnnuelle::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'catechumene_id' => $cat1->id,
            'annee_catechese_id' => $annee->id,
            'niveau_id' => $classe->niveau_id,
            'classe_id' => $classe->id,
            'date_inscription' => now()->toDateString(),
        ]);

        $insc2 = InscriptionAnnuelle::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'catechumene_id' => $cat2->id,
            'annee_catechese_id' => $annee->id,
            'niveau_id' => $classe->niveau_id,
            'classe_id' => $classe->id,
            'date_inscription' => now()->toDateString(),
        ]);

        // 2. Création d'une évaluation
        $evalResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/evaluations', [
                'annee_catechese_id' => $annee->uuid,
                'module_trimestriel_id' => $module->uuid,
                'classe_id' => $classe->uuid,
                'titre' => 'Devoir N°1 Catéchisme',
                'type_eval' => 'devoir',
                'coefficient' => 1.0,
                'note_max' => 20.0,
                'date_evaluation' => '2024-11-10',
            ]);

        $evalResponse->assertStatus(201);
        $evalUuid = $evalResponse->json('data.id');

        // 3. Obtenir la grille de notes
        $gridResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/v1/evaluations/{$evalUuid}/notes-grid");

        $gridResponse->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'evaluation' => ['id', 'stats' => ['moyenne_classe', 'plus_forte_note', 'plus_faible_note', 'saisies_effectuees']],
                'data' => [
                    '*' => ['catechumene_id', 'code_catechumene', 'nom_prenoms', 'note_obtenue', 'appreciation']
                ]
            ]);

        // 4. Saisie des notes (Awa: 17/20, Sékou: 12/20)
        $notesResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/evaluations/{$evalUuid}/notes", [
                'notes' => [
                    ['catechumene_id' => $cat1->uuid, 'note_obtenue' => 17.0, 'appreciation' => 'Très Bien'],
                    ['catechumene_id' => $cat2->uuid, 'note_obtenue' => 12.0, 'appreciation' => 'Assez Bien'],
                ]
            ]);

        $notesResponse->assertStatus(200);

        // 4b. Vérifier la récupération directe des notes (GET)
        $getNotesResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/v1/evaluations/{$evalUuid}/notes");
        $getNotesResponse->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['data' => [['id', 'catechumeneId', 'note', 'nomPrenoms']]]);

        // 5. Simuler des notes aléatoires via le bouton de démo
        $simuResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/evaluations/{$evalUuid}/simuler");

        $simuResponse->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // 6. Calculer automatiquement les bulletins trimestriels et classements
        $calcResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/bulletins-trimestriels/calculer', [
                'classe_id' => $classe->uuid,
                'module_trimestriel_id' => $module->uuid,
            ]);

        $calcResponse->assertStatus(200)
            ->assertJsonPath('status', 'success');
    }

    /**
     * Test complet du CRUD des évaluations synchronisé avec le frontend.
     */
    public function test_can_manage_evaluation_crud(): void
    {
        // 1. Création (POST avec format Angular)
        $createResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/evaluations', [
                'nom'            => 'Interrogation surprise N°1',
                'type'           => 'Interrogation',
                'periode'        => 'Trimestre 1',
                'date'           => '2026-10-15',
                'coefficient'    => 2,
                'bareme'         => 20,
                'anneePastorale' => '2024-2025',
                'statut'         => 'Actif',
                'observation'    => 'Interrogation écrite sur les commandements',
            ]);

        $createResponse->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.nom', 'Interrogation surprise N°1')
            ->assertJsonPath('data.type', 'Interrogation')
            ->assertJsonPath('data.bareme', 20)
            ->assertJsonPath('data.statut', 'Actif');

        $evalId = $createResponse->json('data.id');

        // 2. Liste (GET)
        $listResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/evaluations?type=Interrogation&statut=Actif');

        $listResponse->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['data', 'meta']);

        // 3. Consultation (GET details)
        $showResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/v1/evaluations/{$evalId}");

        $showResponse->assertStatus(200)
            ->assertJsonPath('data.id', $evalId)
            ->assertJsonPath('data.nom', 'Interrogation surprise N°1');

        // 4. Modification (PUT)
        $updateResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/v1/evaluations/{$evalId}", [
                'nom'         => 'Interrogation N°1 (Mise à jour)',
                'coefficient' => 3,
                'bareme'      => 40,
                'date'        => '2026-10-20',
            ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.nom', 'Interrogation N°1 (Mise à jour)')
            ->assertJsonPath('data.coefficient', 3)
            ->assertJsonPath('data.bareme', 40);

        // 5. Basculement de statut (PATCH)
        $toggleResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->patchJson("/api/v1/evaluations/{$evalId}/status");

        $toggleResponse->assertStatus(200)
            ->assertJsonPath('data.statut', 'Inactif');

        // 6. Suppression (DELETE)
        $deleteResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson("/api/v1/evaluations/{$evalId}");

        $deleteResponse->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertSoftDeleted('evaluations', ['uuid' => $evalId]);
    }
}
