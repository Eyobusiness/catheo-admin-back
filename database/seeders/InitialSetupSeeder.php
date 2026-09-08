<?php

namespace Database\Seeders;

use App\Models\Animateur;
use App\Models\AnneeCatechese;
use App\Models\CampagnePreinscription;
use App\Models\Catechumene;
use App\Models\Classe;
use App\Models\Evaluation;
use App\Models\InscriptionAnnuelle;
use App\Models\ModuleTrimestriel;
use App\Models\Niveau;
use App\Models\CatecheseConfiguration;
use App\Models\Profil;
use App\Models\ResponsableCatechese;
use App\Models\Section;
use App\Models\Tarif;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InitialSetupSeeder extends Seeder
{
    /**
     * Run the initial seeds.
     */
    public function run(): void
    {
        // 1. Menus et Profils
        $this->call(MenuSeeder::class);
        $this->call(ProfilSeeder::class);

        $profils = Profil::all()->keyBy('code');
        $profilSuperAdmin = $profils->get('SUPER_ADMIN');
        $profilAdminParoisse = $profils->get('ADMIN_PAROISSE');
        $profilSecretaire = $profils->get('SECRETAIRE');
        $profilRespCatechese = $profils->get('RESPONSABLE_CATECHESE');
        $profilAnimateur = $profils->get('ANIMATEUR');
        $profilComptable = $profils->get('COMPTABLE');
        $profilLecteur = $profils->get('LECTEUR');

        // 2. Paroisse de Démonstration Principale
        $paroisse = CatecheseConfiguration::firstOrCreate(
            ['code_paroisse' => 'PAR-STPAUL-01'],
            [
                'nom_paroisse'     => 'Paroisse Cathédrale Saint-Paul',
                'code_paroisse'    => 'PAR-STPAUL-01',
                'prefixe_matricule'=> 'SP',
                'prefixe_recu'     => 'REC',
                'diocese'          => 'Archidiocèse d\'Abidjan',
                'doyenne'          => 'Doyenne Monseigneur Laurent Yapi',
                'ville'            => 'Abidjan',
                'commune'          => 'Plateau',
                'telephone'        => '+225 2720212223',
                'email'            => 'contact@saintpaul-plateau.ci',
                'site_web'         => 'https://saintpaul-plateau.ci',
                'adresse'          => 'Avenue Jean-Paul II, Plateau, Abidjan',
                'cure_nom'         => 'Père Jean-Baptiste AKRE',
                'coordination_nom' => 'Coordination Pastorale de la Catéchèse',
                'statut'           => 'actif',
            ]
        );

        // 3. Responsables de la Catéchèse
        ResponsableCatechese::firstOrCreate(
            ['nom_prenoms' => 'Père Jean-Baptiste AKRE'],
            [
                'paroisse_configuration_id' => $paroisse->id,
                'fonction'                  => 'Curé de la Paroisse',
                'telephone'                 => '+225 0701020304',
                'statut'                    => 'actif',
            ]
        );

        ResponsableCatechese::firstOrCreate(
            ['nom_prenoms' => 'Père Marc KOFFI'],
            [
                'paroisse_configuration_id' => $paroisse->id,
                'fonction'                  => 'Vicaire Paroissial & Aumônier Catéchèse',
                'telephone'                 => '+225 0702030405',
                'statut'                    => 'actif',
            ]
        );

        // 4. Utilisateurs de Démonstration (7 Profils + Comptes de Test)
        $demoUsers = [
            [
                'name'      => 'Super Administrateur',
                'email'     => 'superadmin@catheo.ci',
                'telephone' => '+225 0100000000',
                'password'  => Hash::make('SuperAdmin123!'),
                'user_type' => 'admin',
                'statut'    => 'actif',
                'profil_id' => $profilSuperAdmin?->id,
                'paroisse_configuration_id' => null,
            ],
            [
                'name'      => 'Abbé Jean-Paul (Admin Paroissial)',
                'email'     => 'admin.stpaul@catheo.ci',
                'telephone' => '+225 0700000001',
                'password'  => Hash::make('12345678'),
                'user_type' => 'admin',
                'statut'    => 'actif',
                'profil_id' => $profilAdminParoisse?->id,
                'paroisse_configuration_id' => $paroisse->id,
            ],
            [
                'name'      => 'Marie KOFFI (Secrétaire)',
                'email'     => 'secretaire@catheo.ci',
                'telephone' => '+225 0700000002',
                'password'  => Hash::make('12345678'),
                'user_type' => 'admin',
                'statut'    => 'actif',
                'profil_id' => $profilSecretaire?->id,
                'paroisse_configuration_id' => $paroisse->id,
            ],
            [
                'name'      => 'Paul KOUASSI (Resp. Catéchèse)',
                'email'     => 'resp.catechese@catheo.ci',
                'telephone' => '+225 0700000003',
                'password'  => Hash::make('12345678'),
                'user_type' => 'admin',
                'statut'    => 'actif',
                'profil_id' => $profilRespCatechese?->id,
                'paroisse_configuration_id' => $paroisse->id,
            ],
            [
                'name'      => 'Marc KONE (Animateur 1)',
                'email'     => 'animateur1@catheo.ci',
                'telephone' => '+225 0700000004',
                'password'  => Hash::make('12345678'),
                'user_type' => 'animateur',
                'statut'    => 'actif',
                'profil_id' => $profilAnimateur?->id,
                'paroisse_configuration_id' => $paroisse->id,
            ],
            [
                'name'      => 'Sophie OUATTARA (Animateur 2)',
                'email'     => 'animateur2@catheo.ci',
                'telephone' => '+225 0700000005',
                'password'  => Hash::make('12345678'),
                'user_type' => 'animateur',
                'statut'    => 'actif',
                'profil_id' => $profilAnimateur?->id,
                'paroisse_configuration_id' => $paroisse->id,
            ],
            [
                'name'      => 'Charles BADO (Comptable)',
                'email'     => 'comptable@catheo.ci',
                'telephone' => '+225 0700000006',
                'password'  => Hash::make('12345678'),
                'user_type' => 'admin',
                'statut'    => 'actif',
                'profil_id' => $profilComptable?->id,
                'paroisse_configuration_id' => $paroisse->id,
            ],
            [
                'name'      => 'Visiteur Auditeur (Lecteur)',
                'email'     => 'lecteur@catheo.ci',
                'telephone' => '+225 0700000007',
                'password'  => Hash::make('12345678'),
                'user_type' => 'admin',
                'statut'    => 'actif',
                'profil_id' => $profilLecteur?->id,
                'paroisse_configuration_id' => $paroisse->id,
            ],
            // Parent de démonstration
            [
                'name'                      => 'Parent Démo Saint-Paul',
                'email'                     => 'parent@catheo.ci',
                'telephone'                 => '+225 0102030405',
                'password'                  => Hash::make('12345678'),
                'user_type'                 => 'parent',
                'statut'                    => 'actif',
                'profil_id'                 => $profils->get('PARENT')?->id ?? $profilLecteur?->id,
                'paroisse_configuration_id' => $paroisse->id,
            ],
            // Compatibilité compte de test historique
            [
                'name'                      => 'Admin Test Legacy',
                'email'                     => 'admin@gmail.com',
                'telephone'                 => '+225 0100000099',
                'password'                  => Hash::make('12345678'),
                'user_type'                 => 'admin',
                'statut'                    => 'actif',
                'profil_id'                 => $profilSuperAdmin?->id,
                'paroisse_configuration_id' => null,
            ],
        ];

        foreach ($demoUsers as $uData) {
            User::updateOrCreate(
                ['email' => $uData['email']],
                $uData
            );
        }

        // 5. Structure Pastorale de Base (Année, Sections, Niveaux, Classes)
        $anneePastoral = AnneeCatechese::firstOrCreate(
            ['libelle' => '2024-2025'],
            [
                'paroisse_configuration_id' => $paroisse->id,
                'date_debut'                => '2024-09-15',
                'date_fin'                  => '2025-06-30',
                'statut'                    => 'active',
            ]
        );

        $secEnfants = Section::firstOrCreate(
            ['code' => 'SEC-ENFANCE'],
            [
                'paroisse_configuration_id' => $paroisse->id,
                'nom'                       => 'Enfance',
                'description'               => 'Catéchèse des enfants de 6 à 11 ans',
                'statut'                    => 'actif',
                'ordre_affichage'           => 1,
            ]
        );

        $secJeunes = Section::firstOrCreate(
            ['code' => 'SEC-JEUNES'],
            [
                'paroisse_configuration_id' => $paroisse->id,
                'nom'                       => 'Jeunes & Adolescents',
                'description'               => 'Catéchèse des adolescents de 12 à 17 ans',
                'statut'                    => 'actif',
                'ordre_affichage'           => 2,
            ]
        );

        $secAdultes = Section::firstOrCreate(
            ['code' => 'SEC-ADULTES'],
            [
                'paroisse_configuration_id' => $paroisse->id,
                'nom'                       => 'Adultes (Catéchuménat)',
                'description'               => 'Préparation aux sacrements de l\'initiation chrétienne pour adultes',
                'statut'                    => 'actif',
                'ordre_affichage'           => 3,
            ]
        );

        $niv1 = Niveau::firstOrCreate(
            [
                'paroisse_configuration_id' => $paroisse->id,
                'section_id'                => $secEnfants->id,
                'nom'                       => '1ère Année d\'Initiation (Éveil à la Foi)',
            ],
            [
                'ordre_affichage'           => 1,
                'statut'                    => 'actif',
            ]
        );

        $niv2 = Niveau::firstOrCreate(
            [
                'paroisse_configuration_id' => $paroisse->id,
                'section_id'                => $secEnfants->id,
                'nom'                       => '2ème Année (Première Communion)',
            ],
            [
                'ordre_affichage'           => 2,
                'statut'                    => 'actif',
            ]
        );

        $nivConf = Niveau::firstOrCreate(
            [
                'paroisse_configuration_id' => $paroisse->id,
                'section_id'                => $secJeunes->id,
                'nom'                       => 'Confirmation - 1ère Année',
            ],
            [
                'ordre_affichage'           => 3,
                'statut'                    => 'actif',
            ]
        );

        // Modules Trimestriels
        ModuleTrimestriel::firstOrCreate(
            ['nom' => '1er Trimestre : Découverte et Foi', 'annee_catechese_id' => $anneePastoral->id],
            [
                'paroisse_configuration_id' => $paroisse->id,
                'numero_trimestre'          => 1,
                'date_debut'                => '2026-09-15',
                'date_fin'                  => '2026-12-20',
            ]
        );

        ModuleTrimestriel::firstOrCreate(
            ['nom' => '2ème Trimestre : Sacrements et Prière', 'annee_catechese_id' => $anneePastoral->id],
            [
                'paroisse_configuration_id' => $paroisse->id,
                'numero_trimestre'          => 2,
                'date_debut'                => '2027-01-05',
                'date_fin'                  => '2027-03-31',
            ]
        );

        ModuleTrimestriel::firstOrCreate(
            ['nom' => '3ème Trimestre : Engagement et Témoignage', 'annee_catechese_id' => $anneePastoral->id],
            [
                'paroisse_configuration_id' => $paroisse->id,
                'numero_trimestre'          => 3,
                'date_debut'                => '2027-04-15',
                'date_fin'                  => '2027-06-30',
            ]
        );

        // Tarifs officiels
        Tarif::firstOrCreate(
            ['intitule' => 'Inscription Annuelle & Manuel de Catéchèse'],
            [
                'paroisse_configuration_id' => $paroisse->id,
                'annee_catechese_id'        => $anneePastoral->id,
                'niveau_id'                 => $niv1->id,
                'description'               => 'Frais complets d\'inscription et manuel paroissial.',
                'montant'                   => 25000.00,
                'type_tarif'                => 'inscription',
                'est_obligatoire'           => true,
                'statut'                    => 'actif',
            ]
        );

        // Campagne de préinscription
        CampagnePreinscription::firstOrCreate(
            ['titre' => 'Campagne Principale d\'Inscriptions 2026-2027'],
            [
                'paroisse_configuration_id' => $paroisse->id,
                'annee_catechese_id'        => $anneePastoral->id,
                'date_debut'                => '2026-08-01',
                'date_fin'                  => '2026-10-15',
                'statut'                    => 'ouverte',
                'description'               => 'Inscriptions et réinscriptions pour la nouvelle année pastorale.',
            ]
        );

        // Classes de base
        $classeBase = Classe::firstOrCreate(
            [
                'paroisse_configuration_id' => $paroisse->id,
                'annee_catechese_id'        => $anneePastoral->id,
                'niveau_id'                 => $niv1->id,
                'nom'                       => 'Initiation 1 - Groupe Saint-Joseph',
            ],
            [
                'statut'                    => 'active',
                'capacite_max'              => 35,
            ]
        );

        Classe::firstOrCreate(
            [
                'paroisse_configuration_id' => $paroisse->id,
                'annee_catechese_id'        => $anneePastoral->id,
                'niveau_id'                 => $niv1->id,
                'nom'                       => 'Initiation 1 - Saint-Joseph (B)',
            ],
            [
                'statut'                    => 'active',
                'capacite_max'              => 35,
            ]
        );

        // Animateur lié
        Animateur::firstOrCreate(
            ['telephone' => '+225 0700000004'],
            [
                'paroisse_configuration_id' => $paroisse->id,
                'nom'                      => 'KONE',
                'prenoms'                  => 'Marc',
                'email'                    => 'animateur1@catheo.ci',
                'password'                 => Hash::make('12345678'),
                'statut'                   => 'actif',
            ]
        );

        // Catéchumène et Parent de base
        $userParent = User::where('email', 'parent@catheo.ci')->first();
        if ($userParent) {
            $catBase = Catechumene::firstOrCreate(
                ['matricule' => 'CAT-2024-DEMO-01'],
                [
                    'paroisse_configuration_id' => $paroisse->id,
                    'user_id'                  => $userParent->id,
                    'nom'                      => 'KOUADIO',
                    'prenoms'                  => 'Ferdinand',
                    'sexe'                     => 'M',
                    'date_naissance'           => '2015-06-20',
                    'lieu_naissance'           => 'Yamoussoukro',
                    'telephone'                => '+225 0102030405',
                    'telephone_tuteur'         => '+225 0102030405',
                    'est_baptise'              => true,
                    'date_bapteme'             => '2016-01-10',
                    'paroisse_bapteme'         => 'Paroisse Saint-Augustin',
                    'statut'                   => 'actif',
                    'password'                 => '12345678',
                ]
            );


            InscriptionAnnuelle::firstOrCreate(
                [
                    'paroisse_configuration_id' => $paroisse->id,
                    'catechumene_id'            => $catBase->id,
                    'annee_catechese_id'        => $anneePastoral->id,
                ],
                [
                    'niveau_id'                => $niv1->id,
                    'classe_id'                => $classeBase->id,
                    'date_inscription'         => '2024-09-10',
                    'statut_inscription'       => 'valide',
                    'frais_inscription_payes'  => true,
                ]
            );
        }

        // 10. Modèles de Documents Officiels
        $this->call(DocumentModeleSeeder::class);
    }
}
