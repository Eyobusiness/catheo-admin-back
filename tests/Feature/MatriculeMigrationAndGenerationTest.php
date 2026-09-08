<?php

namespace Tests\Feature;

use App\Console\Commands\MigrateMatriculesCommand;
use App\Models\AnneeCatechese;
use App\Models\CatecheseConfiguration;
use App\Models\Catechumene;
use App\Models\Classe;
use App\Models\InscriptionAnnuelle;
use App\Models\Niveau;
use App\Models\Section;
use App\Models\User;
use App\Services\MatriculeGeneratorService;
use Database\Seeders\CleanTestDatabaseSeeder;
use Database\Seeders\MenuSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MatriculeMigrationAndGenerationTest extends TestCase
{
    use RefreshDatabase;

    protected CatecheseConfiguration $paroisseSM;
    protected CatecheseConfiguration $paroisseCIM;
    protected Section $secJeune;
    protected Section $secAdulte;
    protected Section $secEnfant;
    protected AnneeCatechese $annee;
    protected MatriculeGeneratorService $service;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MenuSeeder::class);
        $this->seed(CleanTestDatabaseSeeder::class);

        $this->service = app(MatriculeGeneratorService::class);

        // Paroisse Sainte Monique (Préfixe SM)
        $this->paroisseSM = CatecheseConfiguration::where('code_paroisse', 'SM-01')->firstOrFail();

        // Deuxième Paroisse (Préfixe CIM)
        $this->paroisseCIM = CatecheseConfiguration::create([
            'uuid'              => (string) Str::uuid(),
            'nom_paroisse'      => 'Cœur Immaculé de Marie',
            'code_paroisse'     => 'CIM-01',
            'prefixe_matricule' => 'CIM',
            'statut'            => 'actif',
        ]);

        // Année pastorale 2026-2027
        $this->annee = AnneeCatechese::create([
            'uuid'                      => (string) Str::uuid(),
            'paroisse_configuration_id' => $this->paroisseSM->id,
            'libelle'                   => '2026-2027',
            'date_debut'                => '2026-08-31',
            'date_fin'                  => '2027-06-30',
            'statut'                    => 'active',
        ]);

        // Sections
        $this->secJeune = Section::create([
            'uuid'                      => (string) Str::uuid(),
            'paroisse_configuration_id' => $this->paroisseSM->id,
            'nom'                       => 'Jeunes',
            'code'                      => 'SEC-JEUNE',
            'statut'                    => 'actif',
        ]);

        $this->secAdulte = Section::create([
            'uuid'                      => (string) Str::uuid(),
            'paroisse_configuration_id' => $this->paroisseSM->id,
            'nom'                       => 'Adultes',
            'code'                      => 'SEC-ADULTE',
            'statut'                    => 'actif',
        ]);

        $this->secEnfant = Section::create([
            'uuid'                      => (string) Str::uuid(),
            'paroisse_configuration_id' => $this->paroisseSM->id,
            'nom'                       => 'Enfant Primaire',
            'code'                      => 'SEC-ENFANT',
            'statut'                    => 'actif',
        ]);

        $admin = User::where('email', 'admin@sainte-monique.ci')->firstOrFail();
        $this->token = $admin->createToken('test-token')->plainTextToken;
    }

    /**
     * Test 1 — Jeune : Section SEC-JEUNE -> XX26-JXXXX
     */
    public function test_01_section_jeune_genere_code_J(): void
    {
        $matricule = $this->service->generate($this->paroisseSM->id, $this->secJeune, 2026);

        $this->assertStringStartsWith('SM26-J', $matricule);
        $this->assertMatchesRegularExpression('/^SM26-J[A-Z0-9]{4}$/', $matricule);
    }

    /**
     * Test 2 — Adulte : Section SEC-ADULTE -> XX26-AXXXX
     */
    public function test_02_section_adulte_genere_code_A(): void
    {
        $matricule = $this->service->generate($this->paroisseSM->id, $this->secAdulte, 2026);

        $this->assertStringStartsWith('SM26-A', $matricule);
        $this->assertMatchesRegularExpression('/^SM26-A[A-Z0-9]{4}$/', $matricule);
    }

    /**
     * Test 3 — Enfant / autre section : Section SEC-ENFANT -> XX26-EXXXX
     */
    public function test_03_section_enfant_ou_autre_genere_code_E(): void
    {
        $matricule = $this->service->generate($this->paroisseSM->id, $this->secEnfant, 2026);

        $this->assertStringStartsWith('SM26-E', $matricule);
        $this->assertMatchesRegularExpression('/^SM26-E[A-Z0-9]{4}$/', $matricule);
    }

    /**
     * Test 4 — Format : Vérifier le respect strict de la regex
     */
    public function test_04_format_matricule_conforme_regex(): void
    {
        $matricules = [
            $this->service->generate($this->paroisseSM->id, $this->secJeune, 2026),
            $this->service->generate($this->paroisseSM->id, $this->secAdulte, 2026),
            $this->service->generate($this->paroisseSM->id, $this->secEnfant, 2026),
            $this->service->generate($this->paroisseCIM->id, 'SEC-JEUNE', 2027),
        ];

        foreach ($matricules as $m) {
            $this->assertMatchesRegularExpression(MigrateMatriculesCommand::NEW_FORMAT_REGEX, $m);
        }
    }

    /**
     * Test 5 — Exactement 4 caractères alphanumériques après le code section
     */
    public function test_05_exactement_quatre_caracteres_apres_section(): void
    {
        $matricule = $this->service->generate($this->paroisseSM->id, $this->secJeune, 2026);
        $parts = explode('-', $matricule);

        $this->assertCount(2, $parts);
        // Partie droite ex: J8HDX
        $rightPart = $parts[1];
        $this->assertEquals(5, strlen($rightPart)); // 1 lettre section + 4 caractères aléatoires
        $codeAleatoire = substr($rightPart, 1);
        $this->assertEquals(4, strlen($codeAleatoire));
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}$/', $codeAleatoire);
    }

    /**
     * Test 6 — Unicité : Générer plusieurs matricules et vérifier qu'il n'existe aucun doublon
     */
    public function test_06_unicite_matricules_generes_sans_doublon(): void
    {
        $generated = [];
        for ($i = 0; $i < 50; $i++) {
            $mat = $this->service->generate($this->paroisseSM->id, $this->secJeune, 2026);
            $this->assertNotContains($mat, $generated, "Le matricule {$mat} a été généré en double.");
            $generated[] = $mat;

            // Enregistrement en base pour tester l'anti-collision dans la table
            Catechumene::create([
                'uuid'                      => (string) Str::uuid(),
                'paroisse_configuration_id' => $this->paroisseSM->id,
                'matricule'                 => $mat,
                'nom'                       => 'TEST',
                'prenoms'                   => 'Unique ' . $i,
                'sexe'                      => 'M',
                'statut'                    => 'actif',
            ]);
        }

        $this->assertCount(50, array_unique($generated));
    }

    /**
     * Test 7 — Multi-paroisse : Vérifier que chaque paroisse utilise son propre préfixe
     */
    public function test_07_multi_paroisse_utilise_propre_prefixe(): void
    {
        $matSM = $this->service->generate($this->paroisseSM->id, $this->secJeune, 2026);
        $matCIM = $this->service->generate($this->paroisseCIM->id, $this->secJeune, 2026);

        $this->assertStringStartsWith('SM', $matSM);
        $this->assertStringStartsWith('CIM', $matCIM);
        $this->assertStringStartsNotWith('SM', $matCIM);
    }

    /**
     * Test 8 — Migration : Les anciennes données deviennent conformes au nouveau format
     */
    public function test_08_migration_commande_met_a_jour_anciens_matricules(): void
    {
        $catOld = Catechumene::create([
            'uuid'                      => (string) Str::uuid(),
            'paroisse_configuration_id' => $this->paroisseSM->id,
            'matricule'                 => 'SM26-0609131806L',
            'nom'                       => 'ANCIEN',
            'prenoms'                   => 'Format Test',
            'sexe'                      => 'M',
            'statut'                    => 'actif',
        ]);

        $niveau = Niveau::create([
            'uuid'                      => (string) Str::uuid(),
            'paroisse_configuration_id' => $this->paroisseSM->id,
            'section_id'                => $this->secJeune->id,
            'nom'                       => 'Niveau Jeunes 1',
            'statut'                    => 'actif',
        ]);

        InscriptionAnnuelle::create([
            'uuid'                      => (string) Str::uuid(),
            'paroisse_configuration_id' => $this->paroisseSM->id,
            'catechumene_id'            => $catOld->id,
            'annee_catechese_id'        => $this->annee->id,
            'section_id'                => $this->secJeune->id,
            'niveau_id'                 => $niveau->id,
            'date_inscription'          => '2026-09-01',
            'statut_inscription'        => 'valide',
        ]);

        // Exécution de la commande Artisan
        $this->artisan('catheo:migrate-matricules --force')
            ->assertSuccessful();

        $catOld->refresh();

        $this->assertNotEquals('SM26-0609131806L', $catOld->matricule);
        $this->assertMatchesRegularExpression('/^SM26-J[A-Z0-9]{4}$/', $catOld->matricule);
    }

    /**
     * Test 9 — Nouveau catéchumène : Reçoit directement le nouveau format à la création
     */
    public function test_09_nouveau_catechumene_recoit_directement_nouveau_format(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/catechumenes', [
                'nom'        => 'KOUAME',
                'prenoms'    => 'Marc',
                'sexe'       => 'M',
                'section_id' => $this->secJeune->uuid,
            ]);

        $response->assertStatus(201);
        $matricule = $response->json('data.matricule');

        $this->assertNotNull($matricule);
        $this->assertStringStartsWith('SM26-J', $matricule);
        $this->assertMatchesRegularExpression(MigrateMatriculesCommand::NEW_FORMAT_REGEX, $matricule);
    }

    /**
     * Test 10 — Matricule existant : Modifier le profil ne régénère pas le matricule
     */
    public function test_10_mise_a_jour_profil_ne_modifie_pas_matricule_existant(): void
    {
        $cat = Catechumene::create([
            'uuid'                      => (string) Str::uuid(),
            'paroisse_configuration_id' => $this->paroisseSM->id,
            'matricule'                 => 'SM26-J8HDX',
            'nom'                       => 'KONAN',
            'prenoms'                   => 'Pierre',
            'sexe'                      => 'M',
            'telephone'                 => '0102030405',
            'statut'                    => 'actif',
        ]);

        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson("/api/v1/catechumenes/{$cat->uuid}", [
                'nom'       => 'KONAN MODIFIE',
                'prenoms'   => 'Pierre Paul',
                'telephone' => '0708091011',
            ]);

        $response->assertStatus(200);
        $cat->refresh();

        $this->assertEquals('SM26-J8HDX', $cat->matricule, 'Le matricule ne doit jamais changer lors de la mise à jour du profil.');
        $this->assertEquals('KONAN MODIFIE', $cat->nom);
    }

    /**
     * Test Cas Particulier : Erreur métier claire si le préfixe paroisse n'est pas configuré
     */
    public function test_erreur_si_prefixe_paroisse_non_configure(): void
    {
        $paroisseSansPrefixe = CatecheseConfiguration::create([
            'uuid'              => (string) Str::uuid(),
            'nom_paroisse'      => 'Paroisse Sans Préfixe',
            'code_paroisse'     => 'PSP-01',
            'prefixe_matricule' => null,
            'statut'            => 'actif',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Le préfixe matricule de la paroisse n'est pas configuré.");

        $this->service->generate($paroisseSansPrefixe->id, $this->secJeune, 2026);
    }

    /**
     * Test Dry-Run : Le mode simulation ne modifie rien en base
     */
    public function test_dry_run_ne_modifie_aucune_donnee(): void
    {
        $cat = Catechumene::create([
            'uuid'                      => (string) Str::uuid(),
            'paroisse_configuration_id' => $this->paroisseSM->id,
            'matricule'                 => 'SM26-0609131806L',
            'nom'                       => 'TEST',
            'prenoms'                   => 'Dry Run',
            'sexe'                      => 'M',
            'statut'                    => 'actif',
        ]);

        $this->artisan('catheo:migrate-matricules --dry-run')
            ->assertSuccessful();

        $cat->refresh();
        $this->assertEquals('SM26-0609131806L', $cat->matricule);
    }
}
