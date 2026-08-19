<?php

namespace Tests\Feature;

use App\Models\AnneeCatechese;
use App\Models\Catechumene;
use App\Models\Classe;
use App\Models\InscriptionAnnuelle;
use App\Models\Niveau;
use App\Models\ParoisseConfiguration;
use App\Models\Tarif;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FinanceTest extends TestCase
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
     * Test de création d'un tarif dans la grille tarifaire.
     */
    public function test_can_create_tarif(): void
    {
        $annee = AnneeCatechese::where('libelle', '2024-2025')->first();
        $niveau = Niveau::first();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/tarifs', [
                'annee_catechese_id' => $annee->uuid,
                'niveau_id' => $niveau->uuid,
                'intitule' => 'Droit d\'inscription 1ère année',
                'montant' => 15000.00,
                'type_tarif' => 'inscription',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'intitule' => 'Droit d\'inscription 1ère année',
                    'montant' => 15000.00,
                ],
            ]);
    }

    /**
     * Test d'enregistrement d'un règlement avec génération de reçu, mise à jour de l'inscription et entrée en caisse.
     */
    public function test_can_record_payment_and_update_caisse(): void
    {
        $annee = AnneeCatechese::where('libelle', '2024-2025')->first();
        $classe = Classe::where('code', 'CLS-STJO-A')->first();

        // 1. Création d'un catéchumène et d'une inscription annuelle
        $cat = Catechumene::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'code_catechumene' => 'CAT-2024-0099',
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
        $annee = AnneeCatechese::where('paroisse_configuration_id', $this->paroisse->id)->where('est_active', true)->first();

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
}
