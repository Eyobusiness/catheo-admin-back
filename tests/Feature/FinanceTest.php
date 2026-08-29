<?php

namespace Tests\Feature;

use App\Models\AnneeCatechese;
use App\Models\Catechumene;
use App\Models\Classe;
use App\Models\InscriptionAnnuelle;
use App\Models\Niveau;
use App\Models\CatecheseConfiguration;
use App\Models\Tarif;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceTest extends TestCase
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
     * Test complet du CRUD de la grille tarifaire (Création, Lecture, Mise à jour, Toggle statut, Suppression).
     */
    public function test_can_manage_tarif_full_crud(): void
    {
        $annee = AnneeCatechese::where('libelle', '2024-2025')->first();
        $niveaux = Niveau::take(2)->get();
        $niveau = $niveaux->first();

        // 1. Create (Store)
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/tarifs', [
                'annee_catechese_id' => $annee->uuid,
                'niveau_id'          => $niveau->uuid,
                'niveau_ids'         => [$niveaux[0]->uuid, $niveaux[1]->uuid],
                'intitule'           => 'Manuel de catéchèse',
                'description'        => 'Livre obligatoire pour l\'année',
                'montant'            => 3500.00,
                'type_tarif'         => 'manuel',
                'est_obligatoire'    => true,
                'statut'             => 'actif',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.intitule', 'Manuel de catéchèse')
            ->assertJsonPath('data.type_tarif', 'manuel');

        $this->assertEquals(3500, $response->json('data.montant'));

        $tarifUuid = $response->json('data.id');

        // 2. Index (Liste avec filtres)
        $listRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/tarifs?type_tarif=manuel&search=Manuel');

        $listRes->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonCount(1, 'data');

        // 3. Show
        $showRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/tarifs/' . $tarifUuid);

        $showRes->assertStatus(200)
            ->assertJsonPath('data.intitule', 'Manuel de catéchèse');

        // 4. Update (PUT / PATCH)
        $updateRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/tarifs/' . $tarifUuid, [
                'intitule' => 'Manuel de catéchèse révisé',
                'montant'  => 4000.00,
                'type_tarif' => 'manuel',
            ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.intitule', 'Manuel de catéchèse révisé');

        $this->assertEquals(4000, $updateRes->json('data.montant'));

        // 5. Toggle Status (Actif <-> Inactif)
        $toggleRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->patchJson('/api/v1/tarifs/' . $tarifUuid . '/toggle-status');

        $toggleRes->assertStatus(200)
            ->assertJsonPath('data.statut', 'inactif');

        // 6. Delete (Destroy)
        $deleteRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/v1/tarifs/' . $tarifUuid);

        $deleteRes->assertStatus(200)
            ->assertJson(['status' => 'success']);
    }

    /**
     * Test d'enregistrement d'un règlement avec génération de reçu, mise à jour de l'inscription et entrée en caisse.
     */
    public function test_can_record_payment_and_update_caisse(): void
    {
        $annee = AnneeCatechese::where('libelle', '2024-2025')->first();
        $classe = Classe::first();

        // 1. Création d'un catéchumène et d'une inscription annuelle
        $cat = Catechumene::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'matricule' => 'CAT-2024-0099',
            'nom' => 'KOFFI',
            'prenoms' => 'Ange',
            'sexe' => 'M',
            'date_naissance' => '2015-04-12',
            'statut' => 'actif',
        ]);

        $inscription = InscriptionAnnuelle::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'catechumene_id' => $cat->id,
            'annee_catechese_id' => $annee->id,
            'niveau_id' => $classe->niveau_id,
            'classe_id' => $classe->id,
            'date_inscription' => now()->toDateString(),
            'frais_inscription_payes' => false,
        ]);

        $tarif = Tarif::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'annee_catechese_id' => $annee->id,
            'intitule' => 'Frais Inscription',
            'montant' => 15000.00,
            'type_tarif' => 'inscription',
        ]);

        // 2. Enregistrer le paiement
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/paiements', [
                'annee_catechese_id' => $annee->uuid,
                'inscription_annuelle_id' => $inscription->uuid,
                'mode_paiement' => 'especes',
                'date_paiement' => '2024-10-05',
                'lignes' => [
                    [
                        'tarif_id' => $tarif->uuid,
                        'designation' => 'Frais d\'inscription 2024-2025',
                        'montant' => 15000.00,
                        'quantite' => 1,
                    ]
                ]
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'montant_total' => 15000.00,
                ],
            ]);

        // 3. Vérifier que l'inscription a été marquée comme payée
        $this->assertTrue($inscription->fresh()->frais_inscription_payes);

        // 4. Vérifier l'écriture automatique en caisse paroissiale
        $this->assertDatabaseHas('caisse_paroissiale', [
            'type_mouvement' => 'entree',
            'categorie' => 'inscription',
            'montant' => 15000.00,
        ]);
    }

    /**
     * Test de suivi du solde du journal de caisse paroissiale.
     */
    public function test_can_get_caisse_journal_and_solde(): void
    {
        $annee = AnneeCatechese::where('libelle', '2024-2025')->first();

        // 1. Enregistrer une entrée manuelle en caisse (Don)
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/caisse-paroissiale', [
                'annee_catechese_id' => $annee->uuid,
                'type_mouvement' => 'entree',
                'categorie' => 'don',
                'montant' => 50000.00,
                'libelle' => 'Don anonyme fidèle',
                'date_mouvement' => '2024-10-10',
            ])->assertStatus(201);

        // 2. Enregistrer une sortie manuelle (Dépense)
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/caisse-paroissiale', [
                'annee_catechese_id' => $annee->uuid,
                'type_mouvement' => 'sortie',
                'categorie' => 'depense_fournitures',
                'montant' => 20000.00,
                'libelle' => 'Achat registres et craies',
                'date_mouvement' => '2024-10-12',
            ])->assertStatus(201);

        // 3. Consulter le solde de caisse
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/caisse-paroissiale');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'meta' => [
                    'total_entrees' => 50000.00,
                    'total_sorties' => 20000.00,
                    'solde_caisse' => 30000.00,
                ],
            ]);
    }

    public function test_can_rembourser_un_paiement(): void
    {
        $annee = AnneeCatechese::getAnneeCourante($this->paroisse->id);

        // 1. Enregistrer une recette en caisse
        $recette = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/caisse-paroissiale', [
                'annee_catechese_id' => $annee->uuid,
                'type_mouvement' => 'entree',
                'categorie' => 'inscription',
                'montant' => 15000.00,
                'libelle' => 'Paiement Inscription KOUASSI Jean',
                'date_mouvement' => '2024-10-15',
            ])->assertStatus(201)->json('data');

        // 2. Rembourser ce paiement
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/caisse-paroissiale/' . $recette['id'] . '/rembourser', [
                'montant_rembourse' => 15000.00,
                'motif' => 'Déménagement du catéchumène',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
            ]);
    }

    /**
     * Test de génération en masse d'opérations de paiement par tarif et paiement direct de l'opération.
     */
    public function test_can_generer_operations_par_tarif_et_payer_directement(): void
    {
        $annee = AnneeCatechese::where('libelle', '2024-2025')->first();
        $niveau = Niveau::first();

        // 1. Créer 2 catéchumènes inscrits dans ce niveau
        $cat1 = Catechumene::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'matricule' => 'CAT-2024-8801',
            'nom' => 'BAPTEME',
            'prenoms' => 'Jean',
            'sexe' => 'M',
            'date_naissance' => '2013-02-10',
            'statut' => 'actif',
        ]);

        $ins1 = InscriptionAnnuelle::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'catechumene_id' => $cat1->id,
            'annee_catechese_id' => $annee->id,
            'niveau_id' => $niveau->id,
            'date_inscription' => now()->toDateString(),
            'frais_inscription_payes' => false,
        ]);

        // 2. Créer un tarif spécifique (Frais de baptême)
        $tarif = Tarif::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'annee_catechese_id' => $annee->id,
            'niveau_id' => $niveau->id,
            'intitule' => 'Frais de Célébration Baptême',
            'montant' => 5000.00,
            'type_tarif' => 'bapteme',
            'statut' => 'actif',
        ]);

        // 3. Déclencher la génération en masse pour ce tarif
        $genRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/tarifs/' . $tarif->uuid . '/generer-operations');

        $genRes->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertGreaterThanOrEqual(1, $genRes->json('count'));

        // 4. Vérifier que l'opération en attente a été créée
        $op = \App\Models\OperationPaiement::where('catechumene_id', $cat1->id)
            ->where('tarif_id', $tarif->id)
            ->first();

        $this->assertNotNull($op);
        $this->assertEquals('en_attente', $op->statut);

        // 5. Payer directement cette opération via l'action rapide "Payer"
        $payerRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/operations-paiements/' . $op->uuid . '/payer', [
                'mode_paiement' => 'especes',
                'notes' => 'Paiement direct au guichet',
            ]);

        $payerRes->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('operation.statut', 'paye');

        $this->assertEquals('paye', $op->fresh()->statut);
        $this->assertEquals(5000, $op->fresh()->montant_paye);

        // Vérifier l'écriture dans le journal de caisse
        $this->assertDatabaseHas('caisse_paroissiale', [
            'type_mouvement' => 'entree',
            'montant' => 5000.00,
        ]);
    }

    /**
     * Test complet CRUD des versements de caisse.
     */
    public function test_can_manage_versement_crud(): void
    {
        $annee = AnneeCatechese::first();

        // 1. Créer un versement
        $res = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/versements', [
                'annee_catechese_id' => $annee->uuid,
                'periode_concernee'  => 'Octobre 2026',
                'montant_verse'      => 150000.00,
                'mode_remise'        => 'especes',
                'effectue_par'       => 'Trésorier Paroissial',
                'destinataire'       => 'Père Curé Jean-Marie',
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.periode_concernee', 'Octobre 2026')
            ->assertJsonPath('data.destinataire', 'Père Curé Jean-Marie')
            ->assertJsonPath('data.montant_verse', 150000);

        $versementUuid = $res->json('data.id');

        // 2. Liste des versements et KPIs
        $listRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/versements?search=Curé');

        $listRes->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'kpis' => ['total_en_caisse', 'total_deja_verse', 'reste_a_reverser'],
                'data',
            ]);

        // 3. Modifier le versement
        $updateRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/versements/' . $versementUuid, [
                'periode_concernee' => 'Novembre 2026',
                'destinataire'      => 'Économe Paroissial',
                'montant_verse'     => 175000.00,
            ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.periode_concernee', 'Novembre 2026')
            ->assertJsonPath('data.destinataire', 'Économe Paroissial')
            ->assertJsonPath('data.montant_verse', 175000);

        // 4. Supprimer le versement
        $delRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/v1/versements/' . $versementUuid);

        $delRes->assertStatus(200);
    }
}

