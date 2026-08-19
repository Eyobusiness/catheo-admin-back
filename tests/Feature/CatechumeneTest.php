<?php

namespace Tests\Feature;

use App\Models\AnneeCatechese;
use App\Models\CampagnePreinscription;
use App\Models\Catechumene;
use App\Models\Niveau;
use App\Models\ParoisseConfiguration;
use App\Models\Preinscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatechumeneTest extends TestCase
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
     * Test de création d'une campagne de préinscription.
     */
    public function test_can_create_campagne_preinscription(): void
    {
        $annee = AnneeCatechese::where('libelle', '2024-2025')->first();

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/campagnes-preinscriptions', [
                'annee_catechese_id' => $annee->uuid,
                'titre' => 'Campagne Rentrée 2024-2025',
                'date_debut' => '2024-09-01',
                'date_fin' => '2024-10-15',
                'statut' => 'ouverte',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'titre' => 'Campagne Rentrée 2024-2025',
                    'statut' => 'ouverte',
                ],
            ]);
    }

    /**
     * Test de soumission publique d'une préinscription.
     */
    public function test_public_can_submit_preinscription(): void
    {
        $annee = AnneeCatechese::where('libelle', '2024-2025')->first();
        $campagne = CampagnePreinscription::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'annee_catechese_id' => $annee->id,
            'titre' => 'Campagne Test',
            'date_debut' => '2024-09-01',
            'date_fin' => '2024-10-15',
            'statut' => 'ouverte',
        ]);

        $response = $this->postJson('/api/v1/preinscriptions', [
            'campagne_id' => $campagne->uuid,
            'nom' => 'KOUASSI',
            'prenoms' => 'Jean-Emmanuel',
            'sexe' => 'M',
            'date_naissance' => '2012-05-14',
            'telephone_parent' => '+225 0707070707',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'nom' => 'KOUASSI',
                    'prenoms' => 'Jean-Emmanuel',
                ],
            ]);

        $this->assertDatabaseHas('preinscriptions', ['nom' => 'KOUASSI']);
    }

    /**
     * Test de validation d'une préinscription (Génère Catéchumène + Inscription Annuelle).
     */
    public function test_admin_can_validate_preinscription(): void
    {
        $annee = AnneeCatechese::where('libelle', '2024-2025')->first();
        $niveau = Niveau::first();

        $campagne = CampagnePreinscription::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'annee_catechese_id' => $annee->id,
            'titre' => 'Campagne Validation',
            'date_debut' => '2024-09-01',
            'date_fin' => '2024-10-15',
            'statut' => 'ouverte',
        ]);

        $preinscription = Preinscription::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'campagne_preinscription_id' => $campagne->id,
            'annee_catechese_id' => $annee->id,
            'code_dossier' => 'PRE-TEST123',
            'nom' => 'KONAN',
            'prenoms' => 'Marie-Grace',
            'sexe' => 'F',
            'date_naissance' => '2014-08-20',
            'telephone_parent' => '+225 0505050505',
            'statut' => 'en_attente',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/preinscriptions/' . $preinscription->uuid . '/valider', [
                'niveau_id' => $niveau->uuid,
                'notes_validation' => 'Dossier complet et validé.',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        // Vérification de la création du catéchumène et de son inscription annuelle
        $this->assertDatabaseHas('catechumenes', [
            'nom' => 'KONAN',
            'prenoms' => 'Marie-Grace',
        ]);

        $this->assertDatabaseHas('inscriptions_annuelles', [
            'annee_catechese_id' => $annee->id,
            'niveau_id' => $niveau->id,
        ]);
    }

    /**
     * Test de création directe d'un catéchumène et recherche.
     */
    public function test_can_create_and_search_catechumene(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/catechumenes', [
                'nom' => 'YAO',
                'prenoms' => 'David',
                'sexe' => 'M',
                'date_naissance' => '2013-01-10',
                'telephone_parent' => '+225 0101010101',
            ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'nom' => 'YAO',
                    'prenoms' => 'David',
                ],
            ]);

        // Recherche par nom
        $searchResponse = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/catechumenes?search=YAO');

        $searchResponse->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }
}
