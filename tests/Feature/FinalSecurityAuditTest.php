<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\AnneeCatechese;
use App\Models\CampagnePelerinage;
use App\Models\CatecheseConfiguration;
use App\Models\Catechumene;
use App\Models\Classe;
use App\Models\InscriptionAnnuelle;
use App\Models\InscriptionPelerinage;
use App\Models\Membre;
use App\Models\Niveau;
use App\Models\OperationOrganisation;
use App\Models\Organisation;
use App\Models\PaiementPelerinage;
use App\Models\Produit;
use App\Models\Profil;
use App\Models\Section;
use App\Models\TarifPelerinage;
use App\Models\User;
use Database\Seeders\OrganisationProfilSeeder;
use Database\Seeders\ProduitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FinalSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected CatecheseConfiguration $paroisseA;
    protected CatecheseConfiguration $paroisseB;

    protected Produit $produitOppe;
    protected Produit $produitOppj;
    protected Produit $produitOppa;

    protected Organisation $oppeA;
    protected Organisation $oppjA;
    protected Organisation $oppaA;
    protected Organisation $oppeB;

    protected User $respOppeA;
    protected User $userOppeA;
    protected User $respOppjA;
    protected User $respOppaA;
    protected User $respOppeB;
    protected User $superAdmin;

    protected AnneeCatechese $anneeCouranteA;
    protected Section $secEnfPri;
    protected Section $secEnfCol;
    protected Section $secJeunes;
    protected Section $secAdultes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProduitSeeder::class);
        $this->seed(OrganisationProfilSeeder::class);

        $this->produitOppe = Produit::where('code', Produit::CODE_OPPE)->firstOrFail();
        $this->produitOppj = Produit::where('code', Produit::CODE_OPPJ)->firstOrFail();
        $this->produitOppa = Produit::where('code', Produit::CODE_OPPA)->firstOrFail();

        // Paroisses A et B
        $this->paroisseA = CatecheseConfiguration::create([
            'nom_paroisse'      => 'Paroisse Sainte Famille A',
            'code_paroisse'     => 'PAR-SFA-' . uniqid(),
            'prefixe_matricule' => 'SFA',
            'prefixe_recu'      => 'REC',
            'statut'            => 'actif',
            'ville'             => 'Abidjan',
            'diocese'           => 'Abidjan',
        ]);

        $this->paroisseB = CatecheseConfiguration::create([
            'nom_paroisse'      => 'Paroisse Saint Jean B',
            'code_paroisse'     => 'PAR-SJB-' . uniqid(),
            'prefixe_matricule' => 'SJB',
            'prefixe_recu'      => 'REC',
            'statut'            => 'actif',
            'ville'             => 'Bouaké',
            'diocese'           => 'Bouaké',
        ]);

        // Année active Paroisse A
        $this->anneeCouranteA = AnneeCatechese::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'libelle'                   => 'Année 2025-2026',
            'date_debut'                => '2025-09-01',
            'date_fin'                  => '2026-06-30',
            'statut'                    => 'actif',
        ]);

        // Sections
        $this->secEnfPri = Section::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'code'                      => 'SEC-ENFANTS-PRI',
            'nom'                       => 'Enfants Primaire',
            'statut'                    => 'actif',
        ]);
        $this->secEnfCol = Section::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'code'                      => 'SEC-ENFANTS-COL',
            'nom'                       => 'Enfants Collège',
            'statut'                    => 'actif',
        ]);
        $this->secJeunes = Section::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'code'                      => 'SEC-JEUNES',
            'nom'                       => 'Jeunes',
            'statut'                    => 'actif',
        ]);
        $this->secAdultes = Section::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'code'                      => 'SEC-ADULTES',
            'nom'                       => 'Adultes',
            'statut'                    => 'actif',
        ]);

        // Organisations
        $this->oppeA = Organisation::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'produit_id'                => $this->produitOppe->id,
            'type_organisation'         => Organisation::TYPE_OPPE,
            'code'                      => 'OPPE-SFA',
            'nom'                       => 'OPPE Sainte Famille',
            'statut'                    => 'actif',
        ]);

        $this->oppjA = Organisation::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'produit_id'                => $this->produitOppj->id,
            'type_organisation'         => Organisation::TYPE_OPPJ,
            'code'                      => 'OPPJ-SFA',
            'nom'                       => 'OPPJ Sainte Famille',
            'statut'                    => 'actif',
        ]);

        $this->oppaA = Organisation::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'produit_id'                => $this->produitOppa->id,
            'type_organisation'         => Organisation::TYPE_OPPA,
            'code'                      => 'OPPA-SFA',
            'nom'                       => 'OPPA Sainte Famille',
            'statut'                    => 'actif',
        ]);

        $this->oppeB = Organisation::create([
            'paroisse_configuration_id' => $this->paroisseB->id,
            'produit_id'                => $this->produitOppe->id,
            'type_organisation'         => Organisation::TYPE_OPPE,
            'code'                      => 'OPPE-SJB',
            'nom'                       => 'OPPE Saint Jean B',
            'statut'                    => 'actif',
        ]);

        // Profils
        $profilRespOppe = Profil::where('code', 'RESPONSABLE_OPPE')->firstOrFail();
        $profilUserOppe = Profil::where('code', 'UTILISATEUR_OPPE')->firstOrFail();
        $profilRespOppj = Profil::where('code', 'RESPONSABLE_OPPJ')->firstOrFail();
        $profilRespOppa = Profil::where('code', 'RESPONSABLE_OPPA')->firstOrFail();

        // Utilisateurs
        $this->respOppeA = User::create([
            'name'                      => 'Resp OPPE A',
            'email'                     => 'resp.oppe.a.' . uniqid() . '@example.com',
            'password'                  => Hash::make('Password123!'),
            'profil_id'                 => $profilRespOppe->id,
            'paroisse_configuration_id' => $this->paroisseA->id,
            'organisation_id'           => $this->oppeA->id,
            'statut'                    => 'actif',
        ]);

        $this->userOppeA = User::create([
            'name'                      => 'User OPPE A',
            'email'                     => 'user.oppe.a.' . uniqid() . '@example.com',
            'password'                  => Hash::make('Password123!'),
            'profil_id'                 => $profilUserOppe->id,
            'paroisse_configuration_id' => $this->paroisseA->id,
            'organisation_id'           => $this->oppeA->id,
            'statut'                    => 'actif',
        ]);

        $this->respOppjA = User::create([
            'name'                      => 'Resp OPPJ A',
            'email'                     => 'resp.oppj.a.' . uniqid() . '@example.com',
            'password'                  => Hash::make('Password123!'),
            'profil_id'                 => $profilRespOppj->id,
            'paroisse_configuration_id' => $this->paroisseA->id,
            'organisation_id'           => $this->oppjA->id,
            'statut'                    => 'actif',
        ]);

        $this->respOppaA = User::create([
            'name'                      => 'Resp OPPA A',
            'email'                     => 'resp.oppa.a.' . uniqid() . '@example.com',
            'password'                  => Hash::make('Password123!'),
            'profil_id'                 => $profilRespOppa->id,
            'paroisse_configuration_id' => $this->paroisseA->id,
            'organisation_id'           => $this->oppaA->id,
            'statut'                    => 'actif',
        ]);

        $this->respOppeB = User::create([
            'name'                      => 'Resp OPPE B',
            'email'                     => 'resp.oppe.b.' . uniqid() . '@example.com',
            'password'                  => Hash::make('Password123!'),
            'profil_id'                 => $profilRespOppe->id,
            'paroisse_configuration_id' => $this->paroisseB->id,
            'organisation_id'           => $this->oppeB->id,
            'statut'                    => 'actif',
        ]);

        $this->superAdmin = User::create([
            'name'                      => 'Super Admin',
            'email'                     => 'superadmin.' . uniqid() . '@example.com',
            'password'                  => Hash::make('Password123!'),
            'user_type'                 => 'super_admin',
            'statut'                    => 'actif',
            'paroisse_configuration_id' => null,
            'organisation_id'           => null,
        ]);
    }

    // =========================================================================
    // 1. Accès sans authentification -> 401
    // =========================================================================
    public function test_01_unauthenticated_access_returns_401(): void
    {
        $response = $this->getJson('/api/v1/organisation/dashboard');
        $response->assertStatus(401);
    }

    // =========================================================================
    // 2. Token invalide -> 401
    // =========================================================================
    public function test_02_invalid_token_returns_401(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer invalid-token-12345')
            ->getJson('/api/v1/organisation/dashboard');
        $response->assertStatus(401);
    }

    // =========================================================================
    // 3. Permission absente -> 403
    // =========================================================================
    public function test_03_missing_permission_returns_403(): void
    {
        Sanctum::actingAs($this->userOppeA); // profil UTILISATEUR_OPPE : lecture seule, pas de création de membre
        $response = $this->postJson('/api/v1/organisation/membres', [
            'nom'     => 'Kouamé',
            'prenoms' => 'Jean',
            'sexe'    => 'M',
        ]);
        $response->assertStatus(403);
    }

    // =========================================================================
    // 4. Organisation inactive -> 403
    // =========================================================================
    public function test_04_inactive_organisation_returns_403(): void
    {
        $this->oppeA->update(['statut' => 'inactif']);
        Sanctum::actingAs($this->respOppeA);

        $response = $this->getJson('/api/v1/organisation/dashboard');
        $response->assertStatus(403);
    }

    // =========================================================================
    // 5. Organisation suspendue -> 403
    // =========================================================================
    public function test_05_suspended_organisation_returns_403(): void
    {
        $this->oppeA->update(['statut' => 'suspendu']);
        Sanctum::actingAs($this->respOppeA);

        $response = $this->getJson('/api/v1/organisation/dashboard');
        $response->assertStatus(403);
    }

    // =========================================================================
    // 6. IDOR membre -> 404
    // =========================================================================
    public function test_06_idor_membre_returns_404(): void
    {
        $membreB = Membre::create([
            'organisation_id' => $this->oppeB->id,
            'matricule'       => 'MB-B-001',
            'nom'             => 'Koffi',
            'prenoms'         => 'Albert',
            'sexe'            => 'M',
            'statut'          => 'actif',
        ]);

        Sanctum::actingAs($this->respOppeA);
        $response = $this->getJson("/api/v1/organisation/membres/{$membreB->id}");
        $response->assertStatus(404);
    }

    // =========================================================================
    // 7. IDOR activité -> 404
    // =========================================================================
    public function test_07_idor_activite_returns_404(): void
    {
        $activiteB = Activite::create([
            'organisation_id' => $this->oppeB->id,
            'code'            => 'ACT-B-001',
            'titre'           => 'Pèlerinage Paroisse B',
            'type_activite'   => 'pelerinage',
            'statut'          => 'planifiee',
            'date_debut'      => '2026-05-01',
            'date_fin'        => '2026-05-03',
        ]);

        Sanctum::actingAs($this->respOppeA);
        $response = $this->getJson("/api/v1/organisation/activites/{$activiteB->id}");
        $response->assertStatus(404);
    }

    // =========================================================================
    // 8. IDOR campagne de pèlerinage -> 404
    // =========================================================================
    public function test_08_idor_campagne_returns_404(): void
    {
        $campagneB = CampagnePelerinage::create([
            'organisation_id' => $this->oppeB->id,
            'code'            => 'PEL-B-001',
            'nom'             => 'Pèlerinage B',
            'lieu_depart'     => 'Bouaké',
            'destination'     => 'Yamoussoukro',
            'date_depart'     => '2026-07-01',
            'date_fin'        => '2026-07-03',
            'statut'          => 'ouverte',
        ]);

        Sanctum::actingAs($this->respOppeA);
        $response = $this->getJson("/api/v1/organisation/pelerinages/{$campagneB->id}");
        $response->assertStatus(404);
    }

    // =========================================================================
    // 9. IDOR inscription pèlerinage -> 404
    // =========================================================================
    public function test_09_idor_inscription_returns_404(): void
    {
        $campagneA = CampagnePelerinage::create([
            'organisation_id' => $this->oppeA->id,
            'code'            => 'PEL-A-001',
            'nom'             => 'Pèlerinage A',
            'lieu_depart'     => 'Abidjan',
            'destination'     => 'Yamoussoukro',
            'date_depart'     => '2026-07-01',
            'date_fin'        => '2026-07-03',
            'statut'          => 'ouverte',
        ]);

        $campagneB = CampagnePelerinage::create([
            'organisation_id' => $this->oppeB->id,
            'code'            => 'PEL-B-002',
            'nom'             => 'Pèlerinage B',
            'lieu_depart'     => 'Bouaké',
            'destination'     => 'Yamoussoukro',
            'date_depart'     => '2026-07-01',
            'date_fin'        => '2026-07-03',
            'statut'          => 'ouverte',
        ]);

        $tarifB = TarifPelerinage::create([
            'campagne_pelerinage_id' => $campagneB->id,
            'code'                   => 'TAR-B',
            'libelle'                => 'Tarif B',
            'montant'                => 15000,
        ]);

        $inscriptionB = InscriptionPelerinage::create([
            'campagne_pelerinage_id' => $campagneB->id,
            'tarif_pelerinage_id'    => $tarifB->id,
            'type_participant'       => 'EXTERNE',
            'reference'              => 'INS-B-001',
            'nom'                    => 'Boka',
            'prenoms'                => 'David',
            'montant'                => 15000,
            'montant_paye'           => 0,
            'reste_a_payer'          => 15000,
            'statut_inscription'     => 'en_attente',
            'statut_participation'   => 'prevue',
        ]);

        Sanctum::actingAs($this->respOppeA);
        // Tenter d'accéder à l'inscription de B via l'URL de la campagne A
        $response = $this->getJson("/api/v1/organisation/pelerinages/{$campagneA->id}/inscriptions/{$inscriptionB->id}");
        $response->assertStatus(404);
    }

    // =========================================================================
    // 10. IDOR paiement pèlerinage -> 404
    // =========================================================================
    public function test_10_idor_paiement_returns_404(): void
    {
        $campagneA = CampagnePelerinage::create([
            'organisation_id' => $this->oppeA->id,
            'code'            => 'PEL-A-002',
            'nom'             => 'Pèlerinage A2',
            'lieu_depart'     => 'Abidjan',
            'destination'     => 'Grand-Bassam',
            'date_depart'     => '2026-08-01',
            'date_fin'        => '2026-08-02',
            'statut'          => 'ouverte',
        ]);

        $campagneB = CampagnePelerinage::create([
            'organisation_id' => $this->oppeB->id,
            'code'            => 'PEL-B-003',
            'nom'             => 'Pèlerinage B2',
            'lieu_depart'     => 'Bouaké',
            'destination'     => 'Katiola',
            'date_depart'     => '2026-08-01',
            'date_fin'        => '2026-08-02',
            'statut'          => 'ouverte',
        ]);

        $tarifB = TarifPelerinage::create([
            'campagne_pelerinage_id' => $campagneB->id,
            'code'                   => 'TAR-B3',
            'libelle'                => 'Tarif B3',
            'montant'                => 10000,
        ]);

        $inscriptionB = InscriptionPelerinage::create([
            'campagne_pelerinage_id' => $campagneB->id,
            'tarif_pelerinage_id'    => $tarifB->id,
            'type_participant'       => 'EXTERNE',
            'reference'              => 'INS-B-002',
            'nom'                    => 'Gnahoré',
            'prenoms'                => 'Paul',
            'montant'                => 10000,
            'montant_paye'           => 10000,
            'reste_a_payer'          => 0,
            'statut_inscription'     => 'payee',
            'statut_participation'   => 'prevue',
        ]);

        $paiementB = PaiementPelerinage::create([
            'inscription_pelerinage_id' => $inscriptionB->id,
            'reference'                 => 'PAI-B-001',
            'montant'                   => 10000,
            'mode_paiement'             => 'especes',
            'date_paiement'             => now(),
            'statut'                    => 'valide',
        ]);

        Sanctum::actingAs($this->respOppeA);
        // Tenter d'annuler le paiement de B via la campagne A
        $response = $this->postJson("/api/v1/organisation/pelerinages/{$campagneA->id}/paiements/{$paiementB->id}/annuler", [
            'motif' => 'Tentative frauduleuse',
        ]);
        $response->assertStatus(404);
    }

    // =========================================================================
    // 11. IDOR opération de caisse -> isolée
    // =========================================================================
    public function test_11_idor_operation_returns_404(): void
    {
        OperationOrganisation::create([
            'organisation_id' => $this->oppeB->id,
            'reference'       => 'OP-B-001',
            'type_operation'  => 'entree',
            'montant'         => 50000,
            'libelle'         => 'Don pour B',
            'date_operation'  => now(),
            'statut'          => 'valide',
        ]);

        Sanctum::actingAs($this->respOppeA);
        $response = $this->getJson('/api/v1/organisation/caisse');
        $response->assertStatus(200);

        // Vérifier que le solde de B n'est pas inclus dans la caisse de A
        $synthese = $response->json('synthese');
        $this->assertEquals(0, $synthese['solde_final']);
    }

    // =========================================================================
    // 12. Export autre organisation impossible
    // =========================================================================
    public function test_12_export_other_organisation_impossible(): void
    {
        // Créer un membre dans B
        Membre::create([
            'organisation_id' => $this->oppeB->id,
            'matricule'       => 'MB-EXP-B',
            'nom'             => 'SecretB',
            'prenoms'         => 'Confidentiel',
            'sexe'            => 'M',
            'statut'          => 'actif',
        ]);

        Sanctum::actingAs($this->respOppeA);
        $response = $this->getJson('/api/v1/organisation/exports/membres');
        $response->assertStatus(200);
        $content = $response->streamedContent();

        $this->assertStringNotContainsString('SecretB', $content);
    }

    // =========================================================================
    // 13. Accès Super Admin depuis utilisateur organisation impossible
    // =========================================================================
    public function test_13_organisation_user_cannot_access_super_admin(): void
    {
        Sanctum::actingAs($this->respOppeA);
        $response = $this->getJson('/api/v1/super-admin/dashboard');
        $response->assertStatus(403);
    }

    // =========================================================================
    // 14. OPPE ne peut pas utiliser les données OPPA
    // =========================================================================
    public function test_14_oppe_cannot_access_oppa_data(): void
    {
        $niv = Niveau::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'section_id'                => $this->secAdultes->id,
            'code'                      => 'NIV-A-' . uniqid(),
            'nom'                       => 'Niveau Adulte',
            'statut'                    => 'actif',
        ]);

        // Catéchumène Adulte (OPPA)
        $adulte = Catechumene::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'matricule'                 => 'SFA26-A1111',
            'nom'                       => 'AdulteTest',
            'prenoms'                   => 'Michel',
            'sexe'                      => 'M',
            'statut'                    => 'actif',
        ]);
        InscriptionAnnuelle::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'annee_catechese_id'        => $this->anneeCouranteA->id,
            'catechumene_id'            => $adulte->id,
            'section_id'                => $this->secAdultes->id,
            'niveau_id'                 => $niv->id,
            'statut'                    => 'valide',
        ]);

        Sanctum::actingAs($this->respOppeA);
        $response = $this->getJson('/api/v1/organisation/catheo/population');
        $response->assertStatus(200);

        $matricules = collect($response->json('data'))->pluck('matricule')->all();
        $this->assertNotContains('SFA26-A1111', $matricules);
    }

    // =========================================================================
    // 15. OPPJ ne peut pas utiliser les données OPPE
    // =========================================================================
    public function test_15_oppj_cannot_access_oppe_data(): void
    {
        $niv = Niveau::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'section_id'                => $this->secEnfPri->id,
            'code'                      => 'NIV-E-' . uniqid(),
            'nom'                       => 'Niveau Enfant',
            'statut'                    => 'actif',
        ]);

        // Catéchumène Enfant Primaire (OPPE)
        $enfant = Catechumene::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'matricule'                 => 'SFA26-E2222',
            'nom'                       => 'EnfantTest',
            'prenoms'                   => 'Lucas',
            'sexe'                      => 'M',
            'statut'                    => 'actif',
        ]);
        InscriptionAnnuelle::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'annee_catechese_id'        => $this->anneeCouranteA->id,
            'catechumene_id'            => $enfant->id,
            'section_id'                => $this->secEnfPri->id,
            'niveau_id'                 => $niv->id,
            'statut'                    => 'valide',
        ]);

        Sanctum::actingAs($this->respOppjA);
        $response = $this->getJson('/api/v1/organisation/catheo/population');
        $response->assertStatus(200);

        $matricules = collect($response->json('data'))->pluck('matricule')->all();
        $this->assertNotContains('SFA26-E2222', $matricules);
    }

    // =========================================================================
    // 16. OPPA ne peut pas utiliser les données OPPJ
    // =========================================================================
    public function test_16_oppa_cannot_access_oppj_data(): void
    {
        $niv = Niveau::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'section_id'                => $this->secJeunes->id,
            'code'                      => 'NIV-J-' . uniqid(),
            'nom'                       => 'Niveau Jeune',
            'statut'                    => 'actif',
        ]);

        // Catéchumène Jeune (OPPJ)
        $jeune = Catechumene::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'matricule'                 => 'SFA26-J3333',
            'nom'                       => 'JeuneTest',
            'prenoms'                   => 'Samuel',
            'sexe'                      => 'M',
            'statut'                    => 'actif',
        ]);
        InscriptionAnnuelle::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'annee_catechese_id'        => $this->anneeCouranteA->id,
            'catechumene_id'            => $jeune->id,
            'section_id'                => $this->secJeunes->id,
            'niveau_id'                 => $niv->id,
            'statut'                    => 'valide',
        ]);

        Sanctum::actingAs($this->respOppaA);
        $response = $this->getJson('/api/v1/organisation/catheo/population');
        $response->assertStatus(200);

        $matricules = collect($response->json('data'))->pluck('matricule')->all();
        $this->assertNotContains('SFA26-J3333', $matricules);
    }

    // =========================================================================
    // 17. organisation_id injecté ignoré/rejeté
    // =========================================================================
    public function test_17_injected_organisation_id_is_ignored_or_rejected(): void
    {
        Sanctum::actingAs($this->respOppeA);

        $response = $this->postJson('/api/v1/organisation/membres', [
            'organisation_id' => $this->oppeB->id, // Injection malveillante
            'nom'             => 'InjectionTest',
            'prenoms'         => 'Test',
            'sexe'            => 'M',
            'telephone'       => '0102030405',
        ]);

        $response->assertStatus(201);
        $membre = Membre::where('nom', 'InjectionTest')->firstOrFail();

        // Le membre DOIT appartenir à l'organisation A de l'utilisateur, PAS à l'organisation injectée B
        $this->assertEquals($this->oppeA->id, $membre->organisation_id);
    }

    // =========================================================================
    // 18. paroisse_configuration_id injecté ignoré/rejeté
    // =========================================================================
    public function test_18_injected_paroisse_configuration_id_is_ignored_or_rejected(): void
    {
        Sanctum::actingAs($this->respOppeA);

        $response = $this->postJson('/api/v1/organisation/activites', [
            'paroisse_configuration_id' => $this->paroisseB->id, // Injection malveillante
            'titre'                     => 'Activité Safe',
            'type_activite'             => 'reunion',
            'date_debut'                => '2026-09-01',
        ]);

        $response->assertStatus(201);
        $activite = Activite::where('titre', 'Activité Safe')->firstOrFail();

        $this->assertEquals($this->oppeA->id, $activite->organisation_id);
        $this->assertEquals($this->paroisseA->id, $activite->organisation->paroisse_configuration_id);
    }

    // =========================================================================
    // 19. created_by injecté ignoré/rejeté
    // =========================================================================
    public function test_19_injected_created_by_is_ignored_or_rejected(): void
    {
        Sanctum::actingAs($this->respOppeA);

        $response = $this->postJson('/api/v1/organisation/membres', [
            'created_by' => 'fake-hacker-uuid-12345',
            'nom'        => 'CreatedByAudit',
            'prenoms'    => 'Test',
            'sexe'       => 'F',
        ]);

        $response->assertStatus(201);
        $membre = Membre::where('nom', 'CreatedByAudit')->firstOrFail();

        // Le created_by doit correspondre au UUID du respOppeA connecté
        $this->assertEquals($this->respOppeA->uuid, $membre->created_by);
    }

    // =========================================================================
    // 20. Paiement supérieur au solde refusé (422)
    // =========================================================================
    public function test_20_payment_exceeding_remaining_balance_is_rejected(): void
    {
        $campagne = CampagnePelerinage::create([
            'organisation_id' => $this->oppeA->id,
            'code'            => 'PEL-SOLDE-TEST',
            'nom'             => 'Pèlerinage Solde',
            'lieu_depart'     => 'Abidjan',
            'destination'     => 'Basilique',
            'date_depart'     => '2026-09-10',
            'date_fin'        => '2026-09-12',
            'statut'          => 'ouverte',
        ]);

        $tarif = TarifPelerinage::create([
            'campagne_pelerinage_id' => $campagne->id,
            'code'                   => 'TAR-5000',
            'libelle'                => 'Tarif 5000',
            'montant'                => 5000,
        ]);

        $inscription = InscriptionPelerinage::create([
            'campagne_pelerinage_id' => $campagne->id,
            'tarif_pelerinage_id'    => $tarif->id,
            'type_participant'       => 'EXTERNE',
            'reference'              => 'INS-SOLDE-01',
            'nom'                    => 'SoldeNom',
            'prenoms'                => 'SoldePrenom',
            'montant'                => 5000,
            'montant_paye'           => 0,
            'reste_a_payer'          => 5000,
            'statut_inscription'     => 'en_attente',
            'statut_participation'   => 'prevue',
        ]);

        Sanctum::actingAs($this->respOppeA);
        // Tenter de payer 6000 F alors que le reste est de 5000 F
        $response = $this->postJson("/api/v1/organisation/pelerinages/{$campagne->id}/inscriptions/{$inscription->id}/paiements", [
            'montant'       => 6000,
            'mode_paiement' => 'especes',
        ]);

        $response->assertStatus(422);
    }

    // =========================================================================
    // 21. Montant négatif refusé (422)
    // =========================================================================
    public function test_21_negative_payment_amount_is_rejected(): void
    {
        $campagne = CampagnePelerinage::create([
            'organisation_id' => $this->oppeA->id,
            'code'            => 'PEL-NEG-TEST',
            'nom'             => 'Pèlerinage Négatif',
            'lieu_depart'     => 'Abidjan',
            'destination'     => 'Yamoussoukro',
            'date_depart'     => '2026-09-10',
            'date_fin'        => '2026-09-12',
            'statut'          => 'ouverte',
        ]);

        $tarif = TarifPelerinage::create([
            'campagne_pelerinage_id' => $campagne->id,
            'code'                   => 'TAR-NEG',
            'libelle'                => 'Tarif Neg',
            'montant'                => 10000,
        ]);

        $inscription = InscriptionPelerinage::create([
            'campagne_pelerinage_id' => $campagne->id,
            'tarif_pelerinage_id'    => $tarif->id,
            'type_participant'       => 'EXTERNE',
            'reference'              => 'INS-NEG-01',
            'nom'                    => 'NegNom',
            'prenoms'                => 'NegPrenom',
            'montant'                => 10000,
            'montant_paye'           => 0,
            'reste_a_payer'          => 10000,
            'statut_inscription'     => 'en_attente',
            'statut_participation'   => 'prevue',
        ]);

        Sanctum::actingAs($this->respOppeA);
        $response = $this->postJson("/api/v1/organisation/pelerinages/{$campagne->id}/inscriptions/{$inscription->id}/paiements", [
            'montant'       => -2500,
            'mode_paiement' => 'especes',
        ]);

        $response->assertStatus(422);
    }

    // =========================================================================
    // 22. Double inscription empêchée
    // =========================================================================
    public function test_22_duplicate_registration_prevented(): void
    {
        $campagne = CampagnePelerinage::create([
            'organisation_id' => $this->oppeA->id,
            'code'            => 'PEL-DBL-TEST',
            'nom'             => 'Pèlerinage Doublon',
            'lieu_depart'     => 'Abidjan',
            'destination'     => 'Yamoussoukro',
            'date_depart'     => '2026-09-10',
            'date_fin'        => '2026-09-12',
            'statut'          => 'ouverte',
        ]);

        $tarif = TarifPelerinage::create([
            'campagne_pelerinage_id' => $campagne->id,
            'code'                   => 'TAR-DBL',
            'libelle'                => 'Tarif Dbl',
            'montant'                => 10000,
        ]);

        $cat = Catechumene::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'matricule'                 => 'SFA26-E9999',
            'nom'                       => 'DoublonCheck',
            'prenoms'                   => 'Jean',
            'sexe'                      => 'M',
            'statut'                    => 'actif',
        ]);

        Sanctum::actingAs($this->respOppeA);

        // Première inscription : OK
        $res1 = $this->postJson("/api/v1/organisation/pelerinages/{$campagne->id}/inscriptions", [
            'tarif_pelerinage_id' => $tarif->id,
            'catechumene_id'      => $cat->id,
            'type_participant'    => 'CATECHUMENE',
        ]);
        $res1->assertStatus(201);

        // Deuxième inscription du même catéchumène : Refusée (422)
        $res2 = $this->postJson("/api/v1/organisation/pelerinages/{$campagne->id}/inscriptions", [
            'tarif_pelerinage_id' => $tarif->id,
            'catechumene_id'      => $cat->id,
            'type_participant'    => 'CATECHUMENE',
        ]);
        $res2->assertStatus(422);
    }

    // =========================================================================
    // 23. Dépassement de capacité empêché
    // =========================================================================
    public function test_23_capacity_overbooking_is_prevented(): void
    {
        $campagne = CampagnePelerinage::create([
            'organisation_id' => $this->oppeA->id,
            'code'            => 'PEL-CAP-TEST',
            'nom'             => 'Pèlerinage Capacité 1',
            'lieu_depart'     => 'Abidjan',
            'destination'     => 'Bassam',
            'date_depart'     => '2026-09-10',
            'date_fin'        => '2026-09-11',
            'capacite'        => 1, // Capacité strictement limitée à 1
            'statut'          => 'ouverte',
        ]);

        $tarif = TarifPelerinage::create([
            'campagne_pelerinage_id' => $campagne->id,
            'code'                   => 'TAR-CAP',
            'libelle'                => 'Tarif Cap',
            'montant'                => 5000,
        ]);

        Sanctum::actingAs($this->respOppeA);

        // Inscription 1 : OK (occupe la seule place)
        $res1 = $this->postJson("/api/v1/organisation/pelerinages/{$campagne->id}/inscriptions", [
            'tarif_pelerinage_id' => $tarif->id,
            'type_participant'    => 'EXTERNE',
            'nom'                 => 'Participant1',
            'prenoms'             => 'A',
            'telephone'           => '0101010101',
        ]);
        $res1->assertStatus(201);

        // Inscription 2 : Dépassement -> Refusé (422)
        $res2 = $this->postJson("/api/v1/organisation/pelerinages/{$campagne->id}/inscriptions", [
            'tarif_pelerinage_id' => $tarif->id,
            'type_participant'    => 'EXTERNE',
            'nom'                 => 'Participant2',
            'prenoms'             => 'B',
            'telephone'           => '0202020202',
        ]);
        $res2->assertStatus(422);
    }

    // =========================================================================
    // 24. Paiement annulé correctement
    // =========================================================================
    public function test_24_payment_cancelled_correctly(): void
    {
        $campagne = CampagnePelerinage::create([
            'organisation_id' => $this->oppeA->id,
            'code'            => 'PEL-ANNUL-TEST',
            'nom'             => 'Pèlerinage Annul',
            'lieu_depart'     => 'Abidjan',
            'destination'     => 'Yamoussoukro',
            'date_depart'     => '2026-09-10',
            'date_fin'        => '2026-09-12',
            'statut'          => 'ouverte',
        ]);

        $tarif = TarifPelerinage::create([
            'campagne_pelerinage_id' => $campagne->id,
            'code'                   => 'TAR-ANNUL',
            'libelle'                => 'Tarif Annul',
            'montant'                => 10000,
        ]);

        $inscription = InscriptionPelerinage::create([
            'campagne_pelerinage_id' => $campagne->id,
            'tarif_pelerinage_id'    => $tarif->id,
            'type_participant'       => 'EXTERNE',
            'reference'              => 'INS-ANNUL-01',
            'nom'                    => 'AnnulNom',
            'prenoms'                => 'AnnulPrenom',
            'montant'                => 10000,
            'montant_paye'           => 0,
            'reste_a_payer'          => 10000,
            'statut_inscription'     => 'en_attente',
            'statut_participation'   => 'prevue',
        ]);

        Sanctum::actingAs($this->respOppeA);

        // 1. Enregistrer un acompte de 4000 F
        $resPay = $this->postJson("/api/v1/organisation/pelerinages/{$campagne->id}/inscriptions/{$inscription->id}/paiements", [
            'montant'       => 4000,
            'mode_paiement' => 'especes',
        ]);
        $resPay->assertStatus(201);
        $paiementId = $resPay->json('data.id');

        $inscription->refresh();
        $this->assertEquals(4000, (float) $inscription->montant_paye);
        $this->assertEquals(6000, (float) $inscription->reste_a_payer);
        $this->assertEquals('partiellement_payee', $inscription->statut_inscription);

        // Vérifier l'opération de caisse créée
        $op = OperationOrganisation::where('paiement_pelerinage_id', $paiementId)->first();
        $this->assertNotNull($op);
        $this->assertEquals('valide', $op->statut);

        // 2. Annuler le paiement
        $resAnnul = $this->postJson("/api/v1/organisation/pelerinages/{$campagne->id}/paiements/{$paiementId}/annuler", [
            'motif' => 'Erreur de saisie guichet',
        ]);
        $resAnnul->assertStatus(200);

        // 3. Vérifications de cohérence financière
        $inscription->refresh();
        $this->assertEquals(0, (float) $inscription->montant_paye);
        $this->assertEquals(10000, (float) $inscription->reste_a_payer);
        $this->assertEquals('en_attente', $inscription->statut_inscription);

        $op->refresh();
        $this->assertEquals('annule', $op->statut);
    }

    // =========================================================================
    // 25. Cache isolé par organisation
    // =========================================================================
    public function test_25_dashboard_cache_isolated_per_organisation(): void
    {
        // Créer un membre dans A
        Membre::create([
            'organisation_id' => $this->oppeA->id,
            'matricule'       => 'MB-CACHE-A',
            'nom'             => 'MembreA',
            'prenoms'         => 'Test',
            'sexe'            => 'M',
            'statut'          => 'actif',
        ]);

        // Dashboard A
        Sanctum::actingAs($this->respOppeA);
        $resA = $this->getJson('/api/v1/organisation/dashboard');
        $resA->assertStatus(200);
        $this->assertEquals(1, $resA->json('data.membres.total'));

        // Dashboard B (doit avoir 0 membres, pas contaminé par le cache de A)
        Sanctum::actingAs($this->respOppeB);
        $resB = $this->getJson('/api/v1/organisation/dashboard');
        $resB->assertStatus(200);
        $this->assertEquals(0, $resB->json('data.membres.total'));
    }

    // =========================================================================
    // 26. Soft delete respecté
    // =========================================================================
    public function test_26_soft_delete_respected(): void
    {
        $membre = Membre::create([
            'organisation_id' => $this->oppeA->id,
            'matricule'       => 'MB-SOFT-01',
            'nom'             => 'ToSoftDelete',
            'prenoms'         => 'Lucas',
            'sexe'            => 'M',
            'statut'          => 'actif',
        ]);

        Sanctum::actingAs($this->respOppeA);

        // Suppression logique
        $resDel = $this->deleteJson("/api/v1/organisation/membres/{$membre->id}");
        $resDel->assertStatus(200);

        $this->assertSoftDeleted('membres', ['id' => $membre->id]);

        // Doit retourner 404 lors d'un accès direct
        $resGet = $this->getJson("/api/v1/organisation/membres/{$membre->id}");
        $resGet->assertStatus(404);

        // Ne doit plus apparaître dans le listing normal
        $resList = $this->getJson('/api/v1/organisation/membres');
        $resList->assertStatus(200);
        $this->assertEmpty($resList->json('data'));
    }

    // =========================================================================
    // 27. Données sensibles absentes des erreurs
    // =========================================================================
    public function test_27_sensitive_details_absent_from_errors(): void
    {
        Sanctum::actingAs($this->respOppeA);

        // Erreur 404
        $res404 = $this->getJson('/api/v1/organisation/membres/999999');
        $res404->assertStatus(404);
        $body = $res404->getContent();

        $this->assertStringNotContainsString('SQLSTATE', $body);
        $this->assertStringNotContainsString('Stack trace', $body);
        $this->assertStringNotContainsString('PDOException', $body);
        $this->assertStringNotContainsString('DB_PASSWORD', $body);
    }

    // =========================================================================
    // 28. Secrets absents des réponses API
    // =========================================================================
    public function test_28_secrets_absent_from_api_responses(): void
    {
        Sanctum::actingAs($this->respOppeA);

        $response = $this->getJson('/api/v1/auth/me');
        $response->assertStatus(200);
        $data = $response->json();

        $rawContent = $response->getContent();
        $this->assertStringNotContainsString('password', $rawContent);
        $this->assertStringNotContainsString('remember_token', $rawContent);
    }
}
