<?php

namespace Tests\Feature;

use App\Models\AnneeCatechese;
use App\Models\CampagnePreinscription;
use App\Models\Catechumene;
use App\Models\Niveau;
use App\Models\CatecheseConfiguration;
use App\Models\Preinscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatechumeneTest extends TestCase
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

    public function test_preinscription_validation_uses_configured_tarif_and_no_fake_default(): void
    {
        $annee = AnneeCatechese::where('libelle', '2024-2025')->first();
        // Utiliser le deuxième niveau pour éviter le tarif déjà semé sur le niveau 1
        $niveau = Niveau::skip(1)->first() ?? Niveau::factory()->create();

        // Création d'un tarif personnalisé spécifique
        $tarif = \App\Models\Tarif::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'annee_catechese_id'        => $annee->id,
            'niveau_id'                 => $niveau->id,
            'intitule'                  => 'Frais Catéchèse 2ème Année',
            'montant'                   => 8500.00,
            'type_tarif'                => 'inscription',
            'statut'                    => 'actif',
        ]);

        $campagne = CampagnePreinscription::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'annee_catechese_id'        => $annee->id,
            'titre'                     => 'Campagne Test Tarif',
            'date_debut'                => '2024-09-01',
            'date_fin'                  => '2024-10-15',
            'statut'                    => 'ouverte',
        ]);

        $preinscription = Preinscription::create([
            'paroisse_configuration_id'  => $this->paroisse->id,
            'campagne_preinscription_id' => $campagne->id,
            'annee_catechese_id'         => $annee->id,
            'code_dossier'               => 'PRE-TARIF-01',
            'nom'                        => 'YAO',
            'prenoms'                    => 'Kouassi',
            'sexe'                       => 'M',
            'date_naissance'             => '2015-01-10',
            'statut'                     => 'en_attente',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/preinscriptions/' . $preinscription->uuid . '/valider', [
                'niveau_id'        => $niveau->uuid,
                'notes_validation' => 'Validé avec tarif configuré.',
            ]);

        $response->assertStatus(200);

        // L'opération créée doit avoir le bon tarif_id et le montant configuré (8500), PAS 15000 ni null tarif_id
        $this->assertDatabaseHas('operations_paiements', [
            'paroisse_configuration_id' => $this->paroisse->id,
            'tarif_id'                  => $tarif->id,
            'montant'                   => 8500.00,
            'statut'                    => 'en_attente',
        ]);
    }

    /**
     * Test complet du CRUD pour les campagnes de préinscription.
     */
    public function test_can_manage_campagne_crud_and_status(): void
    {
        $annee = AnneeCatechese::where('libelle', '2024-2025')->first();

        // 1. Créer une campagne
        $createRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/campagnes-preinscriptions', [
                'annee_catechese_id' => $annee->uuid,
                'titre' => 'Campagne CRUD Test',
                'date_debut' => '2024-09-01',
                'date_fin' => '2024-11-30',
                'statut' => 'ouverte',
                'description' => 'Test description',
                'sections_autorisees' => ['Enfants', 'Jeunes'],
            ]);

        $createRes->assertStatus(201);
        $campagneId = $createRes->json('data.id');

        // 2. Obtenir les détails
        $showRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/campagnes-preinscriptions/' . $campagneId);

        $showRes->assertStatus(200)
            ->assertJsonPath('data.titre', 'Campagne CRUD Test')
            ->assertJsonPath('data.est_ouverte', true);

        // 3. Modifier la campagne
        $updateRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/campagnes-preinscriptions/' . $campagneId, [
                'titre' => 'Campagne CRUD Test Modifiée',
                'description' => 'Updated description',
            ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.titre', 'Campagne CRUD Test Modifiée')
            ->assertJsonPath('data.description', 'Updated description');

        // 4. Basculer le statut
        $statusRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->patchJson('/api/v1/campagnes-preinscriptions/' . $campagneId . '/status', [
                'statut' => 'fermee',
            ]);

        $statusRes->assertStatus(200)
            ->assertJsonPath('data.statut', 'fermee')
            ->assertJsonPath('data.est_ouverte', false);

        // 5. Supprimer la campagne
        $deleteRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/v1/campagnes-preinscriptions/' . $campagneId);

        $deleteRes->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Campagne supprimée avec succès.',
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

    /**
     * Test complet du CRUD et statut pour les préinscriptions.
     */
    public function test_can_manage_preinscription_crud_and_status(): void

    {
        $annee = AnneeCatechese::where('libelle', '2024-2025')->first();
        $campagne = CampagnePreinscription::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'annee_catechese_id' => $annee->id,
            'titre' => 'Campagne CRUD Preins',
            'date_debut' => '2024-09-01',
            'date_fin' => '2024-10-15',
            'statut' => 'ouverte',
        ]);

        // 1. Enregistrer via API admin
        $createRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/preinscriptions', [
                'campagne_id' => $campagne->uuid,
                'nom' => 'KRA',
                'prenoms' => 'Cedric',
                'sexe' => 'M',
                'date_naissance' => '2013-07-22',
                'telephone' => '+225 0708091011',
                'type_demande' => 'nouvelle_inscription',
            ]);

        $createRes->assertStatus(201);
        $preinscriptionId = $createRes->json('data.id');

        // 2. Afficher
        $showRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/preinscriptions/' . $preinscriptionId);

        $showRes->assertStatus(200)
            ->assertJsonPath('data.nom', 'KRA')
            ->assertJsonPath('data.statut', 'en_attente');

        // 3. Modifier
        $updateRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/preinscriptions/' . $preinscriptionId, [
                'prenoms' => 'Cedric Junior',
                'adresse' => 'Abidjan Cocody',
            ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.prenoms', 'Cedric Junior')
            ->assertJsonPath('data.adresse', 'Abidjan Cocody');

        // 4. Modifier Statut (statut / rejet)
        $statutRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->patchJson('/api/v1/preinscriptions/' . $preinscriptionId . '/statut', [
                'statut' => 'rejetee',
                'notes_validation' => 'Dossier incomplet.',
            ]);

        $statutRes->assertStatus(200)
            ->assertJsonPath('data.statut', 'rejetee');

        // 5. Supprimer
        $deleteRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/v1/preinscriptions/' . $preinscriptionId);

        $deleteRes->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);
    }

    /**
     * Test de validation d'une réinscription rattachée à un catéchumène existant.
     */
    public function test_admin_can_validate_reinscription_without_duplicating_catechumene(): void
    {
        $annee = AnneeCatechese::where('libelle', '2024-2025')->first();
        $niveau = Niveau::first();

        // 1. Catéchumène préexistant
        $catExistant = Catechumene::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'matricule' => 'CAT-2023-9999',
            'nom' => 'TOURE',
            'prenoms' => 'Amina',
            'sexe' => 'F',
            'date_naissance' => '2012-03-10',
            'telephone' => '+225 0102030499',
            'statut' => 'actif',
        ]);

        $campagne = CampagnePreinscription::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'annee_catechese_id' => $annee->id,
            'titre' => 'Campagne Réinscriptions',
            'date_debut' => '2024-09-01',
            'date_fin' => '2024-10-15',
            'statut' => 'ouverte',
        ]);

        // 2. Préinscription de type réinscription
        $preinscription = Preinscription::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'campagne_preinscription_id' => $campagne->id,
            'annee_catechese_id' => $annee->id,
            'code_dossier' => 'PRE-REINS-01',
            'type_demande' => 'reinscription',
            'nom' => 'TOURE',
            'prenoms' => 'Amina',
            'sexe' => 'F',
            'date_naissance' => '2012-03-10',
            'telephone' => '+225 0102030499',
            'statut' => 'en_attente',
        ]);

        // 3. Validation
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/preinscriptions/' . $preinscription->uuid . '/valider', [
                'niveau_id' => $niveau->uuid,
                'catechumene_id' => $catExistant->uuid,
                'notes_validation' => 'Réinscription validée.',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.matricule', 'CAT-2023-9999');

        // Vérifier qu'aucun doublon de catéchumène n'a été créé
        $count = Catechumene::where('paroisse_configuration_id', $this->paroisse->id)
            ->where('nom', 'TOURE')
            ->count();

        $this->assertEquals(1, $count);

        // Vérifier que l'inscription annuelle a été créée
        $this->assertDatabaseHas('inscriptions_annuelles', [
            'catechumene_id' => $catExistant->id,
            'annee_catechese_id' => $annee->id,
        ]);
    }

    /**
     * Test complet du CRUD des Mutations / Transferts de Catéchumènes.
     */
    public function test_can_manage_mutations_catechumenes_crud_and_approval(): void
    {
        $annee = AnneeCatechese::first();
        $cat = Catechumene::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'matricule' => 'CAT-2024-5555',
            'nom' => 'KASSI',
            'prenoms' => 'Marc',
            'sexe' => 'M',
            'date_naissance' => '2014-05-15',
            'statut' => 'actif',
        ]);

        // 1. Create (Demande de mutation)
        $storeRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/mutations-catechumenes', [
                'catechumene_id' => $cat->uuid,
                'annee_catechese_id' => $annee->uuid,
                'paroisse_origine_nom' => 'Saint Jean Cocody',
                'paroisse_destination_nom' => 'Notre Dame du Perpétuel Secours',
                'motif' => 'Déménagement familial à Treichville',
                'date_mutation' => '2024-11-01',
                'statut' => 'demande',
            ]);

        $storeRes->assertStatus(201)
            ->assertJsonPath('data.paroisse_destination_nom', 'Notre Dame du Perpétuel Secours')
            ->assertJsonPath('data.statut', 'demande');

        $mutationUuid = $storeRes->json('data.id');

        // 2. Index
        $listRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/mutations-catechumenes?search=KASSI');

        $listRes->assertStatus(200)
            ->assertJsonCount(1, 'data');

        // 3. Show
        $showRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/mutations-catechumenes/' . $mutationUuid);

        $showRes->assertStatus(200)
            ->assertJsonPath('data.matricule', 'CAT-2024-5555');

        // 4. Update Status (Approuver)
        $statusRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->patchJson('/api/v1/mutations-catechumenes/' . $mutationUuid . '/statut', [
                'statut' => 'approuve',
            ]);

        $statusRes->assertStatus(200)
            ->assertJsonPath('data.statut', 'approuve');

        // Le statut du catéchumène doit être passé à 'transfere'
        $this->assertEquals('transfere', $cat->fresh()->statut);

        // 5. Update (Modification des détails)
        $updateRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/mutations-catechumenes/' . $mutationUuid, [
                'motif' => 'Déménagement confirmé à Treichville',
            ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.motif', 'Déménagement confirmé à Treichville');

        // 6. Delete
        $deleteRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/v1/mutations-catechumenes/' . $mutationUuid);

        $deleteRes->assertStatus(200);

        // Le statut du catéchumène doit être restauré à 'actif'
        $this->assertEquals('actif', $cat->fresh()->statut);
    }

    /**
     * Test d'affectation individuelle et par lot d'un catéchumène à une classe.
     */
    public function test_can_affect_catechumene_to_classe(): void
    {
        $annee = AnneeCatechese::where('libelle', '2024-2025')->first();
        $niveau = Niveau::first();
        $classe = \App\Models\Classe::where('niveau_id', $niveau->id)->first();

        $cat = Catechumene::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'matricule' => 'CAT-2024-9999',
            'nom' => 'AFFECTATION',
            'prenoms' => 'Test',
            'sexe' => 'M',
            'date_naissance' => '2014-01-01',
            'telephone' => '+225 0101010101',
            'statut' => 'actif',
        ]);

        $inscription = \App\Models\InscriptionAnnuelle::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'catechumene_id' => $cat->id,
            'annee_catechese_id' => $annee->id,
            'section_id' => $niveau->section_id,
            'niveau_id' => $niveau->id,
            'code_inscription' => 'INS-2024-9999',
            'statut_inscription' => 'valide',
        ]);

        // 1. Test affectation via PUT /api/v1/inscriptions-annuelles/{uuid}
        $putRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/inscriptions-annuelles/' . $inscription->uuid, [
                'classe_id' => $classe->uuid,
            ]);

        $putRes->assertStatus(200)
            ->assertJsonPath('data.classe_id', $classe->uuid);

        $this->assertEquals($classe->id, $inscription->fresh()->classe_id);

        // 2. Test affectation via POST /api/v1/inscriptions-annuelles/affecter (par lot ou direct)
        $batchRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/inscriptions-annuelles/affecter', [
                'classe_id' => $classe->uuid,
                'inscription_ids' => [$inscription->uuid],
            ]);

        $batchRes->assertStatus(200)
            ->assertJson(['status' => 'success']);

        $this->assertEquals($classe->id, $inscription->fresh()->classe_id);
    }
}



