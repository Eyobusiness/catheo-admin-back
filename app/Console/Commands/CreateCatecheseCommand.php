<?php

namespace App\Console\Commands;

use App\Models\AnneeCatechese;
use App\Models\CatecheseConfiguration;
use App\Models\Niveau;
use App\Models\Profil;
use App\Models\Section;
use App\Models\User;
use App\Observers\CatecheseConfigurationObserver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateCatecheseCommand extends Command
{
    /**
     * Signature de la commande Artisan.
     */
    protected $signature = 'catheo:create-paroisse
                            {nom? : Nom de la paroisse / catéchèse (ex: Coeur Immaculé de Marie)}
                            {--code= : Code unique de la paroisse (ex: CIM-01)}
                            {--prefixe-matricule= : Préfixe matricule (ex: CIM)}
                            {--prefixe-recu=REC : Préfixe reçu (ex: REC)}
                            {--email= : Email de connexion de l\'administrateur}
                            {--password= : Mot de passe de l\'administrateur}
                            {--nom-admin= : Nom complet de l\'administrateur}
                            {--with-year : Créer une année pastorale par défaut (optionnel, l\'admin doit la créer après connexion)}
                            {--with-defaults : Créer également les sections et niveaux standards}';

    /**
     * Description de la commande.
     */
    protected $description = 'Crée une nouvelle catéchèse / paroisse avec son profil administrateur et son utilisateur admin (sans année pastorale par défaut).';

    public function handle(): int
    {
        $this->info('');
        $this->info('╔════════════════════════════════════════════════════════════╗');
        $this->info('║       CRÉATION D\'UNE NOUVELLE PAROISSE / CATÉCHÈSE        ║');
        $this->info('╚════════════════════════════════════════════════════════════╝');
        $this->info('');

        // 1. Récupération des informations de la paroisse
        $nom = $this->argument('nom') ?: $this->ask('Nom de la paroisse / catéchèse', 'Coeur Immaculé de Marie');
        $code = $this->option('code') ?: $this->ask('Code unique de la paroisse', 'CIM-01');
        $prefixeMatricule = $this->option('prefixe-matricule') ?: $this->ask('Préfixe pour les matricules', 'CIM');
        $prefixeRecu = $this->option('prefixe-recu') ?: 'REC';

        // 2. Récupération des informations de l'administrateur
        $adminEmail = $this->option('email') ?: $this->ask('Email de l\'administrateur', 'admin@cim.ci');
        $adminPassword = $this->option('password') ?: $this->secret('Mot de passe de l\'administrateur') ?: 'Admin2026@';
        $adminName = $this->option('nom-admin') ?: 'Administrateur ' . $nom;

        // 3. Création ou mise à jour de la paroisse (CatecheseConfiguration)
        $this->line('⏳ 1. Création de la paroisse...');
        $paroisse = CatecheseConfiguration::firstOrCreate(
            ['code_paroisse' => $code],
            [
                'nom_paroisse'      => $nom,
                'prefixe_matricule' => strtoupper($prefixeMatricule),
                'prefixe_recu'      => strtoupper($prefixeRecu),
                'statut'            => 'actif',
            ]
        );

        // Si elle existait déjà, assurer la mise à jour des infos
        $paroisse->update([
            'nom_paroisse'      => $nom,
            'prefixe_matricule' => strtoupper($prefixeMatricule),
            'prefixe_recu'      => strtoupper($prefixeRecu),
            'statut'            => 'actif',
        ]);

        $this->info("   ✅ Paroisse enregistrée : {$paroisse->nom_paroisse} (ID: {$paroisse->id}, Code: {$paroisse->code_paroisse})");

        // 4. Création ou récupération du profil ADMIN pour cette paroisse
        $this->line('⏳ 2. Configuration du profil ADMIN et attribution des permissions...');
        $observer = new CatecheseConfigurationObserver();
        $profil = $observer->createAdminProfilForParoisse($paroisse);
        $this->info("   ✅ Profil ADMIN configuré : {$profil->nom} (Code: {$profil->code}, ID: {$profil->id})");

        // 5. Création de l'année pastorale (uniquement si demandée)
        $anneeCourante = null;
        if ($this->option('with-year')) {
            $this->line('⏳ 3. Configuration de l\'année pastorale courante...');
            $anneeCourante = AnneeCatechese::firstOrCreate(
                [
                    'paroisse_configuration_id' => $paroisse->id,
                    'libelle'                   => '2026-2027',
                ],
                [
                    'date_debut' => '2026-09-01',
                    'date_fin'   => '2027-08-31',
                    'statut'     => 'active',
                ]
            );
            $this->info("   ✅ Année pastorale active : {$anneeCourante->libelle} (ID: {$anneeCourante->id})");
        } else {
            $this->line('ℹ️  Aucune année active créée (l\'administrateur créera son année pastorale après connexion).');
        }

        // 6. Création ou mise à jour du compte administrateur
        $this->line('⏳ 4. Création du compte utilisateur Administrateur...');
        $adminUser = User::updateOrCreate(
            ['email' => $adminEmail],
            [
                'name'                      => $adminName,
                'password'                  => Hash::make($adminPassword),
                'user_type'                 => 'admin',
                'statut'                    => 'actif',
                'profil_id'                 => $profil->id,
                'paroisse_configuration_id' => $paroisse->id,
            ]
        );
        $this->info("   ✅ Utilisateur admin rattaché : {$adminUser->email} (ID: {$adminUser->id})");

        // 7. Sections par défaut optionnelles
        if ($this->option('with-defaults')) {
            $this->line('⏳ 5. Initialisation des sections et niveaux standards...');
            $this->createDefaultSectionsAndNiveaux($paroisse);
            $this->info("   ✅ Sections et niveaux standards créés.");
        }

        // 8. Résumé
        $this->info('');
        $this->info('════════════════════════════════════════════════════════════');
        $this->info('  FÉLICITATIONS ! CATÉCHÈSE CRÉÉE AVEC SUCCÈS');
        $this->info('════════════════════════════════════════════════════════════');
        $this->line("  • Nom Paroisse   : {$paroisse->nom_paroisse}");
        $this->line("  • Code Paroisse  : {$paroisse->code_paroisse} (ID: {$paroisse->id})");
        $this->line("  • Préfixe Matr.  : {$paroisse->prefixe_matricule}");
        $this->line("  • Préfixe Reçu   : {$paroisse->prefixe_recu}");
        $this->line("  • Profil assigné : {$profil->nom} ({$profil->code})");
        $this->line("  • Année active   : " . ($anneeCourante ? $anneeCourante->libelle : 'Aucune (à créer par l\'admin)'));
        $this->line('  ----------------------------------------------------------');
        $this->line("  • Identifiant    : {$adminUser->email}");
        $this->line("  • Mot de passe   : {$adminPassword}");
        $this->line('════════════════════════════════════════════════════════════');
        $this->info('');

        return Command::SUCCESS;
    }

    /**
     * Crée des sections et niveaux par défaut pour la paroisse si demandé.
     */
    private function createDefaultSectionsAndNiveaux(CatecheseConfiguration $paroisse): void
    {
        $sectionsData = [
            [
                'code'    => 'SEC-ENF-PRI',
                'nom'     => 'Enfant Primaire',
                'ordre'   => 1,
                'niveaux' => ['1ère Année', '2ème Année', '3ème Année', '4ème Année', '5ème Année'],
            ],
            [
                'code'    => 'SEC-ENF-COL',
                'nom'     => 'Enfant Collège',
                'ordre'   => 2,
                'niveaux' => ['1ère Année', '2ème Année', '3ème Année'],
            ],
            [
                'code'    => 'SEC-JEUNE',
                'nom'     => 'Jeunes',
                'ordre'   => 3,
                'niveaux' => ['1ère Année', '2ème Année'],
            ],
            [
                'code'    => 'SEC-ADULTE',
                'nom'     => 'Adultes',
                'ordre'   => 4,
                'niveaux' => ['1ère Année', '2ème Année', '3ème Année'],
            ],
        ];

        foreach ($sectionsData as $sData) {
            $section = Section::firstOrCreate(
                [
                    'paroisse_configuration_id' => $paroisse->id,
                    'code'                      => $sData['code'],
                ],
                [
                    'nom'             => $sData['nom'],
                    'statut'          => 'actif',
                    'ordre_affichage' => $sData['ordre'],
                ]
            );

            $order = 1;
            foreach ($sData['niveaux'] as $nivNom) {
                Niveau::firstOrCreate(
                    [
                        'paroisse_configuration_id' => $paroisse->id,
                        'section_id'                => $section->id,
                        'nom'                       => $nivNom,
                    ],
                    [
                        'statut'          => 'actif',
                        'ordre_affichage' => $order++,
                    ]
                );
            }
        }
    }
}
