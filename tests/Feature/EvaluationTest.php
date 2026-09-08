<?php

namespace Tests\Feature;

use App\Models\AffectationAnimateur;
use App\Models\Animateur;
use App\Models\AnneeCatechese;
use App\Models\Catechumene;
use App\Models\Classe;
use App\Models\Evaluation;
use App\Models\InscriptionAnnuelle;
use App\Models\ModuleTrimestriel;
use App\Models\Niveau;
use App\Models\Note;
use App\Models\CatecheseConfiguration;
use App\Models\Seance;
use App\Models\Section;
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

    /**
     * TEST SCÉNARIOS 1 à 8 :
     * SCÉNARIO 1 : Admin Session -> Niveau -> Classe -> Création Éval 1 (Interrogation 1, Coeff 1, Note sur 20) -> Récupérer élèves -> Saisir notes -> Moyennes
     * SCÉNARIO 2 : Ajouter une 2ème évaluation (Devoir 1, Coeff 2) -> Saisie notes -> Recalcul moyennes
     * SCÉNARIO 3 : Élève sans note pour la 2ème éval -> Ne compte PAS comme zéro
     * SCÉNARIO 4 : Une seule note -> Moyenne = cette note
     * SCÉNARIO 5 : Deux notes coefficients identiques -> Moyenne arithmétique exacte
     * SCÉNARIO 6 : Trois notes avec coefficients différents -> Moyenne pondérée exacte : (14*1 + 16*2)/(1+2) = 15.33
     * SCÉNARIO 7 : Modifier une note (14 -> 17) -> Moyenne recalculée : (17*1 + 16*2)/3 = 16.33
     * SCÉNARIO 8 : Supprimer une note -> Moyenne recalculée avec les notes restantes
     */
    public function test_complete_evaluations_and_averages_scenarios_1_to_8(): void
    {
        $annee = AnneeCatechese::first();
        $section = Section::firstOrCreate(
            ['paroisse_configuration_id' => $this->paroisse->id, 'code' => 'TEST-SEC-JEUNES'],
            ['nom' => 'Jeunes', 'statut' => 'actif']
        );
        $niveau = Niveau::firstOrCreate(
            ['paroisse_configuration_id' => $this->paroisse->id, 'nom' => '3ème année'],
            ['section_id' => $section->id, 'statut' => 'actif']
        );
        $classe = Classe::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'annee_catechese_id'        => $annee->id,
            'niveau_id'                 => $niveau->id,
            'nom'                       => 'Classe 3A Test',
            'statut'                    => 'actif',
        ]);

        // Inscription de 3 élèves : Jean, Marie, Paul
        $jean = Catechumene::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'matricule'                 => 'CAT-TEST-JEAN',
            'nom'                       => 'KOUASSI',
            'prenoms'                   => 'Jean',
            'sexe'                      => 'M',
            'date_naissance'            => '2014-01-01',
            'statut'                    => 'actif',
        ]);
        InscriptionAnnuelle::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'catechumene_id'            => $jean->id,
            'annee_catechese_id'        => $annee->id,
            'section_id'                => $section->id,
            'niveau_id'                 => $niveau->id,
            'classe_id'                 => $classe->id,
            'date_inscription'          => now()->toDateString(),
            'statut_inscription'        => 'valide',
        ]);

        $marie = Catechumene::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'matricule'                 => 'CAT-TEST-MARIE',
            'nom'                       => 'KOFFI',
            'prenoms'                   => 'Marie',
            'sexe'                      => 'F',
            'date_naissance'            => '2014-02-02',
            'statut'                    => 'actif',
        ]);
        InscriptionAnnuelle::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'catechumene_id'            => $marie->id,
            'annee_catechese_id'        => $annee->id,
            'section_id'                => $section->id,
            'niveau_id'                 => $niveau->id,
            'classe_id'                 => $classe->id,
            'date_inscription'          => now()->toDateString(),
            'statut_inscription'        => 'valide',
        ]);

        $paul = Catechumene::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'matricule'                 => 'CAT-TEST-PAUL',
            'nom'                       => 'YAO',
            'prenoms'                   => 'Paul',
            'sexe'                      => 'M',
            'date_naissance'            => '2014-03-03',
            'statut'                    => 'actif',
        ]);
        InscriptionAnnuelle::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'catechumene_id'            => $paul->id,
            'annee_catechese_id'        => $annee->id,
            'section_id'                => $section->id,
            'niveau_id'                 => $niveau->id,
            'classe_id'                 => $classe->id,
            'date_inscription'          => now()->toDateString(),
            'statut_inscription'        => 'valide',
        ]);

        // ─────────────────────────────────────────────────────────────
        // SCÉNARIO 1 : Admin Session -> Niveau -> Classe -> Ajouter Évaluation 1
        // ─────────────────────────────────────────────────────────────
        $eval1Response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/evaluations', [
                'annee_catechese_id' => $annee->uuid,
                'section_id'         => $section->uuid,
                'niveau_id'          => $niveau->uuid,
                'classe_id'          => $classe->uuid,
                'titre'              => 'Interrogation 1',
                'coefficient'        => 1.0,
                'note_max'           => 20.0,
                'date_evaluation'    => '2026-09-04',
                'type_eval'          => 'interrogation',
            ]);

        $eval1Response->assertStatus(201);
        $eval1Uuid = $eval1Response->json('data.id');

        // Récupération de la liste des élèves (notes-grid)
        $gridResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/v1/evaluations/{$eval1Uuid}/notes-grid");
        $gridResponse->assertStatus(200);
        $this->assertCount(3, $gridResponse->json('data'));

        // Saisie des notes pour Éval 1 : Jean (14), Marie (18), Paul (non noté)
        $notes1Response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/evaluations/{$eval1Uuid}/notes", [
                'notes' => [
                    ['catechumene_id' => $jean->uuid, 'note_obtenue' => 14.0],
                    ['catechumene_id' => $marie->uuid, 'note_obtenue' => 18.0],
                ]
            ]);
        $notes1Response->assertStatus(200);

        // SCÉNARIO 4 & 14 : Vérifier les moyennes après Évaluation 1
        // Jean : 1 seule note = 14 -> Moyenne = 14
        // Marie : 1 seule note = 18 -> Moyenne = 18
        // Paul : aucune note -> Moyenne = null ("Non évalué")
        $moyennes1Response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/v1/evaluations/classes/{$classe->uuid}/moyennes");
        $moyennes1Response->assertStatus(200);

        $elevesM1 = collect($moyennes1Response->json('data.eleves'))->keyBy('catechumene_id');
        $this->assertEquals(14.0, $elevesM1[$jean->uuid]['moyenne']);
        $this->assertEquals(18.0, $elevesM1[$marie->uuid]['moyenne']);
        $this->assertNull($elevesM1[$paul->uuid]['moyenne']);
        $this->assertEquals('Non évalué', $elevesM1[$paul->uuid]['appreciation']);

        // ─────────────────────────────────────────────────────────────
        // SCÉNARIO 2 & 3 & 6 : Ajouter Évaluation 2 (Devoir 1, Coeff 2, Note sur 20)
        // Jean a 16. Marie n'est pas notée.
        // ─────────────────────────────────────────────────────────────
        $eval2Response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/evaluations', [
                'annee_catechese_id' => $annee->uuid,
                'classe_id'          => $classe->uuid,
                'titre'              => 'Devoir 1',
                'coefficient'        => 2.0,
                'note_max'           => 20.0,
                'date_evaluation'    => '2026-09-10',
                'type_eval'          => 'devoir',
            ]);
        $eval2Response->assertStatus(201);
        $eval2Uuid = $eval2Response->json('data.id');

        // Saisie note Éval 2 pour Jean uniquement (16)
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/evaluations/{$eval2Uuid}/notes", [
                'notes' => [
                    ['catechumene_id' => $jean->uuid, 'note_obtenue' => 16.0],
                ]
            ])->assertStatus(200);

        // Recalcul des moyennes
        $moyennes2Response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/v1/evaluations/classes/{$classe->uuid}/moyennes");
        $moyennes2Response->assertStatus(200);

        $elevesM2 = collect($moyennes2Response->json('data.eleves'))->keyBy('catechumene_id');

        // SCÉNARIO 6 : Moyenne pondérée de Jean :
        // (14 * 1 + 16 * 2) / (1 + 2) = 46 / 3 = 15.33
        $this->assertEquals(15.33, $elevesM2[$jean->uuid]['moyenne']);
        $this->assertEquals(2, $elevesM2[$jean->uuid]['nombre_notes']);

        // SCÉNARIO 3 : Marie n'a pas fait la 2ème évaluation : elle ne compte PAS comme 0 !
        // Sa moyenne reste 18.0
        $this->assertEquals(18.0, $elevesM2[$marie->uuid]['moyenne']);
        $this->assertEquals(1, $elevesM2[$marie->uuid]['nombre_notes']);

        // ─────────────────────────────────────────────────────────────
        // SCÉNARIO 5 : Deux notes avec coefficients identiques
        // Ajouter Éval 3 (Composition, Coeff 1, Note sur 20)
        // Marie obtient 14.
        // Marie a donc Éval 1 (18, coeff 1) et Éval 3 (14, coeff 1) -> (18 + 14)/2 = 16.0
        // ─────────────────────────────────────────────────────────────
        $eval3Response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/evaluations', [
                'annee_catechese_id' => $annee->uuid,
                'classe_id'          => $classe->uuid,
                'titre'              => 'Composition',
                'coefficient'        => 1.0,
                'note_max'           => 20.0,
                'date_evaluation'    => '2026-09-15',
                'type_eval'          => 'composition',
            ]);
        $eval3Response->assertStatus(201);
        $eval3Uuid = $eval3Response->json('data.id');

        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/evaluations/{$eval3Uuid}/notes", [
                'notes' => [
                    ['catechumene_id' => $marie->uuid, 'note_obtenue' => 14.0],
                ]
            ])->assertStatus(200);

        $moyennes3Response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/v1/evaluations/classes/{$classe->uuid}/moyennes");
        $elevesM3 = collect($moyennes3Response->json('data.eleves'))->keyBy('catechumene_id');

        // SCÉNARIO 5 : (18 + 14) / 2 = 16.0
        $this->assertEquals(16.0, $elevesM3[$marie->uuid]['moyenne']);

        // ─────────────────────────────────────────────────────────────
        // SCÉNARIO 7 : Modifier une note (Jean : 14 -> 17 sur Éval 1)
        // Nouvelle moyenne de Jean : (17 * 1 + 16 * 2) / 3 = 49 / 3 = 16.33
        // ─────────────────────────────────────────────────────────────
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/evaluations/{$eval1Uuid}/notes", [
                'notes' => [
                    ['catechumene_id' => $jean->uuid, 'note_obtenue' => 17.0],
                ]
            ])->assertStatus(200);

        $moyennesModifResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/v1/evaluations/classes/{$classe->uuid}/moyennes");
        $elevesModif = collect($moyennesModifResponse->json('data.eleves'))->keyBy('catechumene_id');

        $this->assertEquals(16.33, $elevesModif[$jean->uuid]['moyenne']);

        // ─────────────────────────────────────────────────────────────
        // SCÉNARIO 8 : Supprimer une note (Supprimer la note de 16 de Jean sur Éval 2)
        // En envoyant null pour la note dans batch save
        // Jean n'a plus que sa note de 17 sur Éval 1 -> moyenne = 17.0
        // ─────────────────────────────────────────────────────────────
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/evaluations/{$eval2Uuid}/notes", [
                'notes' => [
                    ['catechumene_id' => $jean->uuid, 'note_obtenue' => null],
                ]
            ])->assertStatus(200);

        $moyennesSupprResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/v1/evaluations/classes/{$classe->uuid}/moyennes");
        $elevesSuppr = collect($moyennesSupprResponse->json('data.eleves'))->keyBy('catechumene_id');

        $this->assertEquals(17.0, $elevesSuppr[$jean->uuid]['moyenne']);
        $this->assertEquals(1, $elevesSuppr[$jean->uuid]['nombre_notes']);

        // Vérifier également l'API de synthèse individuelle pour bulletin (SCÉNARIO 31)
        $syntheseResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/v1/evaluations/catechumenes/{$jean->uuid}/synthese");
        $syntheseResponse->assertStatus(200)
            ->assertJsonPath('data.catechumene.matricule', 'CAT-TEST-JEAN');
        $this->assertEquals(17.0, $syntheseResponse->json('data.moyenne'));
    }

    /**
     * TEST SCÉNARIO 9 : Enseignant (Animateur) tente d'accéder ou créer une évaluation dans une autre classe -> 403 Forbidden.
     */
    public function test_scenario_9_teacher_cannot_access_or_create_for_unassigned_class(): void
    {
        $annee = AnneeCatechese::first();
        $niveau = Niveau::first();

        // Classe 1 (Assignée à l'animateur)
        $classeAutorisee = Classe::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'annee_catechese_id'        => $annee->id,
            'niveau_id'                 => $niveau->id,
            'nom'                       => 'Classe Animateur Autorisee',
            'statut'                    => 'actif',
        ]);

        // Classe 2 (Non assignée)
        $classeInterdite = Classe::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'annee_catechese_id'        => $annee->id,
            'niveau_id'                 => $niveau->id,
            'nom'                       => 'Classe Animateur Interdite',
            'statut'                    => 'actif',
        ]);

        // Créer un animateur et l'affecter uniquement à la Classe 1
        $animateur = Animateur::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'nom'                       => 'KOUADIO',
            'prenoms'                   => 'Michel',
            'telephone'                 => '0700000099',
            'email'                     => 'michel.anim@catheo.ci',
            'password'                  => bcrypt('password123'),
            'statut'                    => 'actif',
        ]);

        AffectationAnimateur::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'animateur_id'              => $animateur->id,
            'annee_catechese_id'        => $annee->id,
            'classe_id'                 => $classeAutorisee->id,
            'role_animateur'            => 'titulaire',
        ]);

        $animToken = $animateur->createToken('AnimTest')->plainTextToken;

        // 1. L'animateur crée une évaluation dans SA classe autorisée -> 201 Created
        $responseOk = $this->withHeader('Authorization', 'Bearer ' . $animToken)
            ->postJson('/api/v1/evaluations', [
                'annee_catechese_id' => $annee->uuid,
                'classe_id'          => $classeAutorisee->uuid,
                'titre'              => 'Évaluation de ma classe',
                'coefficient'        => 1,
                'note_max'           => 20,
                'date_evaluation'    => '2026-09-04',
            ]);
        $responseOk->assertStatus(201);

        // 2. L'animateur tente de créer une évaluation dans l'AUTRE classe -> 403 Forbidden
        $responseForbiddenCreate = $this->withHeader('Authorization', 'Bearer ' . $animToken)
            ->postJson('/api/v1/evaluations', [
                'annee_catechese_id' => $annee->uuid,
                'classe_id'          => $classeInterdite->uuid,
                'titre'              => 'Évaluation frauduleuse',
                'coefficient'        => 1,
                'note_max'           => 20,
                'date_evaluation'    => '2026-09-04',
            ]);
        $responseForbiddenCreate->assertStatus(403);

        // 3. L'animateur tente de consulter les moyennes de l'autre classe -> 403 Forbidden
        $responseForbiddenMoyennes = $this->withHeader('Authorization', 'Bearer ' . $animToken)
            ->getJson("/api/v1/evaluations/classes/{$classeInterdite->uuid}/moyennes");
        $responseForbiddenMoyennes->assertStatus(403);

        // 4. L'animateur filtre par l'autre classe -> 403 Forbidden
        $responseFilterForbidden = $this->withHeader('Authorization', 'Bearer ' . $animToken)
            ->getJson("/api/v1/evaluations?classe_id={$classeInterdite->uuid}");
        $responseFilterForbidden->assertStatus(403);
    }

    /**
     * TEST SCÉNARIO 10 : Utilisateur tente d'accéder à une évaluation d'une autre paroisse -> 403 Forbidden.
     */
    public function test_scenario_10_user_cannot_access_another_parish(): void
    {
        // Créer une autre paroisse
        $autreParoisse = CatecheseConfiguration::create([
            'code_paroisse'       => 'PAR-AUTRE-99',
            'nom_paroisse'        => 'Paroisse Saint Joseph',
            'diocese'             => 'Yopougon',
            'telephone_principal' => '0102030405',
            'statut'              => 'actif',
        ]);

        $anneeAutre = AnneeCatechese::create([
            'paroisse_configuration_id' => $autreParoisse->id,
            'libelle'                   => '2026-2027',
            'date_debut'                => '2026-09-01',
            'date_fin'                  => '2027-06-30',
            'statut'                    => 'actif',
        ]);

        $sectionAutre = Section::create([
            'paroisse_configuration_id' => $autreParoisse->id,
            'code'                      => 'SEC-AUTRE',
            'nom'                       => 'Section Autre',
            'statut'                    => 'actif',
        ]);

        $niveauAutre = Niveau::create([
            'paroisse_configuration_id' => $autreParoisse->id,
            'section_id'                => $sectionAutre->id,
            'nom'                       => 'Niveau Autre',
            'statut'                    => 'actif',
        ]);

        $classeAutre = Classe::create([
            'paroisse_configuration_id' => $autreParoisse->id,
            'annee_catechese_id'        => $anneeAutre->id,
            'niveau_id'                 => $niveauAutre->id,
            'nom'                       => 'Classe Paroisse Autre',
            'statut'                    => 'actif',
        ]);

        $evalAutre = Evaluation::create([
            'paroisse_configuration_id' => $autreParoisse->id,
            'annee_catechese_id'        => $anneeAutre->id,
            'classe_id'                 => $classeAutre->id,
            'titre'                     => 'Éval paroisse 2',
            'coefficient'               => 1,
            'note_max'                  => 20,
            'date_evaluation'           => '2026-09-04',
        ]);

        // L'admin de St-Paul tente d'accéder à l'évaluation de St-Joseph -> 403 Forbidden
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/v1/evaluations/{$evalAutre->uuid}");

        $response->assertStatus(403);
    }

    /**
     * TEST VALIDATION : Une note dépassant le barème (note_max) doit être rejetée avec erreur 422.
     */
    public function test_validation_rejects_notes_exceeding_note_max(): void
    {
        $classe = Classe::first();
        $annee = AnneeCatechese::first();

        $eval = Evaluation::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'annee_catechese_id'        => $annee->id,
            'classe_id'                 => $classe->id,
            'titre'                     => 'Éval Barème 20',
            'coefficient'               => 1,
            'note_max'                  => 20,
            'date_evaluation'           => '2026-09-04',
        ]);

        $cat = Catechumene::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'matricule'                 => 'CAT-TEST-VALID',
            'nom'                       => 'TEST',
            'prenoms'                   => 'Val',
            'sexe'                      => 'M',
            'date_naissance'            => '2014-01-01',
            'statut'                    => 'actif',
        ]);

        // Tentative de saisie d'une note de 25/20
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/evaluations/{$eval->uuid}/notes", [
                'notes' => [
                    ['catechumene_id' => $cat->uuid, 'note_obtenue' => 25.0],
                ]
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['notes']);

        // Tentative de saisie d'une note négative (-2)
        $responseNegative = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson("/api/v1/evaluations/{$eval->uuid}/notes", [
                'notes' => [
                    ['catechumene_id' => $cat->uuid, 'note_obtenue' => -2.0],
                ]
            ]);

        $responseNegative->assertStatus(422)
            ->assertJsonValidationErrors(['notes.0.note_obtenue']);
    }

    /**
     * TEST FILTRAGE : Filtrage par Session (Section) et par Niveau.
     */
    public function test_filtering_evaluations_by_session_and_niveau(): void
    {
        $sectionJeunes = Section::where('code', 'SEC-JEU')->first() ?? Section::first();
        $niveau = Niveau::where('section_id', $sectionJeunes->id)->first() ?? Niveau::first();
        $classe = Classe::where('niveau_id', $niveau->id)->first() ?? Classe::first();
        $annee = AnneeCatechese::first();

        $eval = Evaluation::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'annee_catechese_id'        => $annee->id,
            'classe_id'                 => $classe->id,
            'titre'                     => 'Éval Filtre Contextuel',
            'coefficient'               => 1,
            'note_max'                  => 20,
            'date_evaluation'           => '2026-09-04',
        ]);

        // Filtrage par session / section
        $responseSec = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/v1/evaluations?section_id={$sectionJeunes->uuid}");
        $responseSec->assertStatus(200);

        $evals = collect($responseSec->json('data'));
        $this->assertTrue($evals->contains('id', $eval->uuid));

        // Filtrage par niveau
        $responseNiv = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/v1/evaluations?niveau_id={$niveau->uuid}");
        $responseNiv->assertStatus(200);

        $evalsNiv = collect($responseNiv->json('data'));
        $this->assertTrue($evalsNiv->contains('id', $eval->uuid));
    }
}

