<?php

namespace Tests\Feature;

use App\Models\AnneeCatechese;
use App\Models\Catechumene;
use App\Models\Classe;
use App\Models\Evaluation;
use App\Models\InscriptionAnnuelle;
use App\Models\ModuleTrimestriel;
use App\Models\Niveau;
use App\Models\ParoisseConfiguration;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvaluationTest extends TestCase
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
     * Test de planification d'une séance et de l'appel par lot des présences.
     */
    public function test_can_plan_seance_and_record_batch_presences(): void
    {
        $annee = AnneeCatechese::where('libelle', '2024-2025')->first();
        $classe = Classe::where('code', 'CLS-STJO-A')->first();
        $module = ModuleTrimestriel::where('numero_trimestre', 1)->first();

        // 1. Création d'un catéchumène inscrit dans la classe
        $catechumene = Catechumene::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'code_catechumene' => 'CAT-2024-0001',
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

        // 2. Planifier la séance
        $seanceResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/seances', [
                'annee_catechese_id' => $annee->uuid,
                'classe_id' => $classe->uuid,
                'module_trimestriel_id' => $module->uuid,
                'titre' => 'Séance 1 : La création du monde',
                'date_seance' => '2024-10-05',
                'heure_debut' => '09:00',
                'heure_fin' => '11:00',
            ]);

        $seanceResponse->assertStatus(201);
        $seanceUuid = $seanceResponse->json('data.id');

        // 3. Saisie des présences en lot
        $presenceResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/seances/{$seanceUuid}/presences", [
                'presences' => [
                    [
                        'catechumene_id' => $catechumene->uuid,
                        'statut_presence' => 'present',
                    ]
                ]
            ]);

        $presenceResponse->assertStatus(200);
        $this->assertDatabaseHas('presences', ['statut_presence' => 'present']);
    }

    /**
     * Test de création d'une évaluation, saisie des notes en lot et calcul du bulletin trimestriel.
     */
    public function test_can_create_eval_record_notes_and_calculate_bulletin(): void
    {
        $annee = AnneeCatechese::where('libelle', '2024-2025')->first();
        $classe = Classe::where('code', 'CLS-STJO-A')->first();
        $module = ModuleTrimestriel::where('numero_trimestre', 1)->first();

        // 1. Inscrire 2 catéchumènes dans la classe
        $cat1 = Catechumene::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'code_catechumene' => 'CAT-2024-0010',
            'nom' => 'BAMBA',
            'prenoms' => 'Awa',
            'sexe' => 'F',
            'date_naissance' => '2015-06-12',
            'statut' => 'actif',
        ]);

        $cat2 = Catechumene::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'code_catechumene' => 'CAT-2024-0011',
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
}
