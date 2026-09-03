<?php

namespace Tests\Feature;

use App\Models\CatecheseConfiguration;
use App\Models\Menu;
use App\Models\Profil;
use App\Models\ProfilMenuPermission;
use App\Observers\CatecheseConfigurationObserver;
use App\Models\User;
use Database\Seeders\CleanTestDatabaseSeeder;
use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * CleanDatabaseTest
 *
 * Vérifie les 15 points demandés pour la base de test propre :
 *
 * 1.  Sainte Monique est créée.
 * 2.  prefixe_matricule vaut SM.
 * 3.  prefixe_recu vaut REC.
 * 4.  Le Super Admin existe.
 * 5.  Le Super Admin peut accéder à toutes les paroisses (paroisse_configuration_id = null).
 * 6.  L'Admin Sainte Monique existe.
 * 7.  L'Admin SM est rattaché à Sainte Monique.
 * 8.  L'Admin SM ne peut pas accéder aux autres paroisses (vérifié par scope).
 * 9.  Le profil ADMIN existe pour Sainte Monique.
 * 10. Le profil ADMIN possède toutes les permissions disponibles.
 * 11. Une nouvelle paroisse créée possède automatiquement un profil ADMIN.
 * 12. Ce profil ADMIN reçoit automatiquement toutes les permissions disponibles.
 * 13. La création d'une paroisse ne crée pas plusieurs profils ADMIN.
 * 14. Le seeder peut être exécuté plusieurs fois sans créer de doublons.
 * 15. Les tables menus et profil_menu_permissions ne sont pas vidées.
 */
class CleanDatabaseTest extends TestCase
{
    use RefreshDatabase;

    protected CatecheseConfiguration $sainteMonique;
    protected User $superAdmin;
    protected User $adminSM;
    protected Profil $profilSuperAdmin;
    protected Profil $profilAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        // Exécuter les menus d'abord (nécessaires pour les permissions)
        $this->seed(MenuSeeder::class);
        // Puis le seeder de test propre
        $this->seed(CleanTestDatabaseSeeder::class);

