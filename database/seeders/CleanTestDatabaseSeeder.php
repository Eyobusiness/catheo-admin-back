<?php

namespace Database\Seeders;

use App\Models\CatecheseConfiguration;
use App\Models\Menu;
use App\Models\Profil;
use App\Models\ProfilMenuPermission;
use App\Observers\CatecheseConfigurationObserver;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * CleanTestDatabaseSeeder
 *
 * Seeder idempotent pour préparer une base de données de test propre.
 *
 * CE QUE CE SEEDER FAIT :
 *   1. Vide les données métier dans le bon ordre (FK respectées)
 *   2. Conserve les tables système : menus, profil_menu_permissions des profils système
 *   3. Crée la catéchèse "Sainte Monique" (code=SM-01, prefixe_matricule=SM, prefixe_recu=REC)
 *   4. Crée un Super Admin global (paroisse_configuration_id=null)
 *   5. Crée un Admin rattaché à Sainte Monique
 *   6. Crée (ou retrouve) le profil ADMIN pour Sainte Monique avec toutes les permissions
 *
 * IDEMPOTENCE :
 *   Peut être exécuté plusieurs fois sans créer de doublons.
 *   Utilise firstOrCreate / updateOrCreate partout.
 *
 * RESET COMPLET :
 *   php artisan migrate:fresh && php artisan db:seed --class=MenuSeeder
 *   && php artisan db:seed --class=CleanTestDatabaseSeeder
 *
 * IDENTIFIANTS DE TEST :
 *   - Super Admin : superadmin@catheo.ci / SuperAdmin2026!
 *   - Admin SM   : admin@sainte-monique.ci / Admin2026!
 */
class CleanTestDatabaseSeeder extends Seeder
{
    /**
     * Tables métier à vider dans l'ordre inverse des dépendances FK.
     * menus et profil_menu_permissions sont gérés séparément (conservation des profils système).
     */
    private array $tablesToTruncate = [
        // Niveau 5 – feuilles (dépendances des inscriptions & séances)
        'notes',
        'presences',
        'bulletins_trimestriels',
        'decisions_fin_annee',

        // Niveau 4 – évaluations et séances
        'evaluations',
        'seances',
        'modules_trimestriels',

        // Niveau 4 – activités (tables pivot calendriers)
        'activite_animateur',
        'activite_classe',
        'activite_niveau',
        'activite_section',
        'calendriers',

        // Niveau 4 – finances
        'lignes_paiement',
        'operations_paiements',
        'versements',
        'caisse_paroissiale',

        // Niveau 3 – paiements et catéchumènes liés
        'paiements',
        'catechumen_sacrements',
        'mutations_catechumenes',
        'parrains_marraines',

        // Niveau 3 – inscriptions et preinscriptions
        'inscriptions_annuelles',
        'preinscriptions',
        'campagnes_preinscriptions',

        // Niveau 2 – catéchumènes
        'catechumenes',

        // Niveau 2 – animateurs et affectations
        'affectations_animateurs',
        'animateurs',

        // Niveau 2 – organisation pastorale
        'tarif_niveau',
        'tarifs',
        'classes',
        'niveaux',
        'sections',
        'annee_catecheses',

        // Niveau 2 – sacrements
        'sacrements',

        // Niveau 2 – CEBs et mouvements
        'cebs',
        'mouvements',

        // Niveau 2 – annonces & communications
        'annonce_lectures',
        'annonces',

        // Niveau 1 – logs système (non bloquants)
        'notifications_log',
        'system_notifications',
        'audit_logs',
        'sauvegardes',

        // Niveau 1 – documents
        'documents_generes',
        'modeles_documents',

        // Niveau 1 – configurations annexes
        'responsables_paroisse',
        'apparence_configurations',

        // Niveau 0 – données de session / tokens
        'personal_access_tokens',
        'sessions',
        'jobs',
        'failed_jobs',
        'job_batches',
        'cache',
        'cache_locks',
        'password_reset_tokens',
    ];


    public function run(): void
    {
        $this->command->info('');
        $this->command->info('🧹 ÉTAPE 1 – Nettoyage des données métier...');
        $this->cleanBusinessData();

        $this->command->info('');
        $this->command->info('⛪ ÉTAPE 2 – Création de la catéchèse Sainte Monique...');
        $catechese = $this->createSainteMonique();

        $this->command->info('');
        $this->command->info('👑 ÉTAPE 3 – Création du Super Admin global...');
        $superAdmin = $this->createSuperAdmin();

        $this->command->info('');
        $this->command->info('🔑 ÉTAPE 4 – Création de l\'Admin Sainte Monique...');
        $adminUser = $this->createAdminSainteMonique($catechese);

        $this->command->info('');
        $this->command->info('🛡️  ÉTAPE 5 – Vérification du profil ADMIN de Sainte Monique...');
        $this->verifyAdminProfil($catechese, $adminUser);

        $this->command->info('');
        $this->command->info('✅ Base de test propre prête !');
        $this->printSummary($catechese, $superAdmin, $adminUser);
    }

