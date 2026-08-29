<?php

namespace Database\Seeders;

use App\Models\Activite;
use App\Models\AffectationAnimateur;
use App\Models\Animateur;
use App\Models\Annonce;
use App\Models\AnneeCatechese;
use App\Models\ApparenceConfiguration;
use App\Models\AuditLog;
use App\Models\BulletinTrimestriel;
use App\Models\CaisseParoissiale;
use App\Models\Calendrier;
use App\Models\CampagnePreinscription;
use App\Models\Catechumene;
use App\Models\Ceb;
use App\Models\Classe;
use App\Models\DecisionFinAnnee;
use App\Models\Evaluation;
use App\Models\InscriptionAnnuelle;
use App\Models\LignePaiement;
use App\Models\ModuleTrimestriel;
use App\Models\Mouvement;
use App\Models\MutationCatechumene;
use App\Models\Niveau;
use App\Models\Note;
use App\Models\NotificationLog;
use App\Models\OperationPaiement;
use App\Models\Paiement;
use App\Models\CatecheseConfiguration;
use App\Models\ParrainMarraine;
use App\Models\Preinscription;
use App\Models\Presence;
use App\Models\Profil;
use App\Models\Sauvegarde;
use App\Models\Seance;
use App\Models\Section;
use App\Models\Tarif;
use App\Models\User;
use App\Models\Versement;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class FakeDataSeeder extends Seeder
{
    public function run(): void
    {
        $paroisse = CatecheseConfiguration::first();
        if (!$paroisse) {
            $this->command->error('Exécutez InitialSetupSeeder au préalable.');
            return;
        }

        $profils = Profil::all()->keyBy('code');
        $profilAnimateur = $profils->get('ANIMATEUR');
        $profilSuperAdmin = $profils->get('SUPER_ADMIN');

        $annee = AnneeCatechese::where('paroisse_configuration_id', $paroisse->id)->first();
        $sections = Section::where('paroisse_configuration_id', $paroisse->id)->get()->keyBy('code');
        $secEnfance = $sections->get('SEC-ENFANCE');
        $secJeunes = $sections->get('SEC-JEUNES');
        $secAdultes = $sections->get('SEC-ADULTES');

        $niv1 = Niveau::where('paroisse_configuration_id', $paroisse->id)->where('nom', 'like', '%1ère Année%')->first();
        $niv2 = Niveau::where('paroisse_configuration_id', $paroisse->id)->where('nom', 'like', '%2ème Année%')->first();
        $nivConf = Niveau::where('paroisse_configuration_id', $paroisse->id)->where('nom', 'like', '%Confirmation%')->first();

        $this->command->info('Génération du jeu de données complet et cohérent pour Catheo...');

        // ── 1. APPARENCE & CONFIGURATION ──────────────────────────
        ApparenceConfiguration::firstOrCreate(
            ['paroisse_configuration_id' => $paroisse->id],
            [
                'couleur_principale' => '#1B3A4B',
                'couleur_secondaire' => '#E07A5F',
                'police_caracteres'  => 'Inter',
                'entete_document'    => 'ARCHIDIOCESE D\'ABIDJAN - PAROISSE SAINT-PAUL DU PLATEAU',
                'pied_page_document' => 'Secrétariat Paroissial - Tél : +225 27 20 21 22 23 - email : contact@saintpaul-plateau.ci',
            ]
        );

        // ── 2. CEB & MOUVEMENTS ───────────────────────────────────
        $cebsData = [
            ['nom' => 'CEB Sainte-Trinité', 'code' => 'CEB-TRINITE', 'secteur' => 'Plateau Centre', 'responsable' => 'M. KOFFI Daniel', 'tel' => '+225 0708091001'],
            ['nom' => 'CEB Saint-Esprit',   'code' => 'CEB-ESPRIT',  'secteur' => 'Plateau Nord',   'responsable' => 'Mme KONE Juliette', 'tel' => '+225 0708091002'],
            ['nom' => 'CEB Sainte-Famille',  'code' => 'CEB-FAMILLE', 'secteur' => 'Plateau Sud',    'responsable' => 'M. YAO Thomas',     'tel' => '+225 0708091003'],
        ];
        foreach ($cebsData as $c) {
            Ceb::firstOrCreate(
                ['paroisse_configuration_id' => $paroisse->id, 'nom' => $c['nom']],
                [
                    'responsable' => $c['responsable'],
                    'telephone'   => $c['tel'],
                    'adresse'     => $c['secteur'],
                    'statut'      => 'Active',
                ]
            );
        }

        $mouvementsData = [
            ['nom' => 'Scouts et Guides de Côte d\'Ivoire', 'code' => 'MVT-SCOUT', 'description' => 'Mouvement éducatif pour enfants et jeunes'],
            ['nom' => 'Enfance Missionnaire',               'code' => 'MVT-EM',    'description' => 'Animation missionnaire pour les enfants'],
            ['nom' => 'Cœurs Vaillants et Âmes Vaillantes', 'code' => 'MVT-CVAV',  'description' => 'Apostolat catholique des enfants'],
        ];
        foreach ($mouvementsData as $m) {
            Mouvement::firstOrCreate(
                ['paroisse_configuration_id' => $paroisse->id, 'nom' => $m['nom']],
                [
                    'description' => $m['description'],
                ]
            );
        }

        // ── 3. CLASSES ────────────────────────────────────────────
        $classesConfig = [
            ['nom' => 'Initiation 1 - Groupe Saint-Joseph',   'niveau' => $niv1,    'cap' => 35],
            ['nom' => 'Initiation 1 - Groupe Sainte-Thérèse', 'niveau' => $niv1,    'cap' => 30],
            ['nom' => 'Initiation 2 - Groupe Saint-Pierre',   'niveau' => $niv2,    'cap' => 35],
            ['nom' => 'Confirmation 1 - Groupe Saint-Paul',   'niveau' => $nivConf, 'cap' => 40],
        ];

        $classes = collect();
        foreach ($classesConfig as $cfg) {
            if ($cfg['niveau']) {
                $cls = Classe::firstOrCreate(
                    [
                        'paroisse_configuration_id' => $paroisse->id,
                        'annee_catechese_id'        => $annee->id,
                        'niveau_id'                 => $cfg['niveau']->id,
                        'nom'                       => $cfg['nom'],
                    ],
                    [
                        'capacite_max'              => $cfg['cap'],
                        'statut'                    => 'active',
                    ]
                );
                $classes->push($cls);
            }
        }

        // ── 4. ANIMATEURS & AFFECTATIONS ──────────────────────────
        $animateursData = [
            ['KONAN',    'Adjoua Marie',     'F', '+225 0701010201', 'Infirmière', 'marie.konan@catheo.ci'],
            ['BAMBA',    'Seydou Luc',       'M', '+225 0701010202', 'Comptable',  'luc.bamba@catheo.ci'],
            ['GBAGBO',   'Akissi Elise',     'F', '+225 0701010203', 'Enseignante','elise.gbagbo@catheo.ci'],
            ['OUATTARA', 'Ibrahim Jean',     'M', '+225 0701010204', 'Ingénieur',  'jean.ouattara@catheo.ci'],
            ['TOURE',    'Fatoumata Claire', 'F', '+225 0701010205', 'Secrétaire', 'claire.toure@catheo.ci'],
            ['KOUAME',   'Ama Sophie',       'F', '+225 0701010207', 'Pharmacienne','sophie.kouame@catheo.ci'],
        ];

        $animateurs = collect();
        foreach ($animateursData as $idx => $d) {
            $userAnim = User::firstOrCreate(
                ['email' => $d[5]],
                [
                    'name'                      => $d[0] . ' ' . $d[1],
                    'telephone'                 => $d[3],
                    'password'                  => Hash::make('Animateur123!'),
                    'user_type'                 => 'animateur',
                    'statut'                    => 'actif',
                    'profil_id'                 => $profilAnimateur?->id,
                    'paroisse_configuration_id' => $paroisse->id,
                ]
            );

            $anim = Animateur::firstOrCreate(
                ['telephone' => $d[3]],
                [
                    'paroisse_configuration_id' => $paroisse->id,
                    'nom'                      => $d[0],
                    'prenoms'                  => $d[1],
                    'sexe'                     => $d[2],
                    'email'                    => $d[5],
                    'password'                 => Hash::make('12345678'),
                    'profession'               => $d[4],
                    'statut'                   => 'actif',
                ]
            );
            $animateurs->push($anim);

            // Affectation aux classes
            $targetClasse = $classes->get($idx % $classes->count());
            if ($targetClasse) {
                AffectationAnimateur::firstOrCreate(
                    [
                        'paroisse_configuration_id' => $paroisse->id,
                        'animateur_id'              => $anim->id,
                        'classe_id'                 => $targetClasse->id,
                    ],
                    [
                        'annee_catechese_id' => $annee->id,
                        'role_animateur'     => ($idx % 2 === 0) ? 'principal' : 'adjoint',
                    ]
                );
            }
        }

        // ── 5. CALENDRIER PASTORAL ────────────────────────────────
        $calendriersData = [
            ['titre' => 'Messe de Rentrée Pastorale',     'type' => 'Célébration',  'date' => '2026-09-20', 'h_debut' => '08:30', 'h_fin' => '11:30', 'lieu' => 'Cathédrale Saint-Paul',        'cible_type' => 'TOUS',    'statut' => 'Réalisé'],
            ['titre' => 'Récollection de l\'Avent',       'type' => 'Récollection', 'date' => '2026-12-06', 'h_debut' => '09:00', 'h_fin' => '15:00', 'lieu' => 'Centre Pastoral Saint-Jean',   'cible_type' => 'SECTION', 'statut' => 'Réalisé'],
            ['titre' => 'Devoirs du Premier Trimestre',   'type' => 'Évaluation',   'date' => '2026-12-13', 'h_debut' => '09:00', 'h_fin' => '11:00', 'lieu' => 'Salles de Catéchèse',         'cible_type' => 'NIVEAU',  'statut' => 'Réalisé'],
            ['titre' => 'Pèlerinage au Sanctuaire Marial', 'type' => 'Pèlerinage',   'date' => '2027-03-14', 'h_debut' => '07:30', 'h_fin' => '17:00', 'lieu' => 'Sanctuaire Marial d\'Adjamé', 'cible_type' => 'TOUS',    'statut' => 'Planifié'],
        ];

        foreach ($calendriersData as $cal) {
            Calendrier::firstOrCreate(
                ['paroisse_configuration_id' => $paroisse->id, 'titre' => $cal['titre']],
                [
                    'annee_catechese_id' => $annee->id,
                    'type'               => $cal['type'],
                    'date'               => $cal['date'],
                    'heure_debut'        => $cal['h_debut'],
                    'heure_fin'          => $cal['h_fin'],
                    'lieu'               => $cal['lieu'],
                    'cible_type'         => $cal['cible_type'],
                    'description'        => 'Événement officiel inscrit au calendrier pastoral.',
                    'statut'             => $cal['statut'],
                ]
            );
        }

        // ── 6. PRÉINSCRIPTIONS ────────────────────────────────────
        $campagne = CampagnePreinscription::first();
        $preinscriptionsData = [
            ['KOUASSI', 'Jean Emmanuel', 'M', '2016-04-12', 'Yamoussoukro', 'KOUASSI Michel', 'KONAN Therese', '+225 0709010203', 'famille.kouassi@email.ci', 'validee'],
            ['YAO',     'Grace Emmanuelle', 'F', '2015-08-22', 'Abidjan Cocody', 'YAO Kouame', 'AMANI Henriette', '+225 0709010204', 'famille.yao@email.ci', 'validee'],
            ['KONE',    'Ange Marc',     'M', '2016-02-18', 'Bouaké', 'KONE Bakary', 'DIALLO Fatou', '+225 0709010205', 'famille.kone@email.ci', 'en_attente'],
            ['TRAORE',  'Marie Chloe',   'F', '2017-11-05', 'Abidjan Plateau', 'TRAORE Adama', 'CISSE Sarah', '+225 0709010206', 'famille.traore@email.ci', 'rejetee'],
        ];

        foreach ($preinscriptionsData as $idx => $p) {
            Preinscription::firstOrCreate(
                ['code_dossier' => sprintf('PRE-2026-%03d', $idx + 1)],
                [
                    'paroisse_configuration_id'  => $paroisse->id,
                    'campagne_preinscription_id' => $campagne?->id,
                    'annee_catechese_id'         => $annee->id,
                    'niveau_souhaite_id'         => $niv1?->id,
                    'type_demande'               => 'nouvelle_inscription',
                    'nom'                        => $p[0],
                    'prenoms'                    => $p[1],
                    'sexe'                       => $p[2],
                    'date_naissance'             => $p[3],
                    'lieu_naissance'             => $p[4],
                    'nom_pere'                   => $p[5],
                    'telephone_pere'             => $p[7],
                    'nom_mere'                   => $p[6],
                    'telephone_mere'             => $p[7],
                    'telephone'                  => $p[7],
                    'est_baptise'                => ($idx % 2 === 0),
                    'statut'                     => $p[9],
                    'notes_validation'           => ($p[9] === 'rejetee') ? 'Âge requis non atteint.' : 'Dossier complet et vérifié.',
                ]
            );
        }

        // ── 7. CATÉCHUMÈNES, INSC. ANNUELLES, PARRAINS, MUTATIONS ──
        $catechumenesData = [
            ['KOUADIO',   'Ferdinand',      'M', '2015-06-20', 'Yamoussoukro', '+225 0102030405', 'ferdinand.kouadio@email.com', true,  '2016-01-10', 'Paroisse Saint-Augustin'],
            ['AMANI',     'Christelle',     'F', '2015-09-14', 'Abidjan',      '+225 0102030406', 'christelle.amani@email.com',  true,  '2016-03-25', 'Cathédrale Saint-Paul'],
            ['N\'DRI',    'Jean-Luc',       'M', '2014-11-03', 'Daloa',        '+225 0102030407', 'jeanluc.ndri@email.com',      false, null,         null],
            ['KASSI',     'Dominique Ange', 'M', '2015-02-17', 'Grand-Bassam', '+225 0102030408', 'dominique.kassi@email.com',   true,  '2015-12-05', 'Paroisse Sainte-Thérèse'],
            ['BEUGRE',    'Sandrine Affou', 'F', '2014-07-28', 'Dabou',        '+225 0102030409', 'sandrine.beugre@email.com',   false, null,         null],
            ['DJE',       'Wilfried',       'M', '2013-05-19', 'Tiassalé',     '+225 0102030410', 'wilfried.dje@email.com',      true,  '2014-08-15', 'Paroisse Saint-Joseph'],
        ];

        $catechumenes = collect();
        $inscriptions = collect();
        $primaryClass = $classes->first();

        foreach ($catechumenesData as $i => $c) {
            $mat = sprintf('CAT-2026-%04d', $i + 1);

            $userParent = User::firstOrCreate(
                ['email' => $c[6]],
                [
                    'name'                      => 'Tuteur de ' . $c[0] . ' ' . $c[1],
                    'username'                  => $mat,
                    'telephone'                 => $c[5],
                    'password'                  => Hash::make('Parent123!'),
                    'user_type'                 => 'parent',
                    'statut'                    => 'actif',
                    'paroisse_configuration_id' => $paroisse->id,
                ]
            );

            $cat = Catechumene::firstOrCreate(
                ['matricule' => $mat],
                [
                    'paroisse_configuration_id' => $paroisse->id,
                    'user_id'                  => $userParent->id,
                    'nom'                      => $c[0],
                    'prenoms'                  => $c[1],
                    'sexe'                     => $c[2],
                    'date_naissance'           => $c[3],
                    'lieu_naissance'           => $c[4],
                    'telephone'                => $c[5],
                    'telephone_tuteur'         => $c[5],
                    'est_baptise'              => $c[7],
                    'date_bapteme'             => $c[8],
                    'paroisse_bapteme'         => $c[9],
                    'statut'                   => 'actif',
                ]
            );
            $catechumenes->push($cat);

            // Inscription Annuelle
            $insc = InscriptionAnnuelle::firstOrCreate(
                [
                    'paroisse_configuration_id' => $paroisse->id,
                    'catechumene_id'            => $cat->id,
                    'annee_catechese_id'        => $annee->id,
                ],
                [
                    'niveau_id'               => $primaryClass?->niveau_id ?? $niv1->id,
                    'classe_id'               => $primaryClass?->id,
                    'code_inscription'        => sprintf('INS-2026-%04d', $i + 1),
                    'date_inscription'        => '2026-09-05',
                    'statut_inscription'      => 'valide',
                    'frais_inscription_payes' => true,
                ]
            );
            $inscriptions->push($insc);

            // Parrain / Marraine
            ParrainMarraine::firstOrCreate(
                ['paroisse_configuration_id' => $paroisse->id, 'catechumene_id' => $cat->id],
                [
                    'type'                  => ($i % 2 === 0) ? 'parrain' : 'marraine',
                    'nom_prenoms'           => 'PARRAIN_' . $c[0] . ' Joseph',
                    'telephone'             => '+225 07090909' . sprintf('%02d', $i),
                    'email'                 => 'parrain.' . strtolower($c[0]) . '@email.com',
                    'paroisse_origine'      => 'Paroisse Saint-Jean de Cocody',
                    'sacrement_confirmation'=> true,
                ]
            );
        }

        // Mutation exemple
        if ($catechumenes->isNotEmpty()) {
            MutationCatechumene::firstOrCreate(
                ['paroisse_configuration_id' => $paroisse->id, 'catechumene_id' => $catechumenes->last()->id],
                [
                    'annee_catechese_id'       => $annee->id,
                    'paroisse_origine_nom'     => 'Paroisse Saint-Paul du Plateau',
                    'paroisse_destination_nom' => 'Paroisse Sainte-Famille de la Riviera 2',
                    'motif'                    => 'Déménagement de la famille',
                    'date_mutation'            => '2026-10-15',
                    'statut'                   => 'approuve',
                ]
            );
        }

        // ── 8. SÉANCES & PRÉSENCES ────────────────────────────────
        $mod1 = ModuleTrimestriel::where('annee_catechese_id', $annee->id)->first();

        $seancesData = [
            ['titre' => 'Dieu Créateur et Père de Miséricorde', 'date' => '2026-10-04', 'debut' => '09:00', 'fin' => '11:00'],
            ['titre' => 'La Création et l\'Alliance avec Abraham', 'date' => '2026-10-11', 'debut' => '09:00', 'fin' => '11:00'],
            ['titre' => 'Moïse et la Loi de l\'Amour', 'date' => '2026-10-18', 'debut' => '09:00', 'fin' => '11:00'],
            ['titre' => 'Jésus, Fils de Dieu et Sauveur', 'date' => '2026-10-25', 'debut' => '09:00', 'fin' => '11:00'],
        ];

        foreach ($seancesData as $sd) {
            if ($primaryClass) {
                $seance = Seance::firstOrCreate(
                    [
                        'paroisse_configuration_id' => $paroisse->id,
                        'classe_id'                 => $primaryClass->id,
                        'date_seance'               => $sd['date'],
                    ],
                    [
                        'annee_catechese_id' => $annee->id,
                        'titre'              => $sd['titre'],
                        'heure_debut'        => $sd['debut'] . ':00',
                        'heure_fin'          => $sd['fin'] . ':00',
                        'statut'             => 'effectuee',
                    ]
                );

                // Pointage présences pour chaque catéchumène
                foreach ($catechumenes as $idx => $cat) {
                    $statutPres = ($idx === 4) ? 'absent' : (($idx === 5) ? 'retard' : 'present');
                    Presence::firstOrCreate(
                        [
                            'paroisse_configuration_id' => $paroisse->id,
                            'seance_id'                 => $seance->id,
                            'catechumene_id'            => $cat->id,
                        ],
                        [
                            'statut_presence' => $statutPres,
                            'motif_absence'   => ($statutPres === 'absent') ? 'Raison familiale' : null,
                        ]
                    );
                }
            }
        }

        // ── 9. ÉVALUATIONS, NOTES, BULLETINS & DÉCISIONS ───────────
        $eval1 = Evaluation::firstOrCreate(
            ['paroisse_configuration_id' => $paroisse->id, 'titre' => 'Interrogation T1 - Dieu Créateur'],
            [
                'annee_catechese_id'    => $annee->id,
                'module_trimestriel_id' => $mod1?->id,
                'classe_id'             => $primaryClass?->id,
                'type_eval'             => 'interrogation',
                'date_evaluation'       => '2026-10-25',
                'note_max'              => 20.0,
                'coefficient'           => 1.0,
                'statut'                => 'actif',
            ]
        );

        $eval2 = Evaluation::firstOrCreate(
            ['paroisse_configuration_id' => $paroisse->id, 'titre' => 'Devoir de Synthèse Trimestre 1'],
            [
                'annee_catechese_id'    => $annee->id,
                'module_trimestriel_id' => $mod1?->id,
                'classe_id'             => $primaryClass?->id,
                'type_eval'             => 'composition',
                'date_evaluation'       => '2026-12-06',
                'note_max'              => 20.0,
                'coefficient'           => 2.0,
                'statut'                => 'actif',
            ]
        );

        $notesSample = [18.0, 16.5, 14.0, 15.5, 12.0, 17.0];
        foreach ($catechumenes as $idx => $cat) {
            $val = $notesSample[$idx % count($notesSample)];
            $insc = $inscriptions->get($idx);

            Note::firstOrCreate(
                ['paroisse_configuration_id' => $paroisse->id, 'evaluation_id' => $eval1->id, 'catechumene_id' => $cat->id],
                ['note_obtenue' => $val, 'appreciation' => 'Très bonne participation et écoute.']
            );

            Note::firstOrCreate(
                ['paroisse_configuration_id' => $paroisse->id, 'evaluation_id' => $eval2->id, 'catechumene_id' => $cat->id],
                ['note_obtenue' => $val - 1.0, 'appreciation' => 'Bon travail d\'ensemble.']
            );

            // Bulletin Trimestriel
            if ($mod1 && $insc) {
                BulletinTrimestriel::firstOrCreate(
                    [
                        'paroisse_configuration_id' => $paroisse->id,
                        'inscription_annuelle_id'   => $insc->id,
                        'module_trimestriel_id'     => $mod1->id,
                    ],
                    [
                        'moyenne_trimestrielle'    => $val - 0.5,
                        'rang'                     => $idx + 1,
                        'assiduite_total_absences' => ($idx === 4) ? 1 : 0,
                        'appreciation_generale'    => 'Trimestre très satisfaisant. Félicitations.',
                        'statut'                   => 'valide',
                    ]
                );

                // Décision fin d'année
                DecisionFinAnnee::firstOrCreate(
                    [
                        'paroisse_configuration_id' => $paroisse->id,
                        'inscription_annuelle_id'   => $insc->id,
                    ],
                    [
                        'decision'         => 'admis',
                        'moyenne_annuelle' => $val - 0.5,
                        'mention'          => 'Bien',
                        'sacrement_recu'   => true,
                        'date_decision'    => '2027-06-25',
                        'observations'     => 'Admis au niveau supérieur avec les félicitations.',
                    ]
                );
            }
        }

        // ── 10. FINANCES : TARIFS, PAIEMENTS, CAISSE, VERSEMENTS ──
        $tarif = Tarif::first();
        if ($tarif && $catechumenes->isNotEmpty()) {
            foreach ($catechumenes->take(4) as $idx => $cat) {
                $recu = sprintf('REC-2026-%04d', $idx + 1);
                $insc = $inscriptions->get($idx);

                $paiement = Paiement::firstOrCreate(
                    ['paroisse_configuration_id' => $paroisse->id, 'numero_recu' => $recu],
                    [
                        'annee_catechese_id'        => $annee->id,
                        'inscription_annuelle_id'   => $insc?->id,
                        'catechumene_id'            => $cat->id,
                        'montant_total'             => 25000.00,
                        'mode_paiement'             => ($idx % 2 === 0) ? 'especes' : 'mobile_money',
                        'reference_transaction'     => 'WAV-' . Str::upper(Str::random(8)),
                        'date_paiement'             => '2026-09-10',
                        'statut'                    => 'valide',
                        'notes'                     => 'Paiement intégral validé au secrétariat.',
                    ]
                );

                LignePaiement::firstOrCreate(
                    ['paroisse_configuration_id' => $paroisse->id, 'paiement_id' => $paiement->id, 'tarif_id' => $tarif->id],
                    [
                        'designation' => $tarif->intitule,
                        'montant'     => 25000.00,
                        'quantite'    => 1,
                        'sous_total'  => 25000.00,
                    ]
                );

                OperationPaiement::firstOrCreate(
                    ['paroisse_configuration_id' => $paroisse->id, 'reference' => 'OP-' . $recu],
                    [
                        'annee_catechese_id' => $annee->id,
                        'catechumene_id'     => $cat->id,
                        'tarif_id'           => $tarif->id,
                        'libelle'            => 'Inscription Catéchèse 2026-2027 - ' . $cat->nom . ' ' . $cat->prenoms,
                        'montant'            => 25000.00,
                        'montant_paye'       => 25000.00,
                        'echeance'           => '2026-10-15',
                        'statut'             => 'paye',
                    ]
                );
            }
        }

        // Écritures de Caisse
        $caisseEntries = [
            ['libelle' => 'Encaissement des inscriptions - Semaine 1', 'type' => 'entree', 'cat' => 'inscription', 'montant' => 500000.00, 'date' => '2026-09-15'],
            ['libelle' => 'Achat de manuels et fournitures de catéchèse', 'type' => 'sortie', 'cat' => 'depense_fournitures', 'montant' => 120000.00, 'date' => '2026-09-20'],
            ['libelle' => 'Versement des recettes hebdomadaires au Curé', 'type' => 'sortie', 'cat' => 'remboursement', 'montant' => 300000.00, 'date' => '2026-09-25'],
        ];

        foreach ($caisseEntries as $ce) {
            CaisseParoissiale::firstOrCreate(
                ['paroisse_configuration_id' => $paroisse->id, 'libelle' => $ce['libelle']],
                [
                    'annee_catechese_id' => $annee->id,
                    'type_mouvement'     => $ce['type'],
                    'categorie'          => $ce['cat'],
                    'montant'            => $ce['montant'],
                    'date_mouvement'     => $ce['date'],
                ]
            );
        }

        // Versement de caisse
        Versement::firstOrCreate(
            ['reference' => 'VERS-2026-001'],
            [
                'paroisse_configuration_id' => $paroisse->id,
                'annee_catechese_id'        => $annee->id,
                'periode_concernee'         => 'Septembre 2026',
                'montant_verse'             => 300000.00,
                'mode_remise'               => 'especes',
                'effectue_par'              => 'Charles BADO (Comptable)',
                'statut'                    => 'valide',
            ]
        );

        // ── 11. COMMUNICATION, NOTIFICATIONS & AUDIT ──────────────
        Annonce::firstOrCreate(
            ['paroisse_configuration_id' => $paroisse->id, 'titre' => 'Réunion solennelle des parents de catéchumènes'],
            [
                'annee_catechese_id' => $annee->id,
                'contenu'            => 'Chers parents, la première réunion pastorale d\'orientation se tiendra ce samedi à 10h00 en la Grande Salle.',
                'cible'              => 'parents',
                'date_publication'   => '2026-09-18',
                'statut'             => 'publiee',
            ]
        );

        NotificationLog::firstOrCreate(
            ['paroisse_configuration_id' => $paroisse->id, 'destinataire' => '+225 0102030405'],
            [
                'canal'        => 'sms',
                'sujet'        => 'Confirmation de reçu',
                'message'      => 'Catheo St-Paul: Le reçu REC-2026-0001 a été validé. Bienvenue à la catéchèse 2026-2027.',
                'statut_envoi' => 'envoye',
                'date_envoi'   => '2026-09-10 10:35:00',
            ]
        );

        $adminUser = User::where('email', 'admin.stpaul@catheo.ci')->first();
        if ($adminUser) {
            AuditLog::firstOrCreate(
                ['paroisse_configuration_id' => $paroisse->id, 'entite_type' => 'AnneeCatechese'],
                [
                    'user_id'           => $adminUser->id,
                    'action'            => 'create',
                    'entite_id'         => $annee->id,
                    'anciennes_valeurs' => null,
                    'nouvelles_valeurs' => ['libelle' => '2026-2027', 'statut' => 'active'],
                    'ip_address'        => '127.0.0.1',
                    'user_agent'        => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/128.0',
                ]
            );
        }

        // ── 12. SAUVEGARDES ───────────────────────────────────────
        Sauvegarde::firstOrCreate(
            ['nom_fichier' => 'catheo_demo_backup_2026_08_16.sql.gz'],
            [
                'paroisse_configuration_id' => $paroisse->id,
                'chemin_fichier'            => 'backups/catheo_demo_backup_2026_08_16.sql.gz',
                'taille_octets'             => 1458200,
                'cree_par'                  => 'Père Administrateur Saint-Paul',
                'type'                      => 'manuel',
                'statut'                    => 'termine',
            ]
        );

        $this->command->info('SUCCÈS: Données de démonstration Catheo 100% cohérentes générées !');
    }
}
