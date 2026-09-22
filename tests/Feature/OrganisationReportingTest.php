<?php

namespace Tests\Feature;

use App\Models\Abonnement;
use App\Models\Activite;
use App\Models\AnneeCatechese;
use App\Models\CampagnePelerinage;
use App\Models\CatecheseConfiguration;
use App\Models\Catechumene;
use App\Models\Classe;
use App\Models\EcheanceAbonnement;
use App\Models\Facture;
use App\Models\Formule;
use App\Models\InscriptionAnnuelle;
use App\Models\InscriptionPelerinage;
use App\Models\Membre;
use App\Models\Niveau;
use App\Models\OperationOrganisation;
use App\Models\OperationPaiement;
use App\Models\Organisation;
use App\Models\Paiement;
use App\Models\PaiementAbonnement;
use App\Models\PaiementPelerinage;
use App\Models\Produit;
use App\Models\Profil;
use App\Models\Section;
use App\Models\TarifPelerinage;
use App\Models\User;
use Database\Seeders\OrganisationProfilSeeder;
use Database\Seeders\ProduitSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrganisationReportingTest extends TestCase
{
    use RefreshDatabase;

    protected CatecheseConfiguration $paroisseA;
    protected CatecheseConfiguration $paroisseB;
    protected CatecheseConfiguration $paroisseC;
    protected Produit $produitOppe;
    protected Produit $produitOppj;
    protected Produit $produitOppa;

    protected Organisation $oppeA;
    protected Organisation $oppjA;
    protected Organisation $oppaA;
    protected Organisation $oppeB;
    protected Organisation $orgInactive;

    protected User $respOppeA;
    protected User $userOppeA;
    protected User $respOppjA;
    protected User $respOppaA;
    protected User $respOppeB;
    protected User $userInactiveOrg;

    protected AnneeCatechese $anneeCouranteA;
    protected AnneeCatechese $anneeArchiveA;

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

        // Paroisses
        $this->paroisseA = CatecheseConfiguration::create([
            'nom_paroisse'      => 'Paroisse Saint Jean A',
            'code_paroisse'     => 'PAR-SJEAN-A-' . uniqid(),
            'prefixe_matricule' => 'SJA',
            'prefixe_recu'      => 'REC',
            'statut'            => 'actif',
            'ville'             => 'Abidjan',
            'diocese'           => 'Abidjan',
        ]);

        $this->paroisseB = CatecheseConfiguration::create([
            'nom_paroisse'      => 'Paroisse Sainte Anne B',
            'code_paroisse'     => 'PAR-SANNE-B-' . uniqid(),
            'prefixe_matricule' => 'SAB',
            'prefixe_recu'      => 'REC',
            'statut'            => 'actif',
            'ville'             => 'Yamoussoukro',
            'diocese'           => 'Yamoussoukro',
        ]);

        $this->paroisseC = CatecheseConfiguration::create([
            'nom_paroisse'      => 'Paroisse Saint Joseph C',
            'code_paroisse'     => 'PAR-SJOSE-C-' . uniqid(),
            'prefixe_matricule' => 'SJC',
            'prefixe_recu'      => 'REC',
            'statut'            => 'actif',
            'ville'             => 'Korhogo',
            'diocese'           => 'Korhogo',
        ]);

        // Années catéchétiques Paroisse A
        $this->anneeCouranteA = AnneeCatechese::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'libelle'                   => 'Année Pastorale 2025-2026',
            'date_debut'                => '2025-09-01',
            'date_fin'                  => '2026-06-30',
            'statut'                    => 'actif',
        ]);

        $this->anneeArchiveA = AnneeCatechese::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'libelle'                   => 'Année Pastorale 2024-2025 (Archive)',
            'date_debut'                => '2024-09-01',
            'date_fin'                  => '2025-06-30',
            'statut'                    => 'inactif',
        ]);

        // Sections
        $this->secEnfPri = Section::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'code'                      => 'SEC-ENFANTS-PRI',
            'nom'                       => 'ENFANT PRIMAIRE',
            'statut'                    => 'actif',
        ]);

        $this->secEnfCol = Section::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'code'                      => 'SEC-ENFANTS-COL',
            'nom'                       => 'ENFANT COLLÈGE',
            'statut'                    => 'actif',
        ]);

        $this->secJeunes = Section::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'code'                      => 'SEC-JEUNES',
            'nom'                       => 'JEUNES',
            'statut'                    => 'actif',
        ]);

        $this->secAdultes = Section::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'code'                      => 'SEC-ADULTES',
            'nom'                       => 'ADULTES',
            'statut'                    => 'actif',
        ]);

        // Organisations
        $this->oppeA = Organisation::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'produit_id'                => $this->produitOppe->id,
            'type_organisation'         => Organisation::TYPE_OPPE,
            'code'                      => 'OPPE-SJEAN-A',
            'nom'                       => 'OPPE Saint Jean A',
            'statut'                    => 'actif',
        ]);

        $this->oppjA = Organisation::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'produit_id'                => $this->produitOppj->id,
            'type_organisation'         => Organisation::TYPE_OPPJ,
            'code'                      => 'OPPJ-SJEAN-A',
            'nom'                       => 'OPPJ Saint Jean A',
            'statut'                    => 'actif',
        ]);

        $this->oppaA = Organisation::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'produit_id'                => $this->produitOppa->id,
            'type_organisation'         => Organisation::TYPE_OPPA,
            'code'                      => 'OPPA-SJEAN-A',
            'nom'                       => 'OPPA Saint Jean A',
            'statut'                    => 'actif',
        ]);

        $this->oppeB = Organisation::create([
            'paroisse_configuration_id' => $this->paroisseB->id,
            'produit_id'                => $this->produitOppe->id,
            'type_organisation'         => Organisation::TYPE_OPPE,
            'code'                      => 'OPPE-SANNE-B',
            'nom'                       => 'OPPE Sainte Anne B',
            'statut'                    => 'actif',
        ]);

        $this->orgInactive = Organisation::create([
            'paroisse_configuration_id' => $this->paroisseC->id,
            'produit_id'                => $this->produitOppe->id,
            'type_organisation'         => Organisation::TYPE_OPPE,
            'code'                      => 'OPPE-INACTIVE',
            'nom'                       => 'OPPE Inactive',
            'statut'                    => 'suspendu',
        ]);

        // Profils
        $profilRespOppe = Profil::where('code', 'RESPONSABLE_OPPE')->firstOrFail();
        $profilUserOppe = Profil::where('code', 'UTILISATEUR_OPPE')->firstOrFail();
        $profilRespOppj = Profil::where('code', 'RESPONSABLE_OPPJ')->firstOrFail();
        $profilRespOppa = Profil::where('code', 'RESPONSABLE_OPPA')->firstOrFail();

        // Users
        $this->respOppeA = User::create([
            'name'                      => 'Responsable OPPE A',
            'email'                     => 'resp.oppe.a@test.com',
            'password'                  => Hash::make('Password123!'),
            'profil_id'                 => $profilRespOppe->id,
            'paroisse_configuration_id' => $this->paroisseA->id,
            'organisation_id'           => $this->oppeA->id,
            'statut_compte'             => 'actif',
        ]);

        $this->userOppeA = User::create([
            'name'                      => 'Animateur OPPE A (Sans export ni caisse)',
            'email'                     => 'user.oppe.a@test.com',
            'password'                  => Hash::make('Password123!'),
            'profil_id'                 => $profilUserOppe->id,
            'paroisse_configuration_id' => $this->paroisseA->id,
            'organisation_id'           => $this->oppeA->id,
            'statut_compte'             => 'actif',
        ]);

        $this->respOppjA = User::create([
            'name'                      => 'Responsable OPPJ A',
            'email'                     => 'resp.oppj.a@test.com',
            'password'                  => Hash::make('Password123!'),
            'profil_id'                 => $profilRespOppj->id,
            'paroisse_configuration_id' => $this->paroisseA->id,
            'organisation_id'           => $this->oppjA->id,
            'statut_compte'             => 'actif',
        ]);

        $this->respOppaA = User::create([
            'name'                      => 'Responsable OPPA A',
            'email'                     => 'resp.oppa.a@test.com',
            'password'                  => Hash::make('Password123!'),
            'profil_id'                 => $profilRespOppa->id,
            'paroisse_configuration_id' => $this->paroisseA->id,
            'organisation_id'           => $this->oppaA->id,
            'statut_compte'             => 'actif',
        ]);

        $this->respOppeB = User::create([
            'name'                      => 'Responsable OPPE B',
            'email'                     => 'resp.oppe.b@test.com',
            'password'                  => Hash::make('Password123!'),
            'profil_id'                 => $profilRespOppe->id,
            'paroisse_configuration_id' => $this->paroisseB->id,
            'organisation_id'           => $this->oppeB->id,
            'statut_compte'             => 'actif',
        ]);

        $this->userInactiveOrg = User::create([
            'name'                      => 'User Org Inactive',
            'email'                     => 'user.inactive@test.com',
            'password'                  => Hash::make('Password123!'),
            'profil_id'                 => $profilRespOppe->id,
            'paroisse_configuration_id' => $this->paroisseC->id,
            'organisation_id'           => $this->orgInactive->id,
            'statut_compte'             => 'actif',
        ]);
    }

    /**
     * Helper pour inscrire un catéchumène.
     */
    protected function inscrireCatechumene(CatecheseConfiguration $paroisse, AnneeCatechese $annee, Section $section, string $nom, string $prenoms): Catechumene
    {
        $cat = Catechumene::create([
            'paroisse_configuration_id' => $paroisse->id,
            'matricule'                 => 'CAT-' . uniqid(),
            'nom'                       => $nom,
            'prenoms'                   => $prenoms,
            'sexe'                      => 'M',
            'date_naissance'            => '2014-06-15',
        ]);

        $niv = Niveau::firstOrCreate([
            'paroisse_configuration_id' => $paroisse->id,
            'section_id'                => $section->id,
            'nom'                       => 'Niveau ' . $section->code,
        ], ['ordre' => 1]);

        $cls = Classe::firstOrCreate([
            'paroisse_configuration_id' => $paroisse->id,
            'annee_catechese_id'        => $annee->id,
            'niveau_id'                 => $niv->id,
            'nom'                       => 'Classe ' . $section->code,
        ]);

        InscriptionAnnuelle::create([
            'paroisse_configuration_id' => $paroisse->id,
            'annee_catechese_id'        => $annee->id,
            'catechumene_id'            => $cat->id,
            'section_id'                => $section->id,
            'niveau_id'                 => $niv->id,
            'classe_id'                 => $cls->id,
            'statut'                    => 'actif',
        ]);

        return $cat;
    }

    // ─────────────────────────────────────────────────────────────
    // 1, 17. DASHBOARD OPPE & POPULATION CATHEO OPPE
    // ─────────────────────────────────────────────────────────────

    public function test_dashboard_oppe_indicators_and_catheo_population(): void
    {
        Sanctum::actingAs($this->respOppeA);

        // Données Organisation OPPE A
        Membre::create([
            'organisation_id' => $this->oppeA->id,
            'nom'             => 'Kouassi',
            'prenoms'         => 'Alain',
            'sexe'            => 'M',
            'statut'          => 'actif',
        ]);
        Membre::create([
            'organisation_id' => $this->oppeA->id,
            'nom'             => 'Konan',
            'prenoms'         => 'Clarisse',
            'sexe'            => 'F',
            'statut'          => 'inactif',
        ]);

        $activite = Activite::create([
            'organisation_id' => $this->oppeA->id,
            'code'            => 'ACT-TEST-01',
            'titre'           => 'Sortie Enfants',
            'statut'          => Activite::STATUT_PLANIFIEE,
            'date_debut'      => '2026-03-01',
            'taux_execution'  => 50,
        ]);

        $campagne = CampagnePelerinage::create([
            'organisation_id' => $this->oppeA->id,
            'activite_id'     => $activite->id,
            'code'            => 'CAMP-OPPE-2026',
            'nom'             => 'Pèlerinage des Petits Anges',
            'lieu_depart'     => 'Abidjan',
            'destination'     => 'Basilique Yamoussoukro',
            'date_depart'     => '2026-04-10',
            'date_fin'        => '2026-04-12',
            'capacite'        => 50,
            'statut'          => CampagnePelerinage::STATUT_OUVERTE,
        ]);

        $tarif = TarifPelerinage::create([
            'campagne_pelerinage_id' => $campagne->id,
            'code'                   => 'TARIF-ENFANT',
            'libelle'                => 'Tarif Enfant',
            'montant'                => 15000,
            'type_public'            => 'ENFANT',
            'statut'                 => 'actif',
        ]);

        $inscription = InscriptionPelerinage::create([
            'campagne_pelerinage_id' => $campagne->id,
            'tarif_pelerinage_id'    => $tarif->id,
            'reference'              => 'INS-2026-001',
            'nom'                    => 'Yao',
            'prenoms'                => 'Jean',
            'sexe'                   => 'M',
            'type_participant'       => InscriptionPelerinage::TYPE_EXTERNE,
            'montant'                => 15000,
            'montant_paye'           => 15000,
            'reste_a_payer'          => 0,
            'statut_inscription'     => InscriptionPelerinage::STATUT_PAYEE,
            'statut_participation'   => InscriptionPelerinage::PARTICIPATION_PRESENTE,
        ]);

        PaiementPelerinage::create([
            'inscription_pelerinage_id' => $inscription->id,
            'reference'                 => 'PAY-2026-001',
            'montant'                   => 15000,
            'mode_paiement'             => 'ESPECES',
            'date_paiement'             => now(),
            'statut'                    => PaiementPelerinage::STATUT_VALIDE,
            'enregistre_par'            => $this->respOppeA->id,
        ]);

        // Données CATHEO Paroisse A : 2 Primaire, 1 Collège, 1 Jeune (hors périmètre OPPE)
        $this->inscrireCatechumene($this->paroisseA, $this->anneeCouranteA, $this->secEnfPri, 'Enfant', 'Pri 1');
        $this->inscrireCatechumene($this->paroisseA, $this->anneeCouranteA, $this->secEnfPri, 'Enfant', 'Pri 2');
        $this->inscrireCatechumene($this->paroisseA, $this->anneeCouranteA, $this->secEnfCol, 'Enfant', 'Col 1');
        $this->inscrireCatechumene($this->paroisseA, $this->anneeCouranteA, $this->secJeunes, 'Jeune', 'J 1');

        $response = $this->getJson('/api/v1/organisation/dashboard?fresh=true');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.membres.total', 2)
            ->assertJsonPath('data.membres.actifs', 1)
            ->assertJsonPath('data.membres.inactifs', 1)
            ->assertJsonPath('data.activites.total', 1)
            ->assertJsonPath('data.activites.planifiees', 1)
            ->assertJsonPath('data.pelerinages.campagnes_total', 1)
            ->assertJsonPath('data.pelerinages.campagnes_ouvertes', 1)
            ->assertJsonPath('data.pelerinages.total_inscrits', 1)
            ->assertJsonPath('data.pelerinages.inscrits_payes', 1)
            ->assertJsonPath('data.pelerinages.inscrits_presents', 1)
            ->assertJsonPath('data.pelerinages.montant_attendu', 15000)
            ->assertJsonPath('data.pelerinages.montant_encaisse', 15000)
            ->assertJsonPath('data.pelerinages.reste_a_encaisser', 0)
            ->assertJsonPath('data.catheo.catheo_connecte', true)
            ->assertJsonPath('data.catheo.total_population', 3)
            ->assertJsonPath('data.catheo.total_primaire', 2)
            ->assertJsonPath('data.catheo.total_college', 1);
    }

    // ─────────────────────────────────────────────────────────────
    // 2, 18. DASHBOARD OPPJ & POPULATION CATHEO OPPJ
    // ─────────────────────────────────────────────────────────────

    public function test_dashboard_oppj_indicators_and_catheo_population(): void
    {
        Sanctum::actingAs($this->respOppjA);

        $this->inscrireCatechumene($this->paroisseA, $this->anneeCouranteA, $this->secJeunes, 'Jeune', 'Uno');
        $this->inscrireCatechumene($this->paroisseA, $this->anneeCouranteA, $this->secJeunes, 'Jeune', 'Dos');
        $this->inscrireCatechumene($this->paroisseA, $this->anneeCouranteA, $this->secEnfPri, 'Enfant', 'Hors Oppj');

        $response = $this->getJson('/api/v1/organisation/dashboard?fresh=true');

        $response->assertStatus(200)
            ->assertJsonPath('data.catheo.catheo_connecte', true)
            ->assertJsonPath('data.catheo.total_population', 2)
            ->assertJsonPath('data.catheo.total_jeunes', 2);
    }

    // ─────────────────────────────────────────────────────────────
    // 3, 19. DASHBOARD OPPA & POPULATION CATHEO OPPA
    // ─────────────────────────────────────────────────────────────

    public function test_dashboard_oppa_indicators_and_catheo_population(): void
    {
        Sanctum::actingAs($this->respOppaA);

        $this->inscrireCatechumene($this->paroisseA, $this->anneeCouranteA, $this->secAdultes, 'Adulte', 'Senior');
        $this->inscrireCatechumene($this->paroisseA, $this->anneeCouranteA, $this->secJeunes, 'Jeune', 'Hors Oppa');

        $response = $this->getJson('/api/v1/organisation/dashboard?fresh=true');

        $response->assertStatus(200)
            ->assertJsonPath('data.catheo.catheo_connecte', true)
            ->assertJsonPath('data.catheo.total_population', 1)
            ->assertJsonPath('data.catheo.total_adultes', 1);
    }

    // ─────────────────────────────────────────────────────────────
    // 4, 26. DASHBOARD SANS CATHEO CONNECTÉ
    // ─────────────────────────────────────────────────────────────

    public function test_dashboard_disconnected_catheo_when_no_active_year(): void
    {
        // Paroisse B sans année catéchétique active
        Sanctum::actingAs($this->respOppeB);

        $response = $this->getJson('/api/v1/organisation/dashboard?fresh=true');

        $response->assertStatus(200)
            ->assertJsonPath('data.catheo.catheo_connecte', false);
    }

    // ─────────────────────────────────────────────────────────────
    // 5. STATISTIQUES MEMBRES ET FILTRES
    // ─────────────────────────────────────────────────────────────

    public function test_statistiques_membres_with_filters(): void
    {
        Sanctum::actingAs($this->respOppeA);

        Membre::create([
            'organisation_id' => $this->oppeA->id,
            'nom'             => 'Dufour',
            'prenoms'         => 'Marc',
            'sexe'            => 'M',
            'fonction'        => 'Animateur',
            'date_entree'     => '2025-01-10',
            'statut'          => 'actif',
        ]);
        Membre::create([
            'organisation_id' => $this->oppeA->id,
            'nom'             => 'Dufour',
            'prenoms'         => 'Sophie',
            'sexe'            => 'F',
            'fonction'        => 'Secrétaire',
            'date_entree'     => '2025-02-15',
            'statut'          => 'actif',
        ]);
        Membre::create([
            'organisation_id' => $this->oppeA->id,
            'nom'             => 'Koffi',
            'prenoms'         => 'Paul',
            'sexe'            => 'M',
            'fonction'        => 'Animateur',
            'date_entree'     => '2024-05-20',
            'statut'          => 'inactif',
        ]);

        // Global
        $resp = $this->getJson('/api/v1/organisation/statistiques/membres');
        $resp->assertStatus(200)
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.actifs', 2)
            ->assertJsonPath('data.inactifs', 1)
            ->assertJsonPath('data.repartition_sexe.M', 2)
            ->assertJsonPath('data.repartition_sexe.F', 1);

        // Filtre par sexe et fonction
        $filtered = $this->getJson('/api/v1/organisation/statistiques/membres?sexe=M&fonction=Animateur&statut=actif');
        $filtered->assertStatus(200)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.actifs', 1);
    }

    // ─────────────────────────────────────────────────────────────
    // 6. STATISTIQUES ACTIVITÉS ET FILTRES
    // ─────────────────────────────────────────────────────────────

    public function test_statistiques_activites_with_filters(): void
    {
        Sanctum::actingAs($this->respOppeA);

        Activite::create([
            'organisation_id' => $this->oppeA->id,
            'code'            => 'ACT-001',
            'titre'           => 'Activité 1',
            'type_activite'   => 'REUNION',
            'statut'          => Activite::STATUT_TERMINEE,
            'date_debut'      => '2025-10-01',
            'taux_execution'  => 100,
        ]);
        Activite::create([
            'organisation_id' => $this->oppeA->id,
            'code'            => 'ACT-002',
            'titre'           => 'Activité 2',
            'type_activite'   => 'FORMATION',
            'statut'          => Activite::STATUT_PLANIFIEE,
            'date_debut'      => '2025-11-15',
            'taux_execution'  => 0,
        ]);

        $res = $this->getJson('/api/v1/organisation/statistiques/activites');
        $res->assertStatus(200)
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.taux_moyen_execution', 50)
            ->assertJsonPath('data.repartition_statut.terminee', 1)
            ->assertJsonPath('data.repartition_statut.planifiee', 1);

        // Filtre par type
        $resFilter = $this->getJson('/api/v1/organisation/statistiques/activites?type_activite=REUNION');
        $resFilter->assertStatus(200)
            ->assertJsonPath('data.total', 1)
            ->assertJsonPath('data.taux_moyen_execution', 100);
    }

    // ─────────────────────────────────────────────────────────────
    // 7, 16. STATISTIQUES PÈLERINAGES ET FILTRE PAR CAMPAGNE
    // ─────────────────────────────────────────────────────────────

    public function test_statistiques_pelerinages_with_filters(): void
    {
        Sanctum::actingAs($this->respOppeA);

        $campagne1 = CampagnePelerinage::create([
            'organisation_id' => $this->oppeA->id,
            'code'            => 'CAMP-01',
            'nom'             => 'Campagne 1',
            'lieu_depart'     => 'Abidjan',
            'destination'     => 'Yamoussoukro',
            'date_depart'     => '2026-05-01',
            'date_fin'        => '2026-05-03',
            'capacite'        => 100,
            'statut'          => CampagnePelerinage::STATUT_OUVERTE,
        ]);

        $tarif1 = TarifPelerinage::create([
            'campagne_pelerinage_id' => $campagne1->id,
            'code'                   => 'T1',
            'libelle'                => 'Tarif Unique',
            'montant'                => 10000,
            'type_public'            => 'GENERAL',
            'statut'                 => 'actif',
        ]);

        // Inscrit 1 : Payé et présent
        $ins1 = InscriptionPelerinage::create([
            'campagne_pelerinage_id' => $campagne1->id,
            'tarif_pelerinage_id'    => $tarif1->id,
            'reference'              => 'INS-P-01',
            'nom'                    => 'Brou',
            'prenoms'                => 'Marc',
            'sexe'                   => 'M',
            'type_participant'       => InscriptionPelerinage::TYPE_CATECHUMENE,
            'montant'                => 10000,
            'montant_paye'           => 10000,
            'reste_a_payer'          => 0,
            'statut_inscription'     => InscriptionPelerinage::STATUT_PAYEE,
            'statut_participation'   => InscriptionPelerinage::PARTICIPATION_PRESENTE,
        ]);

        PaiementPelerinage::create([
            'inscription_pelerinage_id' => $ins1->id,
            'reference'                 => 'PAY-001',
            'montant'                   => 10000,
            'mode_paiement'             => 'ESPECES',
            'date_paiement'             => now(),
            'statut'                    => PaiementPelerinage::STATUT_VALIDE,
            'enregistre_par'            => $this->respOppeA->id,
        ]);

        // Inscrit 2 : Partiel et absent
        InscriptionPelerinage::create([
            'campagne_pelerinage_id' => $campagne1->id,
            'tarif_pelerinage_id'    => $tarif1->id,
            'reference'              => 'INS-P-02',
            'nom'                    => 'Bamba',
            'prenoms'                => 'Awa',
            'sexe'                   => 'F',
            'type_participant'       => InscriptionPelerinage::TYPE_EXTERNE,
            'montant'                => 10000,
            'montant_paye'           => 4000,
            'reste_a_payer'          => 6000,
            'statut_inscription'     => InscriptionPelerinage::STATUT_PARTIELLEMENT_PAYEE,
            'statut_participation'   => InscriptionPelerinage::PARTICIPATION_ABSENTE,
        ]);

        $res = $this->getJson('/api/v1/organisation/statistiques/pelerinages');
        $res->assertStatus(200)
            ->assertJsonPath('data.campagnes.total', 1)
            ->assertJsonPath('data.campagnes.capacite_totale', 100)
            ->assertJsonPath('data.campagnes.places_occupees', 2)
            ->assertJsonPath('data.campagnes.places_restantes', 98)
            ->assertJsonPath('data.inscriptions.total', 2)
            ->assertJsonPath('data.inscriptions.catheo', 1)
            ->assertJsonPath('data.inscriptions.externes', 1)
            ->assertJsonPath('data.inscriptions.payes', 1)
            ->assertJsonPath('data.inscriptions.partiellement_payes', 1)
            ->assertJsonPath('data.inscriptions.presents', 1)
            ->assertJsonPath('data.inscriptions.absents', 1)
            ->assertJsonPath('data.inscriptions.taux_presence', 50)
            ->assertJsonPath('data.finances.montant_attendu', 20000)
            ->assertJsonPath('data.finances.montant_encaisse', 10000)
            ->assertJsonPath('data.finances.solde_restant', 10000);

        // Filtre par campagne
        $resCampagne = $this->getJson("/api/v1/organisation/statistiques/pelerinages?campagne_id={$campagne1->id}");
        $resCampagne->assertStatus(200)
            ->assertJsonPath('data.inscriptions.total', 2);
    }

    // ─────────────────────────────────────────────────────────────
    // 8, 15. STATISTIQUES FINANCIÈRES ET FILTRAGE PAR PÉRIODE
    // ─────────────────────────────────────────────────────────────

    public function test_statistiques_finances_with_filters(): void
    {
        Sanctum::actingAs($this->respOppeA);

        OperationOrganisation::create([
            'organisation_id' => $this->oppeA->id,
            'reference'       => 'OP-01',
            'type_operation'  => OperationOrganisation::TYPE_ENTREE,
            'libelle'         => 'Don paroissial',
            'montant'         => 50000,
            'date_operation'  => '2025-05-10',
            'mode_reglement'  => 'VIREMENT',
            'statut'          => OperationOrganisation::STATUT_VALIDE,
            'created_by'      => $this->respOppeA->id,
        ]);

        OperationOrganisation::create([
            'organisation_id' => $this->oppeA->id,
            'reference'       => 'OP-02',
            'type_operation'  => OperationOrganisation::TYPE_SORTIE,
            'libelle'         => 'Achat matériel',
            'montant'         => 15000,
            'date_operation'  => '2025-05-15',
            'mode_reglement'  => 'ESPECES',
            'statut'          => OperationOrganisation::STATUT_VALIDE,
            'created_by'      => $this->respOppeA->id,
        ]);

        // Autre mois
        OperationOrganisation::create([
            'organisation_id' => $this->oppeA->id,
            'reference'       => 'OP-03',
            'type_operation'  => OperationOrganisation::TYPE_ENTREE,
            'libelle'         => 'Vente gâteaux',
            'montant'         => 20000,
            'date_operation'  => '2025-06-01',
            'mode_reglement'  => 'ESPECES',
            'statut'          => OperationOrganisation::STATUT_VALIDE,
            'created_by'      => $this->respOppeA->id,
        ]);

        $res = $this->getJson('/api/v1/organisation/statistiques/finances');
        $res->assertStatus(200)
            ->assertJsonPath('data.total_entrees', 70000)
            ->assertJsonPath('data.total_sorties', 15000)
            ->assertJsonPath('data.solde', 55000);

        // Filtre mai 2025
        $resMai = $this->getJson('/api/v1/organisation/statistiques/finances?date_debut=2025-05-01&date_fin=2025-05-31');
        $resMai->assertStatus(200)
            ->assertJsonPath('data.total_entrees', 50000)
            ->assertJsonPath('data.total_sorties', 15000)
            ->assertJsonPath('data.solde', 35000);
    }

    // ─────────────────────────────────────────────────────────────
    // 9. ÉTAT DE CAISSE AVEC SOLDE INITIAL ET OPÉRATEUR
    // ─────────────────────────────────────────────────────────────

    public function test_etat_de_caisse_with_initial_balance_and_operator(): void
    {
        Sanctum::actingAs($this->respOppeA);

        // Opération antérieure (avant le 1er juin 2025)
        OperationOrganisation::create([
            'organisation_id' => $this->oppeA->id,
            'reference'       => 'OP-ANT-01',
            'type_operation'  => OperationOrganisation::TYPE_ENTREE,
            'libelle'         => 'Solde antérieur reporté',
            'montant'         => 40000,
            'date_operation'  => '2025-05-20',
            'statut'          => OperationOrganisation::STATUT_VALIDE,
            'created_by'      => $this->respOppeA->uuid,
        ]);

        // Opération dans la période (juin 2025)
        $opPeriode = OperationOrganisation::create([
            'organisation_id' => $this->oppeA->id,
            'reference'       => 'OP-PER-01',
            'type_operation'  => OperationOrganisation::TYPE_ENTREE,
            'libelle'         => 'Cotisation juin',
            'montant'         => 10000,
            'date_operation'  => '2025-06-10',
            'statut'          => OperationOrganisation::STATUT_VALIDE,
            'created_by'      => $this->respOppeA->uuid,
        ]);

        $res = $this->getJson('/api/v1/organisation/caisse?date_debut=2025-06-01&date_fin=2025-06-30');

        $res->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('synthese.solde_initial', 40000)
            ->assertJsonPath('synthese.total_entrees', 10000)
            ->assertJsonPath('synthese.total_sorties', 0)
            ->assertJsonPath('synthese.solde_periode', 10000)
            ->assertJsonPath('synthese.solde_final', 50000)
            ->assertJsonPath('synthese.nombre_operations', 1);

        // Traçabilité opérateur
        $data = $res->json('data');
        $this->assertNotEmpty($data);
        $this->assertEquals($this->respOppeA->name, $data[0]['operateur']['name']);
    }

    // ─────────────────────────────────────────────────────────────
    // 10. RAPPORT ANNUEL
    // ─────────────────────────────────────────────────────────────

    public function test_rapport_annuel_consolidation(): void
    {
        Sanctum::actingAs($this->respOppeA);

        Membre::create([
            'organisation_id' => $this->oppeA->id,
            'nom'             => 'Test',
            'prenoms'         => 'Rapport',
            'sexe'            => 'M',
            'date_entree'     => '2025-02-01',
            'statut'          => 'actif',
        ]);

        Activite::create([
            'organisation_id' => $this->oppeA->id,
            'code'            => 'ACT-RAP-01',
            'titre'           => 'Activité 2025',
            'date_debut'      => '2025-08-01',
            'statut'          => Activite::STATUT_TERMINEE,
            'taux_execution'  => 100,
        ]);

        OperationOrganisation::create([
            'organisation_id' => $this->oppeA->id,
            'reference'       => 'OP-RAP-01',
            'type_operation'  => OperationOrganisation::TYPE_ENTREE,
            'libelle'         => 'Entrée 2025',
            'montant'         => 30000,
            'date_operation'  => '2025-07-01',
            'statut'          => OperationOrganisation::STATUT_VALIDE,
            'created_by'      => $this->respOppeA->id,
        ]);

        $res = $this->getJson('/api/v1/organisation/rapports/annuel?annee=2025');

        $res->assertStatus(200)
            ->assertJsonPath('data.annee_exercice', 2025)
            ->assertJsonPath('data.membres.total', 1)
            ->assertJsonPath('data.membres.actifs', 1)
            ->assertJsonPath('data.activites.total', 1)
            ->assertJsonPath('data.finances.total_entrees', 30000)
            ->assertJsonPath('data.finances.solde_net', 30000)
            ->assertJsonPath('data.catheo.catheo_connecte', true);
    }

    // ─────────────────────────────────────────────────────────────
    // 11. EXPORT CSV MEMBRES
    // ─────────────────────────────────────────────────────────────

    public function test_exports_csv_membres(): void
    {
        Sanctum::actingAs($this->respOppeA);

        Membre::create([
            'organisation_id' => $this->oppeA->id,
            'nom'             => 'Koffi',
            'prenoms'         => 'Armand',
            'sexe'            => 'M',
            'telephone'       => '0102030405',
            'statut'          => 'actif',
        ]);

        $res = $this->get('/api/v1/organisation/exports/membres');

        $res->assertStatus(200);
        $res->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $res->streamedContent();
        $this->assertStringContainsString('Koffi', $content);
        $this->assertStringContainsString('Armand', $content);
        $this->assertStringContainsString(';', $content); // Délimiteur point-virgule
    }

    // ─────────────────────────────────────────────────────────────
    // 12. EXPORT CSV ACTIVITÉS
    // ─────────────────────────────────────────────────────────────

    public function test_exports_csv_activites(): void
    {
        Sanctum::actingAs($this->respOppeA);

        Activite::create([
            'organisation_id' => $this->oppeA->id,
            'code'            => 'ACT-EXP-01',
            'titre'           => 'Rassemblement Diocésain',
            'type_activite'   => 'RASSEMBLEMENT',
            'statut'          => Activite::STATUT_PLANIFIEE,
            'date_debut'      => '2026-05-10 09:00:00',
        ]);

        $res = $this->get('/api/v1/organisation/exports/activites');

        $res->assertStatus(200);
        $res->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $content = $res->streamedContent();
        $this->assertStringContainsString('ACT-EXP-01', $content);
        $this->assertStringContainsString('Rassemblement Diocésain', $content);
    }

    // ─────────────────────────────────────────────────────────────
    // 13, 14. EXPORT CSV PARTICIPANTS PÈLERINAGE (PROFILS LOGISTIQUES)
    // ─────────────────────────────────────────────────────────────

    public function test_exports_csv_participants_logistic_profiles(): void
    {
        Sanctum::actingAs($this->respOppeA);

        $campagne = CampagnePelerinage::create([
            'organisation_id' => $this->oppeA->id,
            'code'            => 'CAMP-LOGISTIQUE',
            'nom'             => 'Pèlerinage Nord',
            'lieu_depart'     => 'Paroisse St Jean',
            'destination'     => 'Katiola',
            'date_depart'     => '2026-07-01',
            'date_fin'        => '2026-07-05',
            'capacite'        => 50,
            'statut'          => CampagnePelerinage::STATUT_OUVERTE,
        ]);

        $tarif = TarifPelerinage::create([
            'campagne_pelerinage_id' => $campagne->id,
            'code'                   => 'T-LOG',
            'libelle'                => 'Tarif Log',
            'montant'                => 25000,
            'type_public'            => 'GENERAL',
            'statut'                 => 'actif',
        ]);

        InscriptionPelerinage::create([
            'campagne_pelerinage_id'     => $campagne->id,
            'tarif_pelerinage_id'        => $tarif->id,
            'reference'                  => 'INS-LOG-001',
            'nom'                        => 'N\'Goran',
            'prenoms'                    => 'Cédric',
            'sexe'                       => 'M',
            'telephone'                  => '0505050505',
            'contact_urgence_nom'        => 'Maman N\'Goran',
            'contact_urgence_telephone'  => '0707070707',
            'type_participant'           => InscriptionPelerinage::TYPE_EXTERNE,
            'montant'                    => 25000,
            'montant_paye'               => 25000,
            'badge_imprime'              => true,
            'kit_remis'                  => true,
            'statut_inscription'         => InscriptionPelerinage::STATUT_PAYEE,
            'statut_participation'       => InscriptionPelerinage::PARTICIPATION_PRESENTE,
        ]);

        // Profil général
        $resGen = $this->get("/api/v1/organisation/exports/pelerinages/{$campagne->id}/participants?type_export=general");
        $resGen->assertStatus(200);
        $contentGen = $resGen->streamedContent();
        $this->assertStringContainsString('N\'Goran', $contentGen);
        $this->assertStringContainsString('INS-LOG-001', $contentGen);

        // Profil transport
        $resTrans = $this->get("/api/v1/organisation/exports/pelerinages/{$campagne->id}/participants?type_export=transport");
        $resTrans->assertStatus(200);
        $contentTrans = $resTrans->streamedContent();
        $this->assertStringContainsString('Lieu Départ', $contentTrans);
        $this->assertStringContainsString('Katiola', $contentTrans);
        $this->assertStringContainsString('Maman N\'Goran', $contentTrans);

        // Profil hébergement
        $resHeb = $this->get("/api/v1/organisation/exports/pelerinages/{$campagne->id}/participants?type_export=hebergement");
        $resHeb->assertStatus(200);
        $contentHeb = $resHeb->streamedContent();
        $this->assertStringContainsString('Destination', $contentHeb);
        $this->assertStringContainsString('Katiola', $contentHeb);

        // Profil embarquement
        $resEmb = $this->get("/api/v1/organisation/exports/pelerinages/{$campagne->id}/participants?type_export=embarquement");
        $resEmb->assertStatus(200);
        $contentEmb = $resEmb->streamedContent();
        $this->assertStringContainsString('Badge Imprimé', $contentEmb);
        $this->assertStringContainsString('Kit Remis', $contentEmb);
        $this->assertStringContainsString('OUI', $contentEmb);
    }

    // ─────────────────────────────────────────────────────────────
    // 14. EXPORT CSV PAIEMENTS ET OPÉRATIONS DE CAISSE
    // ─────────────────────────────────────────────────────────────

    public function test_exports_csv_paiements_and_operations(): void
    {
        Sanctum::actingAs($this->respOppeA);

        $campagne = CampagnePelerinage::create([
            'organisation_id' => $this->oppeA->id,
            'code'            => 'CAMP-PAY-EXP',
            'nom'             => 'Pèlerinage Paiements',
            'lieu_depart'     => 'Abidjan',
            'destination'     => 'San Pedro',
            'date_depart'     => '2026-08-10',
            'date_fin'        => '2026-08-14',
            'capacite'        => 20,
            'statut'          => CampagnePelerinage::STATUT_OUVERTE,
        ]);

        $tarif = TarifPelerinage::create([
            'campagne_pelerinage_id' => $campagne->id,
            'code'                   => 'T-PAY',
            'libelle'                => 'Tarif Base',
            'montant'                => 10000,
            'type_public'            => 'GENERAL',
            'statut'                 => 'actif',
        ]);

        $ins = InscriptionPelerinage::create([
            'campagne_pelerinage_id' => $campagne->id,
            'tarif_pelerinage_id'    => $tarif->id,
            'reference'              => 'INS-EXP-PAY-01',
            'nom'                    => 'Gnamien',
            'prenoms'                => 'Paul',
            'sexe'                   => 'M',
            'montant'                => 10000,
            'statut_inscription'     => InscriptionPelerinage::STATUT_PAYEE,
        ]);

        PaiementPelerinage::create([
            'inscription_pelerinage_id' => $ins->id,
            'reference'                 => 'REC-EXP-999',
            'montant'                   => 10000,
            'mode_paiement'             => 'ESPECES',
            'date_paiement'             => now(),
            'statut'                    => PaiementPelerinage::STATUT_VALIDE,
            'enregistre_par'            => $this->respOppeA->id,
        ]);

        OperationOrganisation::create([
            'organisation_id' => $this->oppeA->id,
            'reference'       => 'OP-CAISSE-EXP',
            'type_operation'  => OperationOrganisation::TYPE_ENTREE,
            'libelle'         => 'Paiement pèlerin Gnamien',
            'montant'         => 10000,
            'date_operation'  => now(),
            'statut'          => OperationOrganisation::STATUT_VALIDE,
            'created_by'      => $this->respOppeA->id,
        ]);

        // Export paiements
        $resPay = $this->get("/api/v1/organisation/exports/pelerinages/{$campagne->id}/paiements");
        $resPay->assertStatus(200);
        $contentPay = $resPay->streamedContent();
        $this->assertStringContainsString('REC-EXP-999', $contentPay);
        $this->assertStringContainsString('Gnamien', $contentPay);

        // Export opérations
        $resOp = $this->get('/api/v1/organisation/exports/operations');
        $resOp->assertStatus(200);
        $contentOp = $resOp->streamedContent();
        $this->assertStringContainsString('OP-CAISSE-EXP', $contentOp);

        // Export caisse
        $resCaisse = $this->get('/api/v1/organisation/exports/caisse');
        $resCaisse->assertStatus(200);
        $contentCaisse = $resCaisse->streamedContent();
        $this->assertStringContainsString('OP-CAISSE-EXP', $contentCaisse);
    }

    // ─────────────────────────────────────────────────────────────
    // 20. ANNÉE CATÉCHÉTIQUE ACTIVE UNIQUEMENT
    // ─────────────────────────────────────────────────────────────

    public function test_catheo_active_year_only(): void
    {
        Sanctum::actingAs($this->respOppeA);

        // 1 enfant inscrit sur l'année en cours
        $this->inscrireCatechumene($this->paroisseA, $this->anneeCouranteA, $this->secEnfPri, 'Actif', 'Courant');

        // 2 enfants inscrits UNIQUEMENT sur l'année archivée
        $this->inscrireCatechumene($this->paroisseA, $this->anneeArchiveA, $this->secEnfPri, 'Archive', 'Old 1');
        $this->inscrireCatechumene($this->paroisseA, $this->anneeArchiveA, $this->secEnfPri, 'Archive', 'Old 2');

        $res = $this->getJson('/api/v1/organisation/dashboard?fresh=true');

        $res->assertStatus(200)
            ->assertJsonPath('data.catheo.total_population', 1)
            ->assertJsonPath('data.catheo.total_primaire', 1);
    }

    // ─────────────────────────────────────────────────────────────
    // 21, 22. ISOLATION ORGANISATIONNELLE ET ENTRE PAROISSES
    // ─────────────────────────────────────────────────────────────

    public function test_multi_tenant_organisation_and_parish_isolation(): void
    {
        // Données privées de OPPE A (Paroisse A)
        Membre::create([
            'organisation_id' => $this->oppeA->id,
            'nom'             => 'SecretA',
            'prenoms'         => 'Alpha',
            'sexe'            => 'M',
            'statut'          => 'actif',
        ]);

        OperationOrganisation::create([
            'organisation_id' => $this->oppeA->id,
            'reference'       => 'OP-SECRET-A',
            'type_operation'  => OperationOrganisation::TYPE_ENTREE,
            'libelle'         => 'Fonds secrets A',
            'montant'         => 999999,
            'date_operation'  => now(),
            'statut'          => OperationOrganisation::STATUT_VALIDE,
            'created_by'      => $this->respOppeA->id,
        ]);

        // Connecté en tant que Responsable OPPE B (Paroisse B)
        Sanctum::actingAs($this->respOppeB);

        // Dashboard B ne doit pas voir A
        $resDash = $this->getJson('/api/v1/organisation/dashboard?fresh=true');
        $resDash->assertStatus(200)
            ->assertJsonPath('data.membres.total', 0)
            ->assertJsonPath('data.finances.total_entrees', 0);

        // Statistiques membres B ne doit pas voir A
        $resMem = $this->getJson('/api/v1/organisation/statistiques/membres');
        $resMem->assertStatus(200)
            ->assertJsonPath('data.total', 0);

        // Export opérations B ne doit pas contenir les opérations de A
        $resExp = $this->get('/api/v1/organisation/exports/operations');
        $resExp->assertStatus(200);
        $content = $resExp->streamedContent();
        $this->assertStringNotContainsString('OP-SECRET-A', $content);
        $this->assertStringNotContainsString('999999', $content);
    }

    // ─────────────────────────────────────────────────────────────
    // 23. IDOR SUR LES CAMPAGNES
    // ─────────────────────────────────────────────────────────────

    public function test_idor_protection_on_campagne_export(): void
    {
        // Campagne appartenant à OPPE B
        $campagneB = CampagnePelerinage::create([
            'organisation_id' => $this->oppeB->id,
            'code'            => 'CAMP-B-SECRET',
            'nom'             => 'Campagne Secrète B',
            'lieu_depart'     => 'Yamoussoukro',
            'destination'     => 'Rome',
            'date_depart'     => '2026-09-01',
            'date_fin'        => '2026-09-10',
            'capacite'        => 30,
            'statut'          => CampagnePelerinage::STATUT_OUVERTE,
        ]);

        // Utilisateur de OPPE A tente d'exporter la campagne de B
        Sanctum::actingAs($this->respOppeA);

        $res = $this->get("/api/v1/organisation/exports/pelerinages/{$campagneB->id}/participants");
        $res->assertStatus(404);
    }

    // ─────────────────────────────────────────────────────────────
    // 24. PERMISSIONS & REFUS 403 POUR UTILISATEUR RESTREINT
    // ─────────────────────────────────────────────────────────────

    public function test_permission_enforcement_read_only_user_restrictions(): void
    {
        // Animateur OPPE A : profil UTILISATEUR_OPPE sans permission exports.read ni caisse.read
        Sanctum::actingAs($this->userOppeA);

        // Autorisé à voir le dashboard et les statistiques
        $this->getJson('/api/v1/organisation/dashboard')->assertStatus(200);
        $this->getJson('/api/v1/organisation/statistiques/membres')->assertStatus(200);

        // Refusé sur l'état de caisse (403)
        $this->getJson('/api/v1/organisation/caisse')->assertStatus(403);

        // Refusé sur les exports (403)
        $this->get('/api/v1/organisation/exports/membres')->assertStatus(403);
        $this->get('/api/v1/organisation/exports/activites')->assertStatus(403);
        $this->get('/api/v1/organisation/exports/operations')->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────
    // 25. ORGANISATION INACTIVE (SUSPENDUE)
    // ─────────────────────────────────────────────────────────────

    public function test_inactive_organisation_blocked(): void
    {
        Sanctum::actingAs($this->userInactiveOrg);

        $res = $this->getJson('/api/v1/organisation/dashboard');
        $res->assertStatus(403)
            ->assertJsonPath('status', 'error');
    }

    // ─────────────────────────────────────────────────────────────
    // 27. SÉPARATION PAIEMENTS CATHEO / SAAS / PÈLERINAGE
    // ─────────────────────────────────────────────────────────────

    public function test_payment_isolation_between_catheo_saas_and_pelerinage(): void
    {
        Sanctum::actingAs($this->respOppeA);

        // 1. Paiement CATHEO paroissial classique
        $cat = $this->inscrireCatechumene($this->paroisseA, $this->anneeCouranteA, $this->secEnfPri, 'Pay', 'Catheo');
        $insCat = InscriptionAnnuelle::where('catechumene_id', $cat->id)->first();

        $opPaiementCatheo = OperationPaiement::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'annee_catechese_id'        => $this->anneeCouranteA->id,
            'catechumene_id'            => $cat->id,
            'reference'                 => 'OP-CAT-999',
            'libelle'                   => 'Inscription catéchèse',
            'montant'                   => 75000,
            'montant_paye'              => 75000,
            'statut'                    => 'paye',
        ]);

        Paiement::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'annee_catechese_id'        => $this->anneeCouranteA->id,
            'inscription_annuelle_id'   => $insCat->id,
            'catechumene_id'            => $cat->id,
            'numero_recu'               => 'REC-CAT-999',
            'montant_total'             => 75000,
            'mode_paiement'             => 'especes',
            'date_paiement'             => now(),
            'statut'                    => 'valide',
        ]);

        // 2. Paiement Abonnement SaaS Plateforme
        $formule = Formule::create([
            'produit_id'       => $this->produitOppe->id,
            'code'             => 'FORM-TEST',
            'nom'              => 'Formule Test',
            'type_facturation' => 'annuel',
            'prix'             => 120000,
            'statut'           => 'actif',
        ]);

        $abonnement = Abonnement::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'formule_id'                => $formule->id,
            'reference'                 => 'ABO-TEST-001',
            'date_debut'                => '2025-01-01',
            'date_fin'                  => '2025-12-31',
            'montant'                   => 120000.00,
            'devise'                    => 'XOF',
            'statut'                    => 'actif',
        ]);

        $echeance = EcheanceAbonnement::create([
            'abonnement_id' => $abonnement->id,
            'reference'     => 'ECH-TEST-001',
            'periode_debut' => '2025-01-01',
            'periode_fin'   => '2025-12-31',
            'date_echeance' => '2025-01-15',
            'montant'       => 120000,
            'statut'        => 'payee',
        ]);

        $facture = Facture::create([
            'echeance_abonnement_id'    => $echeance->id,
            'reference'                 => 'FAC-TEST-001',
            'date_facture'              => '2025-01-01',
            'date_echeance'             => '2025-01-31',
            'montant_total'             => 120000,
            'statut'                    => 'payee',
        ]);

        PaiementAbonnement::create([
            'echeance_abonnement_id' => $echeance->id,
            'reference'              => 'PAY-SAAS-001',
            'montant'                => 120000,
            'mode_paiement'          => 'virement',
            'date_paiement'          => now(),
            'statut'                 => 'valide',
        ]);

        // 3. Opération réelle de l'Organisation OPPE
        OperationOrganisation::create([
            'organisation_id' => $this->oppeA->id,
            'reference'       => 'OP-REEL-OPPE',
            'type_operation'  => OperationOrganisation::TYPE_ENTREE,
            'libelle'         => 'Vraie recette OPPE',
            'montant'         => 8000,
            'date_operation'  => now(),
            'statut'          => OperationOrganisation::STATUT_VALIDE,
            'created_by'      => $this->respOppeA->id,
        ]);

        // Vérification dans les finances de l'Organisation : UNIQUEMENT 8000 (jamais 75000 ni 120000)
        $resFin = $this->getJson('/api/v1/organisation/statistiques/finances');
        $resFin->assertStatus(200)
            ->assertJsonPath('data.total_entrees', 8000)
            ->assertJsonPath('data.solde', 8000);

        // Vérification dans l'état de caisse de l'Organisation : UNIQUEMENT 8000
        $resCaisse = $this->getJson('/api/v1/organisation/caisse');
        $resCaisse->assertStatus(200)
            ->assertJsonPath('synthese.total_entrees', 8000)
            ->assertJsonPath('synthese.solde_final', 8000)
            ->assertJsonPath('synthese.nombre_operations', 1);
    }
}
