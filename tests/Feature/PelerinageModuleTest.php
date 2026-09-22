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
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PelerinageModuleTest extends TestCase
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
    protected AnneeCatechese $anneePasseeA;

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
            'nom_paroisse'      => 'Paroisse Saint Paul A',
            'code_paroisse'     => 'PAR-SPAUL-A-' . uniqid(),
            'prefixe_matricule' => 'SPA',
            'prefixe_recu'      => 'REC',
            'statut'            => 'actif',
            'ville'             => 'Abidjan',
            'diocese'           => 'Abidjan',
        ]);

        $this->paroisseB = CatecheseConfiguration::create([
            'nom_paroisse'      => 'Paroisse Saint Pierre B',
            'code_paroisse'     => 'PAR-SPIER-B-' . uniqid(),
            'prefixe_matricule' => 'SPB',
            'prefixe_recu'      => 'REC',
            'statut'            => 'actif',
            'ville'             => 'Bouaké',
            'diocese'           => 'Bouaké',
        ]);

        // Années catéchétiques Paroisse A
        $this->anneeCouranteA = AnneeCatechese::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'libelle'                   => 'Année Pastorale 2025-2026',
            'date_debut'                => '2025-09-01',
            'date_fin'                  => '2026-06-30',
            'statut'                    => 'actif',
        ]);

        $this->anneePasseeA = AnneeCatechese::create([
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
            'code'                      => 'OPPE-SPAUL-A',
            'nom'                       => 'OPPE Saint Paul A',
            'statut'                    => 'actif',
        ]);

        $this->oppjA = Organisation::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'produit_id'                => $this->produitOppj->id,
            'type_organisation'         => Organisation::TYPE_OPPJ,
            'code'                      => 'OPPJ-SPAUL-A',
            'nom'                       => 'OPPJ Saint Paul A',
            'statut'                    => 'actif',
        ]);

        $this->oppaA = Organisation::create([
            'paroisse_configuration_id' => $this->paroisseA->id,
            'produit_id'                => $this->produitOppa->id,
            'type_organisation'         => Organisation::TYPE_OPPA,
            'code'                      => 'OPPA-SPAUL-A',
            'nom'                       => 'OPPA Saint Paul A',
            'statut'                    => 'actif',
        ]);

        $this->oppeB = Organisation::create([
            'paroisse_configuration_id' => $this->paroisseB->id,
            'produit_id'                => $this->produitOppe->id,
            'type_organisation'         => Organisation::TYPE_OPPE,
            'code'                      => 'OPPE-SPIER-B',
            'nom'                       => 'OPPE Saint Pierre B',
            'statut'                    => 'actif',
        ]);

        // Profils
        $profilRespOppe = Profil::where('code', 'RESPONSABLE_OPPE')->firstOrFail();
        $profilUserOppe = Profil::where('code', 'UTILISATEUR_OPPE')->firstOrFail();
        $profilRespOppj = Profil::where('code', 'RESPONSABLE_OPPJ')->firstOrFail();
        $profilRespOppa = Profil::where('code', 'RESPONSABLE_OPPA')->firstOrFail();

        // Users
        $this->respOppeA = User::create([
            'name'                      => 'Responsable OPPE A',
            'email'                     => 'resp.oppe.a@example.com',
            'password'                  => Hash::make('Password123!'),
            'profil_id'                 => $profilRespOppe->id,
            'paroisse_configuration_id' => $this->paroisseA->id,
            'organisation_id'           => $this->oppeA->id,
            'statut_compte'             => 'actif',
        ]);

        $this->userOppeA = User::create([
            'name'                      => 'Animateur OPPE A (Lecture seule)',
            'email'                     => 'user.oppe.a@example.com',
            'password'                  => Hash::make('Password123!'),
            'profil_id'                 => $profilUserOppe->id,
            'paroisse_configuration_id' => $this->paroisseA->id,
            'organisation_id'           => $this->oppeA->id,
            'statut_compte'             => 'actif',
        ]);

        $this->respOppjA = User::create([
            'name'                      => 'Responsable OPPJ A',
            'email'                     => 'resp.oppj.a@example.com',
            'password'                  => Hash::make('Password123!'),
            'profil_id'                 => $profilRespOppj->id,
            'paroisse_configuration_id' => $this->paroisseA->id,
            'organisation_id'           => $this->oppjA->id,
            'statut_compte'             => 'actif',
        ]);

        $this->respOppaA = User::create([
            'name'                      => 'Responsable OPPA A',
            'email'                     => 'resp.oppa.a@example.com',
            'password'                  => Hash::make('Password123!'),
            'profil_id'                 => $profilRespOppa->id,
            'paroisse_configuration_id' => $this->paroisseA->id,
            'organisation_id'           => $this->oppaA->id,
            'statut_compte'             => 'actif',
        ]);

        $this->respOppeB = User::create([
            'name'                      => 'Responsable OPPE B',
            'email'                     => 'resp.oppe.b@example.com',
            'password'                  => Hash::make('Password123!'),
            'profil_id'                 => $profilRespOppe->id,
            'paroisse_configuration_id' => $this->paroisseB->id,
            'organisation_id'           => $this->oppeB->id,
            'statut_compte'             => 'actif',
        ]);

        $superAdminProfil = Profil::firstOrCreate(
            ['code' => 'SUPER_ADMIN'],
            ['nom' => 'Super Admin', 'is_system' => true, 'statut' => 'actif', 'permissions' => ['*']]
        );

        $this->superAdmin = User::create([
            'name'                      => 'Platform Admin',
            'email'                     => 'platform@example.com',
            'password'                  => Hash::make('Password123!'),
            'profil_id'                 => $superAdminProfil->id,
            'paroisse_configuration_id' => null,
            'organisation_id'           => null,
            'statut_compte'             => 'actif',
        ]);
    }

    /**
     * Helper pour créer un catéchumène inscrit.
     */
    protected function creerCatechumeneInscrit(CatecheseConfiguration $paroisse, AnneeCatechese $annee, Section $section, string $nom, string $prenoms): Catechumene
    {
        $cat = Catechumene::create([
            'paroisse_configuration_id' => $paroisse->id,
            'matricule'                 => 'CAT-' . uniqid(),
            'nom'                       => $nom,
            'prenoms'                   => $prenoms,
            'sexe'                      => 'M',
            'date_naissance'            => '2014-05-10',
            'telephone'                 => '0700000001',
        ]);

        $niv = Niveau::firstOrCreate([
            'paroisse_configuration_id' => $paroisse->id,
            'section_id'                => $section->id,
            'nom'                       => 'Niveau Test',
        ], ['ordre' => 1]);

        $cls = Classe::firstOrCreate([
            'paroisse_configuration_id' => $paroisse->id,
            'annee_catechese_id'        => $annee->id,
            'niveau_id'                 => $niv->id,
            'nom'                       => 'Classe Test',
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
    // 1. CAMPAGNE : CRÉATION, CODE, CYCLE DE VIE
    // ─────────────────────────────────────────────────────────────

    public function test_campaign_creation_code_generation_and_activity_link(): void
    {
        Sanctum::actingAs($this->respOppeA);

        // Activité de la même organisation
        $activite = Activite::create([
            'organisation_id' => $this->oppeA->id,
            'code'            => 'ACT-TEST-001',
            'titre'           => 'Activité Préparatoire',
            'type_activite'   => 'Pèlerinage',
            'date_debut'      => '2026-11-01 08:00:00',
            'date_fin'        => '2026-11-02 18:00:00',
            'lieu'            => 'Yamoussoukro',
            'statut'          => 'planifiee',
        ]);

        $response = $this->postJson('/api/v1/organisation/pelerinages', [
            'nom'                    => 'Pèlerinage des Enfants 2026',
            'description'            => 'Grand rassemblement diocésain',
            'activite_id'            => $activite->id,
            'lieu_depart'            => 'Abidjan Plateau',
            'destination'            => 'Basilique Notre-Dame de Yamoussoukro',
            'date_depart'            => '2026-11-01',
            'heure_depart'           => '06:30',
            'date_fin'               => '2026-11-02',
            'heure_fin'              => '18:00',
            'date_debut_inscription' => '2026-09-20',
            'date_fin_inscription'   => '2026-10-25',
            'capacite'               => 150,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.nom', 'Pèlerinage des Enfants 2026')
            ->assertJsonPath('data.date_fin', '2026-11-02')
            ->assertJsonPath('data.statut', 'brouillon');

        $code = $response->json('data.code');
        $this->assertStringStartsWith('PEL-' . date('Y') . '-', $code);

        // Vérification de rejet si on tente de lier une activité d'une autre organisation
        $activiteAutre = Activite::create([
            'organisation_id' => $this->oppjA->id,
            'code'            => 'ACT-OPPJ-001',
            'titre'           => 'Activité OPPJ',
            'type_activite'   => 'Jeunesse',
            'date_debut'      => '2026-11-01 08:00:00',
            'date_fin'        => '2026-11-02 18:00:00',
            'lieu'            => 'Abidjan',
            'statut'          => 'planifiee',
        ]);

        $responseFraud = $this->postJson('/api/v1/organisation/pelerinages', [
            'nom'          => 'Campagne Frauduleuse',
            'activite_id'  => $activiteAutre->id,
            'lieu_depart'  => 'Abidjan',
            'destination'  => 'Bassam',
            'date_depart'  => '2026-11-01',
            'date_fin'     => '2026-11-01',
        ]);

        $responseFraud->assertStatus(422);
    }

    public function test_campaign_status_lifecycle_ouvrir_cloturer_annuler(): void
    {
        Sanctum::actingAs($this->respOppeA);

        $campagne = CampagnePelerinage::create([
            'organisation_id' => $this->oppeA->id,
            'code'            => 'PEL-2026-0001',
            'nom'             => 'Pèlerinage Test',
            'lieu_depart'     => 'Abidjan',
            'destination'     => 'Yamoussoukro',
            'date_depart'     => '2026-11-01',
            'date_fin'        => '2026-11-02',
            'statut'          => CampagnePelerinage::STATUT_BROUILLON,
        ]);

        // 1. Ouvrir
        $this->patchJson("/api/v1/organisation/pelerinages/{$campagne->id}/ouvrir")
            ->assertStatus(200)
            ->assertJsonPath('data.statut', 'ouverte');

        // 2. Clôturer
        $this->patchJson("/api/v1/organisation/pelerinages/{$campagne->id}/cloturer")
            ->assertStatus(200)
            ->assertJsonPath('data.statut', 'cloturee');

        // 3. Annuler
        $this->patchJson("/api/v1/organisation/pelerinages/{$campagne->id}/annuler", ['motif' => 'Intempéries'])
            ->assertStatus(200)
            ->assertJsonPath('data.statut', 'annulee');
    }

    // ─────────────────────────────────────────────────────────────
    // 2. TARIFS : CONFIGURATION MULTIPLE SANS AGE_MIN/MAX
    // ─────────────────────────────────────────────────────────────

    public function test_tariffs_multiple_creation_and_listing(): void
    {
        Sanctum::actingAs($this->respOppeA);

        $campagne = CampagnePelerinage::create([
            'organisation_id' => $this->oppeA->id,
            'code'            => 'PEL-2026-0002',
            'nom'             => 'Pèlerinage Tarifs',
            'lieu_depart'     => 'Abidjan',
            'destination'     => 'Yamoussoukro',
            'date_depart'     => '2026-11-01',
            'date_fin'        => '2026-11-02',
            'statut'          => 'ouverte',
        ]);

        // Tarif 1: Standard
        $this->postJson("/api/v1/organisation/pelerinages/{$campagne->id}/tarifs", [
            'libelle' => 'Tarif Enfant',
            'montant' => 15000,
        ])->assertStatus(201)->assertJsonPath('data.montant', 15000);

        // Tarif 2: Accompagnateur
        $this->postJson("/api/v1/organisation/pelerinages/{$campagne->id}/tarifs", [
            'libelle' => 'Tarif Accompagnateur',
            'montant' => 25000,
        ])->assertStatus(201)->assertJsonPath('data.montant', 25000);

        // Liste
        $this->getJson("/api/v1/organisation/pelerinages/{$campagne->id}/tarifs")
            ->assertStatus(200)
            ->assertJsonCount(2, 'data');
    }

    // ─────────────────────────────────────────────────────────────
    // 3. INSCRIPTION EXTERNE & RESPECT DE LA CAPACITÉ
    // ─────────────────────────────────────────────────────────────

    public function test_external_registration_and_strict_capacity_enforcement(): void
    {
        Sanctum::actingAs($this->respOppeA);

        $campagne = CampagnePelerinage::create([
            'organisation_id' => $this->oppeA->id,
            'code'            => 'PEL-2026-0003',
            'nom'             => 'Campagne Capacité Limitée',
            'lieu_depart'     => 'Abidjan',
            'destination'     => 'Grand-Bassam',
            'date_depart'     => '2026-10-10',
            'date_fin'        => '2026-10-10',
            'capacite'        => 2, // Capacité fixée à 2 participants
            'statut'          => 'ouverte',
        ]);

        $tarif = TarifPelerinage::create([
            'campagne_pelerinage_id' => $campagne->id,
            'code'                   => 'TAR-STD',
            'libelle'                => 'Tarif Normal',
            'montant'                => 10000,
            'statut'                 => 'actif',
        ]);

        // Inscription 1: Externe
        $res1 = $this->postJson("/api/v1/organisation/pelerinages/{$campagne->id}/inscriptions", [
            'tarif_pelerinage_id' => $tarif->id,
            'type_participant'    => 'EXTERNE',
            'nom'                 => 'DUPONT',
            'prenoms'             => 'Claire',
            'sexe'                => 'F',
            'telephone'           => '0701020304',
        ]);
        $res1->assertStatus(201);
        $ins1Id = $res1->json('data.id');

        // Inscription 2: Externe
        $this->postJson("/api/v1/organisation/pelerinages/{$campagne->id}/inscriptions", [
            'tarif_pelerinage_id' => $tarif->id,
            'type_participant'    => 'EXTERNE',
            'nom'                 => 'KONE',
            'prenoms'             => 'Ibrahim',
            'sexe'                => 'M',
            'telephone'           => '0501020304',
        ])->assertStatus(201);

        // Inscription 3: Doit être REFUSÉE car capacité de 2 atteinte
        $this->postJson("/api/v1/organisation/pelerinages/{$campagne->id}/inscriptions", [
            'tarif_pelerinage_id' => $tarif->id,
            'type_participant'    => 'EXTERNE',
            'nom'                 => 'YAO',
            'prenoms'             => 'Serge',
        ])->assertStatus(422)
          ->assertJsonFragment(['status' => 'error']);

        // Annulation de l'inscription 1 -> libère la place
        $this->patchJson("/api/v1/organisation/pelerinages/{$campagne->id}/inscriptions/{$ins1Id}/annuler")
            ->assertStatus(200);

        // Maintenant, la nouvelle inscription DOIT passer
        $this->postJson("/api/v1/organisation/pelerinages/{$campagne->id}/inscriptions", [
            'tarif_pelerinage_id' => $tarif->id,
            'type_participant'    => 'EXTERNE',
            'nom'                 => 'YAO',
            'prenoms'             => 'Serge',
            'sexe'                => 'M',
        ])->assertStatus(201);
    }

    // ─────────────────────────────────────────────────────────────
    // 4. PASSERELLE CATHEO : FILTRAGE SECTIONS CANONIQUES & ANNEE ACTIVE
    // ─────────────────────────────────────────────────────────────

    public function test_catheo_population_gateway_and_strict_section_filtering(): void
    {
        // Création de 4 catéchumènes sur l'année courante : 1 Primaire, 1 Collège, 1 Jeune, 1 Adulte
        $catPri = $this->creerCatechumeneInscrit($this->paroisseA, $this->anneeCouranteA, $this->secEnfPri, 'KOFFI', 'Primaire');
        $catCol = $this->creerCatechumeneInscrit($this->paroisseA, $this->anneeCouranteA, $this->secEnfCol, 'YAO', 'College');
        $catJeu = $this->creerCatechumeneInscrit($this->paroisseA, $this->anneeCouranteA, $this->secJeunes, 'BAH', 'Jeune');
        $catAdu = $this->creerCatechumeneInscrit($this->paroisseA, $this->anneeCouranteA, $this->secAdultes, 'AMON', 'Adulte');

        // Création d'un catéchumène sur l'année PASSÉE (archive) -> Ne doit jamais être repris
        $catPasse = $this->creerCatechumeneInscrit($this->paroisseA, $this->anneePasseeA, $this->secEnfPri, 'ANCIEN', 'Archive');

        // A. Test OPPE : doit générer uniquement PRI + COL (2 catéchumènes)
        Sanctum::actingAs($this->respOppeA);

        $campagneOppe = CampagnePelerinage::create([
            'organisation_id' => $this->oppeA->id,
            'code'            => 'PEL-2026-OPPE',
            'nom'             => 'Pèlerinage OPPE',
            'lieu_depart'     => 'Abidjan',
            'destination'     => 'Yamoussoukro',
            'date_depart'     => '2026-11-01',
            'date_fin'        => '2026-11-02',
            'statut'          => 'ouverte',
        ]);

        $tarifOppe = TarifPelerinage::create([
            'campagne_pelerinage_id' => $campagneOppe->id,
            'code'                   => 'TAR-OPPE',
            'libelle'                => 'Tarif Enfant',
            'montant'                => 12000,
            'statut'                 => 'actif',
        ]);

        $resOppe = $this->postJson("/api/v1/organisation/pelerinages/{$campagneOppe->id}/generer-inscriptions-catheo", [
            'tarif_pelerinage_id' => $tarifOppe->id,
        ]);

        $resOppe->assertStatus(201)
            ->assertJsonPath('data.nombre_cree', 2)
            ->assertJsonPath('data.nombre_trouve', 2);

        // Vérification des catéchumènes inscrits dans OPPE
        $inscritsOppe = InscriptionPelerinage::where('campagne_pelerinage_id', $campagneOppe->id)->pluck('catechumene_id')->all();
        $this->assertContains($catPri->id, $inscritsOppe);
        $this->assertContains($catCol->id, $inscritsOppe);
        $this->assertNotContains($catJeu->id, $inscritsOppe);
        $this->assertNotContains($catAdu->id, $inscritsOppe);
        $this->assertNotContains($catPasse->id, $inscritsOppe);

        // Idempotence : relancer la génération ne doit créer aucun doublon
        $this->postJson("/api/v1/organisation/pelerinages/{$campagneOppe->id}/generer-inscriptions-catheo")
            ->assertStatus(201)
            ->assertJsonPath('data.nombre_cree', 0)
            ->assertJsonPath('data.nombre_deja_existant', 2);

        // B. Test OPPJ : doit générer uniquement JEUNES (1 catéchumène)
        Sanctum::actingAs($this->respOppjA);

        $campagneOppj = CampagnePelerinage::create([
            'organisation_id' => $this->oppjA->id,
            'code'            => 'PEL-2026-OPPJ',
            'nom'             => 'Pèlerinage OPPJ',
            'lieu_depart'     => 'Abidjan',
            'destination'     => 'Yamoussoukro',
            'date_depart'     => '2026-11-01',
            'date_fin'        => '2026-11-02',
            'statut'          => 'ouverte',
        ]);

        $tarifOppj = TarifPelerinage::create([
            'campagne_pelerinage_id' => $campagneOppj->id,
            'code'                   => 'TAR-OPPJ',
            'libelle'                => 'Tarif Jeune',
            'montant'                => 15000,
            'statut'                 => 'actif',
        ]);

        $resOppj = $this->postJson("/api/v1/organisation/pelerinages/{$campagneOppj->id}/generer-inscriptions-catheo");
        $resOppj->assertStatus(201)
            ->assertJsonPath('data.nombre_cree', 1);

        $inscritsOppj = InscriptionPelerinage::where('campagne_pelerinage_id', $campagneOppj->id)->pluck('catechumene_id')->all();
        $this->assertContains($catJeu->id, $inscritsOppj);
        $this->assertNotContains($catPri->id, $inscritsOppj);
        $this->assertNotContains($catAdu->id, $inscritsOppj);

        // C. Test OPPA : doit générer uniquement ADULTES (1 catéchumène)
        Sanctum::actingAs($this->respOppaA);

        $campagneOppa = CampagnePelerinage::create([
            'organisation_id' => $this->oppaA->id,
            'code'            => 'PEL-2026-OPPA',
            'nom'             => 'Pèlerinage OPPA',
            'lieu_depart'     => 'Abidjan',
            'destination'     => 'Yamoussoukro',
            'date_depart'     => '2026-11-01',
            'date_fin'        => '2026-11-02',
            'statut'          => 'ouverte',
        ]);

        TarifPelerinage::create([
            'campagne_pelerinage_id' => $campagneOppa->id,
            'code'                   => 'TAR-OPPA',
            'libelle'                => 'Tarif Adulte',
            'montant'                => 20000,
            'statut'                 => 'actif',
        ]);

        $this->postJson("/api/v1/organisation/pelerinages/{$campagneOppa->id}/generer-inscriptions-catheo")
            ->assertStatus(201)
            ->assertJsonPath('data.nombre_cree', 1);

        $inscritsOppa = InscriptionPelerinage::where('campagne_pelerinage_id', $campagneOppa->id)->pluck('catechumene_id')->all();
        $this->assertContains($catAdu->id, $inscritsOppa);
        $this->assertNotContains($catPri->id, $inscritsOppa);
        $this->assertNotContains($catJeu->id, $inscritsOppa);
    }

    // ─────────────────────────────────────────────────────────────
    // 5. PAIEMENTS PARTIELS, STATUTS ET ÉCRITURES FINANCIÈRES
    // ─────────────────────────────────────────────────────────────

    public function test_partial_payments_calculation_status_transitions_and_operations(): void
    {
        Sanctum::actingAs($this->respOppeA);

        $campagne = CampagnePelerinage::create([
            'organisation_id' => $this->oppeA->id,
            'code'            => 'PEL-2026-PAI',
            'nom'             => 'Pèlerinage Paiements',
            'lieu_depart'     => 'Abidjan',
            'destination'     => 'Yamoussoukro',
            'date_depart'     => '2026-11-01',
            'date_fin'        => '2026-11-02',
            'statut'          => 'ouverte',
        ]);

        $tarif = TarifPelerinage::create([
            'campagne_pelerinage_id' => $campagne->id,
            'code'                   => 'TAR-STD',
            'libelle'                => 'Tarif Standard',
            'montant'                => 20000,
            'statut'                 => 'actif',
        ]);

        // Inscription initiale : 20 000 F, reste_a_payer = 20 000 F, statut = en_attente
        $inscription = InscriptionPelerinage::create([
            'campagne_pelerinage_id' => $campagne->id,
            'tarif_pelerinage_id'    => $tarif->id,
            'type_participant'       => 'EXTERNE',
            'reference'              => 'INS-TEST-001',
            'nom'                    => 'KOUASSI',
            'prenoms'                => 'Elysée',
            'montant'                => 20000,
            'montant_paye'           => 0,
            'reste_a_payer'          => 20000,
            'statut_inscription'     => 'en_attente',
        ]);

        // 1. Premier paiement partiel de 8 000 F
        $res1 = $this->postJson("/api/v1/organisation/pelerinages/{$campagne->id}/inscriptions/{$inscription->id}/paiements", [
            'montant'       => 8000,
            'mode_paiement' => 'wave',
        ]);

        $res1->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('inscription.montant_paye', 8000)
            ->assertJsonPath('inscription.reste_a_payer', 12000)
            ->assertJsonPath('inscription.statut_inscription', 'partiellement_payee');

        $paiement1Id = $res1->json('data.id');

        // Vérification de l'écriture financière d'entrée dans operation_organisations
        $operation = OperationOrganisation::where('paiement_pelerinage_id', $paiement1Id)->first();
        $this->assertNotNull($operation);
        $this->assertEquals(8000, $operation->montant);
        $this->assertEquals('entree', $operation->type_operation);
        $this->assertEquals($this->oppeA->id, $operation->organisation_id);

        // 2. Tentative de surpaiement (> reste_a_payer 12 000 F) -> Doit échouer avec 422
        $this->postJson("/api/v1/organisation/pelerinages/{$campagne->id}/inscriptions/{$inscription->id}/paiements", [
            'montant'       => 15000,
            'mode_paiement' => 'especes',
        ])->assertStatus(422);

        // 3. Deuxième paiement soldant le reste (12 000 F) -> Doit basculer à payee
        $res2 = $this->postJson("/api/v1/organisation/pelerinages/{$campagne->id}/inscriptions/{$inscription->id}/paiements", [
            'montant'       => 12000,
            'mode_paiement' => 'orange_money',
        ]);

        $res2->assertStatus(201)
            ->assertJsonPath('inscription.montant_paye', 20000)
            ->assertJsonPath('inscription.reste_a_payer', 0)
            ->assertJsonPath('inscription.statut_inscription', 'payee');

        // 4. Annulation du premier paiement -> Recalcul immédiat
        $this->postJson("/api/v1/organisation/pelerinages/{$campagne->id}/paiements/{$paiement1Id}/annuler", [
            'motif' => 'Erreur de saisie caissier',
        ])->assertStatus(200)
          ->assertJsonPath('inscription.montant_paye', 12000)
          ->assertJsonPath('inscription.reste_a_payer', 8000)
          ->assertJsonPath('inscription.statut_inscription', 'partiellement_payee');

        // L'opération financière associée doit être marquée annulée
        $this->assertEquals('annule', $operation->fresh()->statut);
    }

    // ─────────────────────────────────────────────────────────────
    // 6. CLÔTURE DE CAMPAGNE ET ANNULATION DES IMPAYÉS
    // ─────────────────────────────────────────────────────────────

    public function test_campaign_closure_cancels_unpaid_inscriptions_while_preserving_paid(): void
    {
        Sanctum::actingAs($this->respOppeA);

        $campagne = CampagnePelerinage::create([
            'organisation_id' => $this->oppeA->id,
            'code'            => 'PEL-2026-CLOTURE',
            'nom'             => 'Pèlerinage Clôture Test',
            'lieu_depart'     => 'Abidjan',
            'destination'     => 'Yamoussoukro',
            'date_depart'     => '2026-11-01',
            'date_fin'        => '2026-11-02',
            'statut'          => 'ouverte',
        ]);

        $tarif = TarifPelerinage::create([
            'campagne_pelerinage_id' => $campagne->id,
            'code'                   => 'TAR-STD',
            'libelle'                => 'Tarif Normal',
            'montant'                => 10000,
            'statut'                 => 'actif',
        ]);

        // Participant 1: A payé
        $insPayee = InscriptionPelerinage::create([
            'campagne_pelerinage_id' => $campagne->id,
            'tarif_pelerinage_id'    => $tarif->id,
            'type_participant'       => 'EXTERNE',
            'reference'              => 'INS-PAYEE-001',
            'nom'                    => 'DIALLO',
            'prenoms'                => 'Alpha',
            'montant'                => 10000,
            'montant_paye'           => 10000,
            'reste_a_payer'          => 0,
            'statut_inscription'     => 'payee',
        ]);

        // Participant 2: N'a rien payé (en_attente)
        $insImpayee = InscriptionPelerinage::create([
            'campagne_pelerinage_id' => $campagne->id,
            'tarif_pelerinage_id'    => $tarif->id,
            'type_participant'       => 'CATECHUMENE',
            'reference'              => 'INS-IMPAYEE-002',
            'nom'                    => 'CISSE',
            'prenoms'                => 'Moussa',
            'montant'                => 10000,
            'montant_paye'           => 0,
            'reste_a_payer'          => 10000,
            'statut_inscription'     => 'en_attente',
        ]);

        // Clôture de la campagne
        $res = $this->patchJson("/api/v1/organisation/pelerinages/{$campagne->id}/cloturer");

        $res->assertStatus(200)
            ->assertJsonPath('data.statut', 'cloturee')
            ->assertJsonPath('meta.inscriptions_annulees', 1);

        // L'inscription payée reste intacte
        $this->assertEquals('payee', $insPayee->fresh()->statut_inscription);

        // L'inscription impayée est passée à annulée (et non supprimée physiquement !)
        $this->assertEquals('annulee', $insImpayee->fresh()->statut_inscription);
        $this->assertNull($insImpayee->fresh()->deleted_at);
    }

    // ─────────────────────────────────────────────────────────────
    // 7. SUIVI DES PARTICIPATIONS ET POINTAGE
    // ─────────────────────────────────────────────────────────────

    public function test_participation_tracking_and_batch_update(): void
    {
        Sanctum::actingAs($this->respOppeA);

        $campagne = CampagnePelerinage::create([
            'organisation_id' => $this->oppeA->id,
            'code'            => 'PEL-2026-PART',
            'nom'             => 'Pèlerinage Pointage',
            'lieu_depart'     => 'Abidjan',
            'destination'     => 'Yamoussoukro',
            'date_depart'     => '2026-11-01',
            'date_fin'        => '2026-11-02',
            'statut'          => 'ouverte',
        ]);

        $tarif = TarifPelerinage::create([
            'campagne_pelerinage_id' => $campagne->id,
            'code'                   => 'TAR-STD',
            'libelle'                => 'Tarif Normal',
            'montant'                => 10000,
        ]);

        $ins1 = InscriptionPelerinage::create([
            'campagne_pelerinage_id' => $campagne->id,
            'tarif_pelerinage_id'    => $tarif->id,
            'type_participant'       => 'EXTERNE',
            'reference'              => 'INS-PART-001',
            'nom'                    => 'BAMBA',
            'prenoms'                => 'Fatou',
            'montant'                => 10000,
            'statut_participation'  => 'prevue',
        ]);

        $ins2 = InscriptionPelerinage::create([
            'campagne_pelerinage_id' => $campagne->id,
            'tarif_pelerinage_id'    => $tarif->id,
            'type_participant'       => 'EXTERNE',
            'reference'              => 'INS-PART-002',
            'nom'                    => 'TOURE',
            'prenoms'                => 'Karim',
            'montant'                => 10000,
            'statut_participation'  => 'prevue',
        ]);

        // Pointage unitaire
        $this->patchJson("/api/v1/organisation/pelerinages/{$campagne->id}/inscriptions/{$ins1->id}/participation", [
            'statut_participation' => 'presente',
            'badge_imprime'        => true,
            'kit_remis'            => true,
        ])->assertStatus(200)
          ->assertJsonPath('data.statut_participation', 'presente')
          ->assertJsonPath('data.kit_remis', true);

        $this->assertNotNull($ins1->fresh()->date_remise_kit);

        // Pointage en masse (batch)
        $this->postJson("/api/v1/organisation/pelerinages/{$campagne->id}/participation/batch", [
            'inscriptions' => [
                ['id' => $ins2->id, 'statut_participation' => 'absente', 'kit_remis' => false],
            ],
        ])->assertStatus(200)
          ->assertJsonPath('data.total_mis_a_jour', 1);

        $this->assertEquals('absente', $ins2->fresh()->statut_participation);
    }

    // ─────────────────────────────────────────────────────────────
    // 8. ISOLATION MULTI-TENANT & IDOR
    // ─────────────────────────────────────────────────────────────

    public function test_strict_multi_tenant_isolation_and_idor_prevention(): void
    {
        // Campagne créée par Paroisse A / OPPE A
        $campagneA = CampagnePelerinage::create([
            'organisation_id' => $this->oppeA->id,
            'code'            => 'PEL-PAR-A',
            'nom'             => 'Pèlerinage A',
            'lieu_depart'     => 'Abidjan',
            'destination'     => 'Yamoussoukro',
            'date_depart'     => '2026-11-01',
            'date_fin'        => '2026-11-02',
            'statut'          => 'ouverte',
        ]);

        $tarifA = TarifPelerinage::create([
            'campagne_pelerinage_id' => $campagneA->id,
            'code'                   => 'TAR-A',
            'libelle'                => 'Tarif A',
            'montant'                => 10000,
        ]);

        // Tentative d'accès par l'utilisateur d'une AUTRE paroisse (Paroisse B / OPPE B)
        Sanctum::actingAs($this->respOppeB);

        // Tentative de consultation
        $this->getJson("/api/v1/organisation/pelerinages/{$campagneA->id}")
            ->assertStatus(404);

        // Tentative de création de tarif sur la campagne A
        $this->postJson("/api/v1/organisation/pelerinages/{$campagneA->id}/tarifs", [
            'libelle' => 'Tarif Frauduleux',
            'montant' => 5000,
        ])->assertStatus(404);

        // Tentative de clôture
        $this->patchJson("/api/v1/organisation/pelerinages/{$campagneA->id}/cloturer")
            ->assertStatus(404);

        // Tentative d'accès par une autre organisation de la MÊME paroisse (OPPJ A)
        Sanctum::actingAs($this->respOppjA);

        $this->getJson("/api/v1/organisation/pelerinages/{$campagneA->id}")
            ->assertStatus(404);
    }

    // ─────────────────────────────────────────────────────────────
    // 9. PERMISSIONS & UTILISATEUR LECTURE SEULE
    // ─────────────────────────────────────────────────────────────

    public function test_read_only_user_cannot_modify_or_record_payments(): void
    {
        $campagne = CampagnePelerinage::create([
            'organisation_id' => $this->oppeA->id,
            'code'            => 'PEL-RO-TEST',
            'nom'             => 'Campagne Lecture Seule',
            'lieu_depart'     => 'Abidjan',
            'destination'     => 'Yamoussoukro',
            'date_depart'     => '2026-11-01',
            'date_fin'        => '2026-11-02',
            'statut'          => 'ouverte',
        ]);

        $tarif = TarifPelerinage::create([
            'campagne_pelerinage_id' => $campagne->id,
            'code'                   => 'TAR-RO',
            'libelle'                => 'Tarif Standard',
            'montant'                => 10000,
        ]);

        $ins = InscriptionPelerinage::create([
            'campagne_pelerinage_id' => $campagne->id,
            'tarif_pelerinage_id'    => $tarif->id,
            'type_participant'       => 'EXTERNE',
            'reference'              => 'INS-RO-001',
            'nom'                    => 'TEST',
            'prenoms'                => 'User',
            'montant'                => 10000,
            'reste_a_payer'          => 10000,
            'statut_inscription'     => 'en_attente',
        ]);

        // Connexion en tant qu'animateur / utilisateur avec permission de lecture seule
        Sanctum::actingAs($this->userOppeA);

        // Lecture autorisée
        $this->getJson("/api/v1/organisation/pelerinages/{$campagne->id}")
            ->assertStatus(200);

        // Création de campagne interdite (403)
        $this->postJson('/api/v1/organisation/pelerinages', [
            'nom'          => 'Nouvelle Campagne',
            'lieu_depart'  => 'Abidjan',
            'destination'  => 'Yamoussoukro',
            'date_depart'  => '2026-11-01',
            'date_fin'     => '2026-11-02',
        ])->assertStatus(403);

        // Enregistrement de paiement interdit (403)
        $this->postJson("/api/v1/organisation/pelerinages/{$campagne->id}/inscriptions/{$ins->id}/paiements", [
            'montant'       => 5000,
            'mode_paiement' => 'especes',
        ])->assertStatus(403);

        // Pointage de participation interdit (403)
        $this->patchJson("/api/v1/organisation/pelerinages/{$campagne->id}/inscriptions/{$ins->id}/participation", [
            'statut_participation' => 'presente',
        ])->assertStatus(403);
    }

    // ─────────────────────────────────────────────────────────────
    // 10. ORGANISATION OU PAROISSE INACTIVE
    // ─────────────────────────────────────────────────────────────

    public function test_inactive_organisation_access_is_forbidden(): void
    {
        $this->oppeA->update(['statut' => 'inactif']);

        Sanctum::actingAs($this->respOppeA);

        $this->getJson('/api/v1/organisation/pelerinages')
            ->assertStatus(403)
            ->assertJsonPath('status', 'error');
    }

    // ─────────────────────────────────────────────────────────────
    // 11. GESTION DU CHAMP TAILLE (CRUD, NORMALISATION & VALIDATION)
    // ─────────────────────────────────────────────────────────────

    public function test_taille_crud_and_validation(): void
    {
        Sanctum::actingAs($this->respOppeA);

        $campagne = CampagnePelerinage::create([
            'organisation_id' => $this->oppeA->id,
            'code'            => 'PEL-2026-TAILLE',
            'nom'             => 'Pèlerinage Tailles',
            'lieu_depart'     => 'Abidjan',
            'destination'     => 'Yamoussoukro',
            'date_depart'     => '2026-11-01',
            'date_fin'        => '2026-11-02',
            'statut'          => 'ouverte',
        ]);

        $tarif = TarifPelerinage::create([
            'campagne_pelerinage_id' => $campagne->id,
            'code'                   => 'TAR-TAILLE',
            'libelle'                => 'Tarif Normal',
            'montant'                => 15000,
            'statut'                 => 'actif',
        ]);

        // 1. Création avec taille valide en majuscule ('XL')
        $res1 = $this->postJson("/api/v1/organisation/pelerinages/{$campagne->id}/inscriptions", [
            'tarif_pelerinage_id' => $tarif->id,
            'type_participant'    => 'EXTERNE',
            'nom'                 => 'KOUASSI',
            'prenoms'             => 'Jean',
            'sexe'                => 'M',
            'taille'              => 'XL',
            'telephone'           => '0102030405',
        ]);
        $res1->assertStatus(201)
            ->assertJsonPath('data.taille', 'XL');
        $insId = $res1->json('data.id');

        // 2. Création avec taille en minuscule ('m' -> normalisé en 'M')
        $res2 = $this->postJson("/api/v1/organisation/pelerinages/{$campagne->id}/inscriptions", [
            'tarif_pelerinage_id' => $tarif->id,
            'type_participant'    => 'EXTERNE',
            'nom'                 => 'AMANI',
            'prenoms'             => 'Sara',
            'sexe'                => 'F',
            'taille'              => 'm',
            'telephone'           => '0708091011',
        ]);
        $res2->assertStatus(201)
            ->assertJsonPath('data.taille', 'M');

        // 3. Mise à jour de la taille ('XL' -> 'XXL')
        $resUpdate = $this->putJson("/api/v1/organisation/pelerinages/{$campagne->id}/inscriptions/{$insId}", [
            'taille' => 'XXL',
        ]);
        $resUpdate->assertStatus(200)
            ->assertJsonPath('data.taille', 'XXL');

        // 4. Rejet d'une taille invalide (ex: 'OVERSIZE') -> 422
        $resInvalid = $this->postJson("/api/v1/organisation/pelerinages/{$campagne->id}/inscriptions", [
            'tarif_pelerinage_id' => $tarif->id,
            'type_participant'    => 'EXTERNE',
            'nom'                 => 'TEST',
            'prenoms'             => 'Invalide',
            'taille'              => 'OVERSIZE',
            'telephone'           => '0909090909',
        ]);
        $resInvalid->assertStatus(422)
            ->assertJsonValidationErrors(['taille']);

        // 5. Filtrage des inscriptions par taille
        $resFilter = $this->getJson("/api/v1/organisation/pelerinages/{$campagne->id}/inscriptions?taille=XXL");
        $resFilter->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.taille', 'XXL');
    }
}