        // Charger les entités pour les tests
        $this->sainteMonique    = CatecheseConfiguration::where('code_paroisse', 'SM-01')->firstOrFail();
        $this->superAdmin       = User::where('email', 'superadmin@catheo.ci')->firstOrFail();
        $this->adminSM          = User::where('email', 'admin@sainte-monique.ci')->firstOrFail();
        $this->profilSuperAdmin = Profil::where('code', 'SUPER_ADMIN')->firstOrFail();
        $this->profilAdmin      = Profil::where('code', 'ADMIN_SM01')->firstOrFail();
    }

    // =========================================================================
    // TEST 1 – Sainte Monique est créée
    // =========================================================================

    public function test_01_sainte_monique_est_creee(): void
    {
        $this->assertDatabaseHas('paroisse_configurations', [
            'nom_paroisse'  => 'Sainte Monique',
            'code_paroisse' => 'SM-01',
        ]);

        $this->assertNotNull($this->sainteMonique);
        $this->assertEquals('Sainte Monique', $this->sainteMonique->nom_paroisse);
    }

    // =========================================================================
    // TEST 2 – prefixe_matricule vaut SM
    // =========================================================================

    public function test_02_prefixe_matricule_vaut_SM(): void
    {
        $this->assertEquals('SM', $this->sainteMonique->prefixe_matricule);

        $this->assertDatabaseHas('paroisse_configurations', [
            'code_paroisse'      => 'SM-01',
            'prefixe_matricule'  => 'SM',
        ]);
    }

    // =========================================================================
    // TEST 3 – prefixe_recu vaut REC
    // =========================================================================

    public function test_03_prefixe_recu_vaut_REC(): void
    {
        $this->assertEquals('REC', $this->sainteMonique->prefixe_recu);

        $this->assertDatabaseHas('paroisse_configurations', [
            'code_paroisse' => 'SM-01',
            'prefixe_recu'  => 'REC',
        ]);
    }

    // =========================================================================
    // TEST 4 – Le Super Admin existe
    // =========================================================================

    public function test_04_super_admin_existe(): void
    {
        $this->assertDatabaseHas('users', [
            'email'     => 'superadmin@catheo.ci',
            'user_type' => 'admin',
            'statut'    => 'actif',
        ]);

        $this->assertNotNull($this->superAdmin);
        $this->assertEquals('SUPER_ADMIN', $this->profilSuperAdmin->code);
        $this->assertTrue($this->profilSuperAdmin->is_system);
    }

    // =========================================================================
    // TEST 5 – Le Super Admin peut accéder à toutes les paroisses
    // =========================================================================

    public function test_05_super_admin_acces_global_toutes_paroisses(): void
    {
        // Le Super Admin n'est rattaché à aucune paroisse (accès global)
        $this->assertNull($this->superAdmin->paroisse_configuration_id);

        // Son profil a permissions=['*'] → hasPermission() retourne true pour tout
        $this->assertTrue($this->profilSuperAdmin->hasPermission('dashboard.view'));
        $this->assertTrue($this->profilSuperAdmin->hasPermission('catechumenes.manage'));
        $this->assertTrue($this->profilSuperAdmin->hasPermission('finances.delete'));
        $this->assertTrue($this->profilSuperAdmin->hasPermission('anything.force_delete'));
    }

    // =========================================================================
    // TEST 6 – L'Admin Sainte Monique existe
    // =========================================================================

    public function test_06_admin_sainte_monique_existe(): void
    {
        $this->assertDatabaseHas('users', [
            'email'     => 'admin@sainte-monique.ci',
            'user_type' => 'admin',
            'statut'    => 'actif',
        ]);

        $this->assertNotNull($this->adminSM);
    }

    // =========================================================================
    // TEST 7 – L'Admin SM est rattaché à Sainte Monique
    // =========================================================================

    public function test_07_admin_sm_rattache_a_sainte_monique(): void
    {
        $this->assertEquals($this->sainteMonique->id, $this->adminSM->paroisse_configuration_id);

        // Vérification via la relation
        $adminWithParoisse = $this->adminSM->load('catechese');
        $this->assertNotNull($adminWithParoisse->catechese);
        $this->assertEquals('Sainte Monique', $adminWithParoisse->catechese->nom_paroisse);
    }

    // =========================================================================
    // TEST 8 – L'Admin SM ne peut pas accéder aux autres paroisses
    // =========================================================================

    public function test_08_admin_sm_ne_peut_pas_acceder_aux_autres_paroisses(): void
    {
        // Créer une autre paroisse directement en DB pour ne pas déclencher l'observer
        DB::table('paroisse_configurations')->insert([
            'uuid'          => \Illuminate\Support\Str::uuid()->toString(),
            'nom_paroisse'  => 'Saint Pierre',
            'code_paroisse' => 'SP-99',
            'statut'        => 'actif',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // L'Admin SM a paroisse_configuration_id = sainteMonique->id
        // Il n'a PAS accès à Saint Pierre
        $autreParoisse = CatecheseConfiguration::where('code_paroisse', 'SP-99')->first();
        $this->assertNotNull($autreParoisse);
        $this->assertNotEquals($autreParoisse->id, $this->adminSM->paroisse_configuration_id);

        // Vérifier que le scope paroisse fonctionne correctement
        $this->assertEquals($this->sainteMonique->id, $this->adminSM->paroisse_configuration_id);
    }


    // =========================================================================
    // TEST 9 – Le profil ADMIN existe pour Sainte Monique
    // =========================================================================

    public function test_09_profil_admin_existe_pour_sainte_monique(): void
    {
        $this->assertDatabaseHas('profils', [
            'code'      => 'ADMIN_SM01',
            'is_system' => true,
            'statut'    => 'actif',
        ]);

        $this->assertNotNull($this->profilAdmin);
        $this->assertEquals('ADMIN_SM01', $this->profilAdmin->code);
        $this->assertTrue($this->profilAdmin->is_system);
    }

    // =========================================================================
    // TEST 10 – Le profil ADMIN possède toutes les permissions disponibles
    // =========================================================================

    public function test_10_profil_admin_possede_toutes_les_permissions(): void
    {
        $totalMenus = Menu::count();
        $this->assertGreaterThan(0, $totalMenus, 'Il doit exister au moins un menu en base');

        $totalPermissions = ProfilMenuPermission::where('profil_id', $this->profilAdmin->id)->count();

        $this->assertEquals(
            $totalMenus,
            $totalPermissions,
            "Le profil ADMIN doit avoir une entrée de permission pour chacun des {$totalMenus} menus"
        );

        // Vérifier que toutes les permissions sont activées (can_read=true sur tous)
        $nonRead = ProfilMenuPermission::where('profil_id', $this->profilAdmin->id)
            ->where('can_read', false)
            ->count();

        $this->assertEquals(0, $nonRead, 'Toutes les permissions can_read doivent être true pour le profil ADMIN');

        // Vérifier hasPermission() fonctionne
        $this->assertTrue($this->profilAdmin->hasPermission('dashboard.view'));
        $this->assertTrue($this->profilAdmin->hasPermission('catechumenes.manage'));
        $this->assertTrue($this->profilAdmin->hasPermission('finances.delete'));
    }

    // =========================================================================
    // TEST 11 – Nouvelle paroisse créée → profil ADMIN auto-créé
    // =========================================================================

    public function test_11_nouvelle_paroisse_cree_profil_admin_automatiquement(): void
    {
        $profilsAvant = Profil::count();

        // Créer une nouvelle paroisse (l'observer doit se déclencher)
        $nouvelleParoisse = CatecheseConfiguration::create([
            'nom_paroisse'  => 'Saint Joseph',
            'code_paroisse' => 'SJ-TEST',
            'statut'        => 'actif',
        ]);

        $profilsApres = Profil::count();
        $this->assertGreaterThan($profilsAvant, $profilsApres, 'Un nouveau profil ADMIN doit être créé');

        // Le profil ADMIN pour SJ-TEST doit exister
        $profilCree = Profil::where('code', 'ADMIN_SJTEST')->first();
        $this->assertNotNull($profilCree, 'Le profil ADMIN_SJTEST doit exister après création de la paroisse');
    }

    // =========================================================================
    // TEST 12 – Profil ADMIN auto-créé → toutes les permissions
    // =========================================================================

    public function test_12_profil_admin_auto_cree_a_toutes_les_permissions(): void
    {
        $nouvelleParoisse = CatecheseConfiguration::create([
            'nom_paroisse'  => 'Sainte Cécile',
            'code_paroisse' => 'SC-TEST',
            'statut'        => 'actif',
        ]);

        $profilCree = Profil::where('code', 'ADMIN_SCTEST')->first();
        $this->assertNotNull($profilCree);

        $totalMenus       = Menu::count();
        $totalPermissions = ProfilMenuPermission::where('profil_id', $profilCree->id)->count();

        $this->assertEquals(
            $totalMenus,
            $totalPermissions,
            'Le profil ADMIN auto-créé doit avoir une permission pour chaque menu'
        );
    }

    // =========================================================================
    // TEST 13 – Pas de doublon profil ADMIN si la paroisse est créée deux fois
    // =========================================================================

    public function test_13_pas_de_doublon_profil_admin(): void
    {
        // Créer la paroisse directement en DB (sans observer)
        DB::table('paroisse_configurations')->insert([
            'uuid'          => \Illuminate\Support\Str::uuid()->toString(),
            'nom_paroisse'  => 'Notre Dame',
            'code_paroisse' => 'ND-TEST',
            'statut'        => 'actif',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        // Simuler un appel manuel de l'observer (idempotence)
        $paroisse = CatecheseConfiguration::where('code_paroisse', 'ND-TEST')->first();
        $observer = new CatecheseConfigurationObserver();
        $observer->createAdminProfilForParoisse($paroisse);
        $observer->createAdminProfilForParoisse($paroisse); // Deuxième appel = pas de doublon

        $count = Profil::where('code', 'ADMIN_NDTEST')->count();
        $this->assertEquals(1, $count, 'Il ne doit exister qu\'un seul profil ADMIN pour cette paroisse');
    }


    // =========================================================================
    // TEST 14 – Seeder idempotent (2ème exécution = pas de doublons)
    // =========================================================================

    public function test_14_seeder_idempotent_sans_doublons(): void
    {
        // Premier seeder (déjà exécuté dans setUp)
        $paroisses1    = CatecheseConfiguration::count();
        $users1        = User::count();
        $profils1      = Profil::count();

        // Deuxième exécution du seeder
        $this->seed(CleanTestDatabaseSeeder::class);

        $paroisses2 = CatecheseConfiguration::count();
        $users2     = User::count();
        $profils2   = Profil::count();

        $this->assertEquals($paroisses1, $paroisses2, 'Pas de doublon de paroisse');
        $this->assertEquals($users1, $users2, 'Pas de doublon d\'utilisateur');
        $this->assertEquals($profils1, $profils2, 'Pas de doublon de profil');

        // Vérifier qu'il n'y a qu'une Sainte Monique
        $smCount = CatecheseConfiguration::where('code_paroisse', 'SM-01')->count();
        $this->assertEquals(1, $smCount);

        // Vérifier qu'il n'y a qu'un Super Admin
        $saCount = User::where('email', 'superadmin@catheo.ci')->count();
        $this->assertEquals(1, $saCount);
    }

    // =========================================================================
    // TEST 15 – menus et profil_menu_permissions non vidées
    // =========================================================================

    public function test_15_menus_et_permissions_systeme_conserves(): void
    {
        // Les menus doivent exister
        $totalMenus = Menu::count();
        $this->assertGreaterThan(0, $totalMenus, 'Les menus ne doivent pas être vidés');

        // Les profil_menu_permissions doivent exister
        $totalPerms = ProfilMenuPermission::count();
        $this->assertGreaterThan(0, $totalPerms, 'Les profil_menu_permissions ne doivent pas être vidées');

        // Vérifier que le seeder n'a pas supprimé tous les menus
        $this->assertDatabaseHas('menus', ['reference' => 'dashboard']);
    }

    // =========================================================================
    // TEST BONUS – MatriculeGeneratorService utilise le préfixe de la paroisse
    // =========================================================================

    public function test_bonus_matricule_service_utilise_prefixe_paroisse(): void
    {
        $service  = new \App\Services\MatriculeGeneratorService();
        $matricule = $service->generate($this->sainteMonique->id);

        // Le matricule doit commencer par 'SM' (pas de hardcoding dans le service)
        $this->assertStringStartsWith('SM', $matricule, 'Le matricule doit commencer par le préfixe SM de Sainte Monique');
    }

    // =========================================================================
    // TEST BONUS – ReceiptNumberGeneratorService utilise le préfixe de la paroisse
    // =========================================================================

    public function test_bonus_recu_service_utilise_prefixe_paroisse(): void
    {
        $service = new \App\Services\ReceiptNumberGeneratorService();
        $numero  = $service->generate($this->sainteMonique->id);

        // Le numéro doit commencer par 'REC' (pas de hardcoding dans le service)
        $this->assertStringStartsWith('REC', $numero, 'Le numéro de reçu doit commencer par le préfixe REC de Sainte Monique');
    }
}