    // =========================================================================
    // ÉTAPE 1 – Nettoyage
    // =========================================================================

    private function cleanBusinessData(): void
    {
        $driver = DB::getDriverName();

        // Désactiver les contraintes FK selon le driver
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF;');
        }

        foreach ($this->tablesToTruncate as $table) {
            try {
                if (DB::getSchemaBuilder()->hasTable($table)) {
                    if ($driver === 'sqlite') {
                        DB::table($table)->delete(); // SQLite ne supporte pas TRUNCATE
                    } else {
                        DB::table($table)->truncate();
                    }
                    $this->command->line("   ✓ {$table} vidée");
                } else {
                    $this->command->warn("   ⚠ Table {$table} inexistante – ignorée");
                }
            } catch (\Exception $e) {
                $this->command->warn("   ⚠ Impossible de vider {$table} : " . $e->getMessage());
            }
        }

        // Supprimer les utilisateurs
        DB::table('users')->delete();
        $this->command->line('   ✓ users vidée');

        // Supprimer tous les profils et leurs permissions (le seeder recrée ce qu'il faut)
        DB::table('profil_menu_permissions')->delete();
        DB::table('profils')->delete();
        $this->command->line('   ✓ profils vidés (profil_menu_permissions conservées pour les menus système)');

        // Supprimer les configurations paroissiales (après users)
        DB::table('paroisse_configurations')->delete();
        $this->command->line('   ✓ paroisse_configurations vidée');

