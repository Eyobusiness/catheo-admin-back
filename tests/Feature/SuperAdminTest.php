<?php

namespace Tests\Feature;

use App\Models\Abonnement;
use App\Models\CatecheseConfiguration;
use App\Models\EcheanceAbonnement;
use App\Models\Facture;
use App\Models\Formule;
use App\Models\Organisation;
use App\Models\PaiementAbonnement;
use App\Models\Produit;
use App\Models\User;
use Database\Seeders\ProduitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SuperAdminTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $normalUser;
    protected User $orgUser;
    protected CatecheseConfiguration $paroisseA;
    protected CatecheseConfiguration $paroisseB;
    protected Produit $catheo;
    protected Produit $oppe;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Initialiser les 4 produits
        $this->seed(ProduitSeeder::class);
        $this->catheo = Produit::where('code', Produit::CODE_CATHEO)->firstOrFail();
        $this->oppe   = Produit::where('code', Produit::CODE_OPPE)->firstOrFail();

        // 2. Créer deux paroisses
        $this->paroisseA = CatecheseConfiguration::create([
            'nom_paroisse'      => 'Paroisse Sainte Famille',
            'code_paroisse'     => 'PAR-FAM-' . uniqid(),
            'prefixe_matricule' => 'SF',
            'prefixe_recu'      => 'REC',
            'statut'            => 'actif',
            'ville'             => 'Abidjan',
            'diocese'           => 'Abidjan',
        ]);

        $this->paroisseB = CatecheseConfiguration::create([
            'nom_paroisse'      => 'Paroisse Saint Jean',
            'code_paroisse'     => 'PAR-JEA-' . uniqid(),
            'prefixe_matricule' => 'SJ',
            'prefixe_recu'      => 'REC',
            'statut'            => 'actif',
            'ville'             => 'Bouaké',
            'diocese'           => 'Bouaké',
        ]);

        // 3. Créer un Super Admin
        $this->superAdmin = User::create([
            'name'                      => 'Super Admin Test',
            'email'                     => 'superadmin.' . uniqid() . '@catheo.ci',
            'password'                  => Hash::make('SuperAdminPass123!'),
            'user_type'                 => 'super_admin',
            'statut'                    => 'actif',
            'paroisse_configuration_id' => null,
            'organisation_id'           => null,
        ]);

        // 4. Créer un utilisateur CATHEO normal (administrateur de paroisse)
        $this->normalUser = User::create([
            'name'                      => 'Admin Paroissial',
            'email'                     => 'admin.paroisse.' . uniqid() . '@catheo.ci',
            'password'                  => Hash::make('AdminPass123!'),
            'user_type'                 => 'admin',
            'statut'                    => 'actif',
            'paroisse_configuration_id' => $this->paroisseA->id,
            'organisation_id'           => null,
        ]);

        // 5. Créer un utilisateur Organisation
        $org = Organisation::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'produit_id'                => $this->oppe->id,
            'type_organisation'         => Organisation::TYPE_OPPE,
            'code'                      => 'OPPE-SF',
            'nom'                       => 'OPPE Sainte Famille',
            'statut'                    => 'actif',
        ]);

        $this->orgUser = User::create([
            'name'                      => 'Membre Organisation',
            'email'                     => 'org.user.' . uniqid() . '@catheo.ci',
            'password'                  => Hash::make('OrgPass123!'),
            'user_type'                 => 'utilisateur',
            'statut'                    => 'actif',
            'paroisse_configuration_id' => $this->paroisseA->id,
            'organisation_id'           => $org->id,
        ]);
    }

    // =========================================================================
    // SECTION 1 : SÉCURITÉ & CONTRÔLE D'ACCÈS
    // =========================================================================

    public function test_unauthenticated_user_cannot_access_super_admin(): void
    {
        $response = $this->getJson('/api/v1/super-admin/dashboard');
        $response->assertStatus(401);
    }

    public function test_normal_catheo_user_is_forbidden(): void
    {
        Sanctum::actingAs($this->normalUser);

        $response = $this->getJson('/api/v1/super-admin/dashboard');
        $response->assertStatus(403)
            ->assertJson([
                'status'  => 'error',
                'message' => 'Accès refusé. Cet espace est strictement réservé au Super Administrateur de la plateforme.',
            ]);
    }

    public function test_organisation_user_is_forbidden(): void
    {
        Sanctum::actingAs($this->orgUser);

        $response = $this->getJson('/api/v1/super-admin/produits');
        $response->assertStatus(403);
    }

    public function test_super_admin_has_full_access(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->getJson('/api/v1/super-admin/dashboard');
        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);
    }

    // =========================================================================
    // SECTION 2 : GESTION DES PRODUITS SAAS
    // =========================================================================

    public function test_super_admin_can_list_products(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $response = $this->getJson('/api/v1/super-admin/produits');
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => ['id', 'code', 'nom', 'statut', 'formules_count'],
                ],
            ]);

        $this->assertCount(4, $response->json('data'));
    }

    public function test_super_admin_can_create_product_and_duplicate_code_is_rejected(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Création réussie
        $response = $this->postJson('/api/v1/super-admin/produits', [
            'code'        => 'NOUVEAU_PROD',
            'nom'         => 'Nouveau Produit SaaS',
            'description' => 'Description du nouveau produit',
            'statut'      => 'actif',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'data'   => [
                    'code' => 'NOUVEAU_PROD',
                    'nom'  => 'Nouveau Produit SaaS',
                ],
            ]);

        $this->assertDatabaseHas('produits', ['code' => 'NOUVEAU_PROD']);

        // Tentative de doublon de code rejetée
        $duplicateResponse = $this->postJson('/api/v1/super-admin/produits', [
            'code' => 'NOUVEAU_PROD',
            'nom'  => 'Autre Produit avec Même Code',
        ]);

        $duplicateResponse->assertStatus(422);
    }

    public function test_super_admin_can_update_and_toggle_product_status(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Update
        $updateResponse = $this->putJson("/api/v1/super-admin/produits/{$this->catheo->id}", [
            'nom'         => 'CATHEO Pro Edition',
            'description' => 'Gestion paroissiale complète',
        ]);

        $updateResponse->assertStatus(200)
            ->assertJson([
                'data' => [
                    'nom' => 'CATHEO Pro Edition',
                ],
            ]);

        $this->assertSame('CATHEO Pro Edition', $this->catheo->fresh()->nom);

        // Toggle status
        $toggleResponse = $this->patchJson("/api/v1/super-admin/produits/{$this->catheo->id}/toggle-status");
        $toggleResponse->assertStatus(200);
        $this->assertSame('inactif', $this->catheo->fresh()->statut);
    }

    // =========================================================================
    // SECTION 3 : GESTION DES FORMULES TARIFAIRES
    // =========================================================================

    public function test_super_admin_can_create_paid_and_free_formulas(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // 1. Formule payante
        $respPayante = $this->postJson('/api/v1/super-admin/formules', [
            'produit_id'   => $this->catheo->id,
            'code'         => 'ANNUEL_STANDARD',
            'nom'          => 'Formule Annuelle Standard',
            'periodicite'  => 'annuelle',
            'montant'      => 120000.00,
            'devise'       => 'XOF',
            'est_gratuite' => false,
            'statut'       => 'actif',
        ]);

        $respPayante->assertStatus(201)
            ->assertJson([
                'data' => [
                    'code'         => 'ANNUEL_STANDARD',
                    'montant'      => 120000.00,
                    'est_gratuite' => false,
                ],
            ]);

        // 2. Doublon de code pour le même produit refusé
        $respDoublon = $this->postJson('/api/v1/super-admin/formules', [
            'produit_id'  => $this->catheo->id,
            'code'        => 'ANNUEL_STANDARD',
            'nom'         => 'Doublon Formule',
            'periodicite' => 'annuelle',
            'montant'     => 50000,
        ]);
        $respDoublon->assertStatus(500); // InvalidArgumentException gérée

        // 3. Formule gratuite
        $respGratuite = $this->postJson('/api/v1/super-admin/formules', [
            'produit_id'   => $this->oppe->id,
            'code'         => 'DECOUVERTE',
            'nom'          => 'Formule Découverte Gratuite',
            'periodicite'  => 'mensuelle',
            'est_gratuite' => true,
            'montant'      => 99999, // Doit être écrasé à 0 car est_gratuite = true
            'statut'       => 'actif',
        ]);

        $respGratuite->assertStatus(201)
            ->assertJson([
                'data' => [
                    'code'         => 'DECOUVERTE',
                    'montant'      => 0.00,
                    'est_gratuite' => true,
                ],
            ]);
    }

    // =========================================================================
    // SECTION 4 : GESTION DES ABONNEMENTS
    // =========================================================================

    public function test_subscription_creation_with_price_snapshot_and_billing_pipeline(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $formule = Formule::create([
            'produit_id'   => $this->catheo->id,
            'code'         => 'STANDARD_CATHEO',
            'nom'          => 'Standard Catheo',
            'periodicite'  => Formule::PERIODICITE_ANNUELLE,
            'montant'      => 100000.00,
            'devise'       => 'XOF',
            'est_gratuite' => false,
            'statut'       => 'actif',
        ]);

        // Souscription de Paroisse A
        $response = $this->postJson('/api/v1/super-admin/abonnements', [
            'paroisse_configuration_id' => $this->paroisseA->id,
            'formule_id'                => $formule->id,
            'date_debut'                => '2026-10-01',
            'renouvellement_automatique'=> true,
            'observation'               => 'Souscription initiale',
        ]);

        $response->assertStatus(201);
        $aboData = $response->json('data');

        $this->assertStringStartsWith('ABO-', $aboData['reference']);
        $this->assertEquals(100000.00, $aboData['montant']);
        $this->assertSame(Abonnement::STATUT_EN_ATTENTE, $aboData['statut']);

        // Vérifier qu'une échéance initiale et une facture ont été générées automatiquement
        $this->assertDatabaseHas('echeances_abonnement', [
            'abonnement_id' => $aboData['id_interne'],
            'montant'       => 100000.00,
            'statut'        => EcheanceAbonnement::STATUT_EN_ATTENTE,
        ]);

        $this->assertDatabaseHas('factures', [
            'montant_total' => 100000.00,
            'statut'        => Facture::STATUT_EN_ATTENTE,
        ]);

        // Vérification de la règle SNAPSHOT : modifier le montant de la formule ne doit PAS modifier l'abonnement
        $formule->update(['montant' => 150000.00]);
        $aboEnBase = Abonnement::find($aboData['id_interne']);
        $this->assertEquals(100000.00, $aboEnBase->montant);
    }

    public function test_free_formula_subscription_activates_immediately_without_echeance(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $formuleGratuite = Formule::create([
            'produit_id'   => $this->oppe->id,
            'code'         => 'OPPE_FREE',
            'nom'          => 'OPPE Starter Gratuit',
            'periodicite'  => Formule::PERIODICITE_MENSUELLE,
            'montant'      => 0.00,
            'devise'       => 'XOF',
            'est_gratuite' => true,
            'statut'       => 'actif',
        ]);

        $response = $this->postJson('/api/v1/super-admin/abonnements', [
            'paroisse_configuration_id' => $this->paroisseA->id,
            'formule_id'                => $formuleGratuite->id,
        ]);

        $response->assertStatus(201);
        $aboData = $response->json('data');

        // L'abonnement doit être directement actif
        $this->assertSame(Abonnement::STATUT_ACTIF, $aboData['statut']);
        $this->assertEquals(0.00, $aboData['montant']);

        // Aucune dette ni échéance créée
        $this->assertDatabaseMissing('echeances_abonnement', [
            'abonnement_id' => $aboData['id_interne'],
        ]);
    }

    public function test_independent_subscriptions_per_paroisse_and_product(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $formuleCatheo = Formule::create([
            'produit_id'   => $this->catheo->id,
            'code'         => 'F_CATHEO',
            'nom'          => 'Formule CATHEO',
            'periodicite'  => 'annuelle',
            'montant'      => 50000.00,
            'devise'       => 'XOF',
            'est_gratuite' => false,
            'statut'       => 'actif',
        ]);

        $formuleOppe = Formule::create([
            'produit_id'   => $this->oppe->id,
            'code'         => 'F_OPPE',
            'nom'          => 'Formule OPPE',
            'periodicite'  => 'annuelle',
            'montant'      => 0.00,
            'devise'       => 'XOF',
            'est_gratuite' => true,
            'statut'       => 'actif',
        ]);

        // Paroisse A souscrit à CATHEO (en attente) et OPPE (actif)
        $aboCatheo = $this->postJson('/api/v1/super-admin/abonnements', [
            'paroisse_configuration_id' => $this->paroisseA->id,
            'formule_id'                => $formuleCatheo->id,
        ])->json('data');

        $aboOppe = $this->postJson('/api/v1/super-admin/abonnements', [
            'paroisse_configuration_id' => $this->paroisseA->id,
            'formule_id'                => $formuleOppe->id,
        ])->json('data');

        $this->assertSame(Abonnement::STATUT_EN_ATTENTE, $aboCatheo['statut']);
        $this->assertSame(Abonnement::STATUT_ACTIF, $aboOppe['statut']);
    }

    public function test_super_admin_can_change_status_and_cancel_subscription(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $formule = Formule::create([
            'produit_id'   => $this->catheo->id,
            'code'         => 'F_TEST_STATUS',
            'nom'          => 'Test Status',
            'periodicite'  => 'mensuelle',
            'montant'      => 10000.00,
            'devise'       => 'XOF',
            'est_gratuite' => false,
        ]);

        $abo = Abonnement::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'formule_id'                => $formule->id,
            'reference'                 => 'ABO-26-0001',
            'date_debut'                => now()->toDateString(),
            'statut'                    => Abonnement::STATUT_ACTIF,
            'montant'                   => 10000.00,
            'devise'                    => 'XOF',
        ]);

        // Suspendre
        $patchResp = $this->patchJson("/api/v1/super-admin/abonnements/{$abo->id}/statut", [
            'statut'      => Abonnement::STATUT_SUSPENDU,
            'observation' => 'Paiement en retard',
        ]);
        $patchResp->assertStatus(200);
        $this->assertSame(Abonnement::STATUT_SUSPENDU, $abo->fresh()->statut);

        // Résilier
        $resilResp = $this->postJson("/api/v1/super-admin/abonnements/{$abo->id}/resilier", [
            'date_resiliation'  => now()->toDateString(),
            'motif_resiliation' => 'Demande explicite du curé.',
        ]);
        $resilResp->assertStatus(200);
        $this->assertSame(Abonnement::STATUT_RESILIE, $abo->fresh()->statut);
        $this->assertSame('Demande explicite du curé.', $abo->fresh()->motif_resiliation);
    }

    // =========================================================================
    // SECTION 5 : ÉCHÉANCES, PAIEMENTS MULTIPLES & FACTURATION
    // =========================================================================

    public function test_partial_and_full_payments_cascade_updates_echeance_facture_and_abonnement(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // 1. Création formule & abonnement (100 000 XOF)
        $formule = Formule::create([
            'produit_id'   => $this->catheo->id,
            'code'         => 'F_PAY_CASCADE',
            'nom'          => 'Formule Cascade Test',
            'periodicite'  => 'annuelle',
            'montant'      => 100000.00,
            'devise'       => 'XOF',
            'est_gratuite' => false,
        ]);

        $aboResponse = $this->postJson('/api/v1/super-admin/abonnements', [
            'paroisse_configuration_id' => $this->paroisseA->id,
            'formule_id'                => $formule->id,
        ]);

        $aboId = $aboResponse->json('data.id_interne');
        $echeance = EcheanceAbonnement::where('abonnement_id', $aboId)->firstOrFail();
        $facture  = Facture::where('echeance_abonnement_id', $echeance->id)->firstOrFail();

        $this->assertSame(EcheanceAbonnement::STATUT_EN_ATTENTE, $echeance->statut);
        $this->assertSame(Facture::STATUT_EN_ATTENTE, $facture->statut);
        $this->assertSame(Abonnement::STATUT_EN_ATTENTE, $echeance->abonnement->statut);

        // 2. Premier paiement partiel de 40 000 XOF
        $payResp1 = $this->postJson('/api/v1/super-admin/paiements-abonnement', [
            'echeance_abonnement_id' => $echeance->id,
            'montant'                => 40000.00,
            'devise'                 => 'XOF',
            'mode_paiement'          => 'virement',
            'date_paiement'          => now()->toDateString(),
            'reference_transaction'  => 'VIR-001',
        ]);

        $payResp1->assertStatus(201);
        $this->assertStringStartsWith('PAY-', $payResp1->json('data.reference'));

        $echeance->refresh();
        $facture->refresh();
        $this->assertSame(EcheanceAbonnement::STATUT_EN_ATTENTE, $echeance->statut);
        $this->assertSame(40000.00, (float) $echeance->montant_paye);
        $this->assertSame(60000.00, (float) $echeance->solde_restant);
        $this->assertSame(Facture::STATUT_EN_ATTENTE, $facture->statut);

        // 3. Second paiement de 60 000 XOF soldant l'échéance
        $payResp2 = $this->postJson('/api/v1/super-admin/paiements-abonnement', [
            'echeance_abonnement_id' => $echeance->id,
            'montant'                => 60000.00,
            'devise'                 => 'XOF',
            'mode_paiement'          => 'mobile_money',
            'date_paiement'          => now()->toDateString(),
            'reference_transaction'  => 'OM-999',
        ]);

        $payResp2->assertStatus(201);

        $echeance->refresh();
        $facture->refresh();
        $abonnement = Abonnement::find($aboId);

        // L'échéance doit être payée
        $this->assertSame(EcheanceAbonnement::STATUT_PAYEE, $echeance->statut);
        $this->assertSame(100000.00, (float) $echeance->montant_paye);
        $this->assertSame(0.00, (float) $echeance->solde_restant);

        // La facture doit être payée
        $this->assertSame(Facture::STATUT_PAYEE, $facture->statut);

        // L'abonnement doit être automatiquement basculé en ACTIF
        $this->assertSame(Abonnement::STATUT_ACTIF, $abonnement->statut);
    }

    public function test_payment_cancellation_and_refund_reopens_echeance(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $formule = Formule::create([
            'produit_id'   => $this->catheo->id,
            'code'         => 'F_ANNUL_TEST',
            'nom'          => 'Formule Annulation',
            'periodicite'  => 'annuelle',
            'montant'      => 50000.00,
            'devise'       => 'XOF',
            'est_gratuite' => false,
        ]);

        $abo = $this->postJson('/api/v1/super-admin/abonnements', [
            'paroisse_configuration_id' => $this->paroisseA->id,
            'formule_id'                => $formule->id,
        ])->json('data');

        $echeance = EcheanceAbonnement::where('abonnement_id', $abo['id_interne'])->firstOrFail();

        // Paiement total
        $payResp = $this->postJson('/api/v1/super-admin/paiements-abonnement', [
            'echeance_abonnement_id' => $echeance->id,
            'montant'                => 50000.00,
            'mode_paiement'          => 'especes',
            'date_paiement'          => now()->toDateString(),
        ]);

        $payId = $payResp->json('data.id_interne');
        $this->assertSame(EcheanceAbonnement::STATUT_PAYEE, $echeance->fresh()->statut);

        // Annulation du paiement
        $annulResp = $this->postJson("/api/v1/super-admin/paiements-abonnement/{$payId}/annuler", [
            'observation' => 'Erreur de saisie caisse',
        ]);
        $annulResp->assertStatus(200);

        // L'échéance doit repasser à en_attente car le paiement n'est plus valide
        $this->assertSame(EcheanceAbonnement::STATUT_EN_ATTENTE, $echeance->fresh()->statut);
        $this->assertSame(0.00, (float) $echeance->fresh()->montant_paye);
    }

    // =========================================================================
    // SECTION 6 : TABLEAU DE BORD SUPER ADMIN & PAROISSES
    // =========================================================================

    public function test_dashboard_metrics_calculation(): void
    {
        Sanctum::actingAs($this->superAdmin);

        // Créer un paiement pour alimenter le CA
        $formule = Formule::create([
            'produit_id'   => $this->catheo->id,
            'code'         => 'F_DASH',
            'nom'          => 'Formule Dashboard',
            'periodicite'  => 'annuelle',
            'montant'      => 75000.00,
            'devise'       => 'XOF',
            'est_gratuite' => false,
        ]);

        $abo = $this->postJson('/api/v1/super-admin/abonnements', [
            'paroisse_configuration_id' => $this->paroisseA->id,
            'formule_id'                => $formule->id,
        ])->json('data');

        $echeance = EcheanceAbonnement::where('abonnement_id', $abo['id_interne'])->firstOrFail();

        $this->postJson('/api/v1/super-admin/paiements-abonnement', [
            'echeance_abonnement_id' => $echeance->id,
            'montant'                => 75000.00,
            'mode_paiement'          => 'especes',
            'date_paiement'          => now()->toDateString(),
        ]);

        $resp = $this->getJson('/api/v1/super-admin/dashboard');
        $resp->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'paroisses' => ['total', 'actives'],
                    'produits_actifs',
                    'abonnements' => ['actifs', 'en_attente', 'suspendus', 'expires', 'resilies'],
                    'finances' => ['ca_total_encaisse', 'ca_mois_courant', 'echeances_en_retard', 'montant_en_retard', 'devise'],
                    'repartition_produits',
                    'paiements_recents',
                ],
            ]);

        $metrics = $resp->json('data');
        $this->assertGreaterThanOrEqual(2, $metrics['paroisses']['total']);
        $this->assertGreaterThanOrEqual(1, $metrics['abonnements']['actifs']);
        $this->assertEquals(75000.00, $metrics['finances']['ca_total_encaisse']);
    }

    public function test_super_admin_can_supervise_parishes_and_their_subscribed_products(): void
    {
        Sanctum::actingAs($this->superAdmin);

        $resp = $this->getJson('/api/v1/super-admin/paroisses');
        $resp->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    '*' => [
                        'id',
                        'nom_paroisse',
                        'code_paroisse',
                        'diocese',
                        'ville',
                        'statut',
                        'produits_souscrits',
                    ],
                ],
            ]);

        $showResp = $this->getJson("/api/v1/super-admin/paroisses/{$this->paroisseA->id}");
        $showResp->assertStatus(200)
            ->assertJson([
                'data' => [
                    'nom_paroisse' => 'Paroisse Sainte Famille',
                ],
            ]);
    }
}