        // Réactiver les contraintes FK
        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = ON;');
        }

        $this->command->info('   ✅ Nettoyage terminé. menus et structure système conservés.');
    }


    // =========================================================================
    // ÉTAPE 2 – Sainte Monique
    // =========================================================================

    private function createSainteMonique(): CatecheseConfiguration
    {
        // Vérifier si Sainte Monique existe déjà
        $catechese = CatecheseConfiguration::where('code_paroisse', 'SM-01')->first();

        if (!$catechese) {
            // Créer via DB::table() pour contourner l'observer
            // (le profil ADMIN sera créé manuellement dans l'étape suivante)
            $now = now();
            $uuid = \Illuminate\Support\Str::uuid()->toString();
            DB::table('paroisse_configurations')->insert([
                'uuid'               => $uuid,
                'nom_paroisse'       => 'Sainte Monique',
                'code_paroisse'      => 'SM-01',
                'prefixe_matricule'  => 'SM',
                'prefixe_recu'       => 'REC',
                'statut'             => 'actif',
                'created_at'         => $now,
                'updated_at'         => $now,
            ]);
            $catechese = CatecheseConfiguration::where('code_paroisse', 'SM-01')->first();
        } else {
            // Mettre à jour les préfixes si nécessaires
            $catechese->update([
                'prefixe_matricule' => 'SM',
                'prefixe_recu'      => 'REC',
            ]);
            $catechese = $catechese->fresh();
        }


        $this->command->line('   ✓ Catéchèse créée : ' . $catechese->nom_paroisse . ' (code: ' . $catechese->code_paroisse . ')');
        $this->command->line('   ✓ prefixe_matricule = ' . $catechese->prefixe_matricule);
        $this->command->line('   ✓ prefixe_recu      = ' . $catechese->prefixe_recu);

        return $catechese;
    }

    // =========================================================================
    // ÉTAPE 3 – Super Admin
    // =========================================================================

    private function createSuperAdmin(): User
    {
        // Récupérer ou créer le profil SUPER_ADMIN système
        $profilSuperAdmin = Profil::firstOrCreate(
            ['code' => 'SUPER_ADMIN'],
            [
                'nom'         => 'Super Administrateur',
                'description' => 'Accès complet et sans restriction à l\'ensemble de la plateforme Catheo.',
                'statut'      => 'actif',
                'permissions' => ['*'],
                'is_system'   => true,
            ]
        );

        // Attribuer toutes les permissions au SUPER_ADMIN sur tous les menus
        $observer = new CatecheseConfigurationObserver();
        $observer->assignAllPermissionsToProfilFromMenus($profilSuperAdmin);

        $superAdmin = User::updateOrCreate(
            ['email' => 'superadmin@catheo.ci'],
            [
                'name'                      => 'Super Administrateur',
                'email'                     => 'superadmin@catheo.ci',
                'password'                  => Hash::make('SuperAdmin2026!'),
                'user_type'                 => 'admin',
                'statut'                    => 'actif',
                'profil_id'                 => $profilSuperAdmin->id,
                'paroisse_configuration_id' => null, // Accès global – pas limité à une paroisse
            ]
        );

        $this->command->line('   ✓ Super Admin créé : ' . $superAdmin->email);
        $this->command->line('   ✓ Profil : ' . $profilSuperAdmin->code . ' (is_system=true, permissions=[*])');
        $this->command->line('   ✓ paroisse_configuration_id = null → accès global toutes paroisses');

        return $superAdmin;
    }

    // =========================================================================
    // ÉTAPE 4 – Admin Sainte Monique
    // =========================================================================

    private function createAdminSainteMonique(CatecheseConfiguration $catechese): User
    {
        // Code unique du profil ADMIN pour cette paroisse
        $codeParoisse = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $catechese->code_paroisse));
        $profilCode   = 'ADMIN_' . $codeParoisse;

        // Créer (ou retrouver) le profil ADMIN pour Sainte Monique
        $profilAdmin = Profil::firstOrCreate(
            ['code' => $profilCode],
            [
                'nom'         => 'Administrateur – ' . $catechese->nom_paroisse,
                'description' => 'Profil administrateur pour la catéchèse : ' . $catechese->nom_paroisse . '. Accès complet à toutes les fonctionnalités.',
                'statut'      => 'actif',
                'permissions' => ['*'],
                'is_system'   => true,
            ]
        );

        // Attribuer toutes les permissions disponibles (dynamique, sans hardcoder les IDs)
        $observer = new CatecheseConfigurationObserver();
        $observer->assignAllPermissionsToProfilFromMenus($profilAdmin);

        // Créer l'utilisateur Admin
        $adminUser = User::updateOrCreate(
            ['email' => 'admin@sainte-monique.ci'],
            [
                'name'                      => 'Administrateur Sainte Monique',
                'email'                     => 'admin@sainte-monique.ci',
                'password'                  => Hash::make('Admin2026!'),
                'user_type'                 => 'admin',
                'statut'                    => 'actif',
                'profil_id'                 => $profilAdmin->id,
                'paroisse_configuration_id' => $catechese->id, // Limité à Sainte Monique
            ]
        );

        $this->command->line('   ✓ Admin créé : ' . $adminUser->email);
        $this->command->line('   ✓ Profil : ' . $profilAdmin->code . ' (toutes permissions)');
        $this->command->line('   ✓ paroisse_configuration_id = ' . $catechese->id . ' → Sainte Monique uniquement');

        return $adminUser;
    }

    // =========================================================================
    // ÉTAPE 5 – Vérification profil ADMIN
    // =========================================================================

    private function verifyAdminProfil(CatecheseConfiguration $catechese, User $adminUser): void
    {
        $codeParoisse = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $catechese->code_paroisse));
        $profilCode   = 'ADMIN_' . $codeParoisse;

        $profil = Profil::where('code', $profilCode)->first();
        if (!$profil) {
            $this->command->error('   ✗ Profil ADMIN introuvable !');
            return;
        }

        $totalMenus       = Menu::count();
        $totalPermissions = ProfilMenuPermission::where('profil_id', $profil->id)->count();

        $this->command->line('   ✓ Profil ADMIN : ' . $profil->nom);
        $this->command->line('   ✓ Code : ' . $profil->code);
        $this->command->line('   ✓ Menus disponibles : ' . $totalMenus);
        $this->command->line('   ✓ Permissions attribuées : ' . $totalPermissions . '/' . $totalMenus);
        $this->command->line('   ✓ Rattaché à l\'utilisateur : ' . $adminUser->email);

        if ($totalPermissions < $totalMenus) {
            $this->command->warn('   ⚠ Certains menus n\'ont pas de permission associée');
        } else {
            $this->command->line('   ✓ Toutes les permissions sont attribuées');
        }
    }

    // =========================================================================
    // RÉSUMÉ FINAL
    // =========================================================================

    private function printSummary(CatecheseConfiguration $catechese, User $superAdmin, User $adminUser): void
    {
        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('  RÉSUMÉ DE LA BASE DE TEST');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('  Catéchèse : ' . $catechese->nom_paroisse);
        $this->command->info('  Code      : ' . $catechese->code_paroisse);
        $this->command->info('  Préfixe M : ' . $catechese->prefixe_matricule);
        $this->command->info('  Préfixe R : ' . $catechese->prefixe_recu);
        $this->command->info('');
        $this->command->info('  Super Admin : ' . $superAdmin->email . ' / SuperAdmin2026!');
        $this->command->info('  Admin SM    : ' . $adminUser->email . ' / Admin2026!');
        $this->command->info('');
        $this->command->info('  Menus en base          : ' . Menu::count());
        $this->command->info('  Profils créés          : ' . Profil::count());
        $this->command->info('  Permissions (matrice)  : ' . ProfilMenuPermission::count());
        $this->command->info('═══════════════════════════════════════════════════════');
    }
}
