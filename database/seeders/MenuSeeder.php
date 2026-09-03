<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MenuSeeder extends Seeder
{
    /**
     * Insère les 13 menus officiels et leurs sous-menus dans la table menus.
     */
    public function run(): void
    {
        $menusStructure = [
            [
                'ordre'      => 1,
                'libelle'    => 'Tableau de bord',
                'icon'       => 'bi bi-speedometer2',
                'path'       => '/dashboard',
                'code'       => '100',
                'permission' => '1,2,3,4',
                'reference'  => 'dashboard',
                'is_active'  => true,
                'sousMenus'  => [],
            ],
            [
                'ordre'      => 2,
                'libelle'    => 'Catéchumènes',
                'icon'       => 'bi bi-people',
                'path'       => '#',
                'code'       => '200',
                'permission' => '1,2,3,4',
                'reference'  => 'main_catechumenes',
                'is_active'  => true,
                'sousMenus'  => [
                    ['ordre' => 1, 'libelle' => 'Campagnes',               'icon' => 'bi bi-megaphone',          'path' => '/campagnes-preinscriptions', 'code' => '200', 'permission' => null, 'reference' => 'campagnes_preinscriptions', 'is_active' => true],
                    ['ordre' => 2, 'libelle' => 'Préinscriptions',          'icon' => 'bi bi-person-plus',        'path' => '/preinscriptions',            'code' => '200', 'permission' => null, 'reference' => 'preinscriptions',            'is_active' => true],
                    ['ordre' => 3, 'libelle' => 'Inscriptions',            'icon' => 'bi bi-journal-check',      'path' => '/inscriptions-annuelles',      'code' => '200', 'permission' => null, 'reference' => 'inscriptions_annuelles',      'is_active' => true],
                    ['ordre' => 4, 'libelle' => 'Affectations',            'icon' => 'bi bi-diagram-3',          'path' => '/affectations',                'code' => '200', 'permission' => null, 'reference' => 'affectations',                'is_active' => true],
                    ['ordre' => 5, 'libelle' => 'Mutations',               'icon' => 'bi bi-arrow-left-right',   'path' => '/mutations',                   'code' => '200', 'permission' => null, 'reference' => 'mutations',                   'is_active' => true],
                    ['ordre' => 6, 'libelle' => 'Liste des catéchumènes',  'icon' => 'bi bi-person-lines-fill',  'path' => '/catechumenes',                'code' => '200', 'permission' => null, 'reference' => 'catechumenes',                'is_active' => true],
                ],
            ],
            [
                'ordre'      => 3,
                'libelle'    => 'Gestion présences',
                'icon'       => 'bi bi-calendar-check',
                'path'       => '#',
                'code'       => '300',
                'permission' => '1,2,3,4',
                'reference'  => 'main_presences',
                'is_active'  => true,
                'sousMenus'  => [
                    ['ordre' => 1, 'libelle' => 'Séances', 'icon' => 'bi bi-calendar-event', 'path' => '/seances', 'code' => '300', 'permission' => null, 'reference' => 'seances', 'is_active' => true],
                ],
            ],
            [
                'ordre'      => 4,
                'libelle'    => 'Évaluations',
                'icon'       => 'bi bi-clipboard-check',
                'path'       => '#',
                'code'       => '400',
                'permission' => '1,2,3,4',
                'reference'  => 'main_evaluations',
                'is_active'  => true,
                'sousMenus'  => [
                    ['ordre' => 1, 'libelle' => 'Évaluations',    'icon' => 'bi bi-clipboard2-check',        'path' => '/evaluations',    'code' => '400', 'permission' => null, 'reference' => 'evaluations',    'is_active' => true],
                    ['ordre' => 2, 'libelle' => 'Notes',          'icon' => 'bi bi-pencil-square',           'path' => '/notes',          'code' => '400', 'permission' => null, 'reference' => 'notes',          'is_active' => true],
                    ['ordre' => 3, 'libelle' => 'Bilans annuels', 'icon' => 'bi bi-file-earmark-text',       'path' => '/bilans-annuels', 'code' => '400', 'permission' => null, 'reference' => 'bilans_annuels', 'is_active' => true],
                    ['ordre' => 4, 'libelle' => 'Bulletins',      'icon' => 'bi bi-file-earmark-bar-graph',  'path' => '/bulletins',      'code' => '400', 'permission' => null, 'reference' => 'bulletins',      'is_active' => true],
                ],
            ],
            [
                'ordre'      => 5,
                'libelle'    => 'Sacrements',
                'icon'       => 'bi bi-droplet-half',
                'path'       => '#',
                'code'       => '500',
                'permission' => '1,2,3,4',
                'reference'  => 'main_sacrements',
                'is_active'  => true,
                'sousMenus'  => [
                    ['ordre' => 1, 'libelle' => 'Baptême',               'icon' => 'bi bi-droplet',             'path' => '/sacrements/bapteme',               'code' => '500', 'permission' => null, 'reference' => 'bapteme',               'is_active' => true],
                    ['ordre' => 2, 'libelle' => 'Première Communion',    'icon' => 'bi bi-cup-hot',             'path' => '/sacrements/premiere-communion',    'code' => '500', 'permission' => null, 'reference' => 'premiere_communion',    'is_active' => true],
                    ['ordre' => 3, 'libelle' => 'Confirmation',          'icon' => 'bi bi-patch-check',         'path' => '/sacrements/confirmation',          'code' => '500', 'permission' => null, 'reference' => 'confirmation',          'is_active' => true],
                    ['ordre' => 4, 'libelle' => 'Exceptions pastorales', 'icon' => 'bi bi-exclamation-diamond', 'path' => '/sacrements/exceptions-pastorales', 'code' => '500', 'permission' => null, 'reference' => 'exceptions_pastorales', 'is_active' => true],
                ],
            ],
            [
                'ordre'      => 6,
                'libelle'    => 'Finances',
                'icon'       => 'bi bi-cash-stack',
                'path'       => '#',
                'code'       => '600',
                'permission' => '1,2,3,4',
                'reference'  => 'main_finances',
                'is_active'  => true,
                'sousMenus'  => [
                    ['ordre' => 1, 'libelle' => 'Tarification',           'icon' => 'bi bi-tags',      'path' => '/tarifications',         'code' => '600', 'permission' => null, 'reference' => 'tarifications',         'is_active' => true],
                    ['ordre' => 2, 'libelle' => 'Opérations financières', 'icon' => 'bi bi-arrow-repeat', 'path' => '/operations-financieres', 'code' => '600', 'permission' => null, 'reference' => 'operations_financieres', 'is_active' => true],
                    ['ordre' => 3, 'libelle' => 'Caisse',                 'icon' => 'bi bi-safe',      'path' => '/caisse',                'code' => '600', 'permission' => null, 'reference' => 'caisse',                 'is_active' => true],
                    ['ordre' => 4, 'libelle' => 'Versements',             'icon' => 'bi bi-cash-coin', 'path' => '/versements',            'code' => '600', 'permission' => null, 'reference' => 'versements',             'is_active' => true],
                ],
            ],
            [
                'ordre'      => 7,
                'libelle'    => 'Communication',
                'icon'       => 'bi bi-chat-dots',
                'path'       => '#',
                'code'       => '700',
                'permission' => '1,2,3,4',
                'reference'  => 'main_communication',
                'is_active'  => true,
                'sousMenus'  => [
                    ['ordre' => 1, 'libelle' => 'SMS',           'icon' => 'bi bi-phone', 'path' => '/sms',           'code' => '700', 'permission' => null, 'reference' => 'sms',           'is_active' => true],
                    ['ordre' => 2, 'libelle' => 'Notifications', 'icon' => 'bi bi-bell',  'path' => '/notifications', 'code' => '700', 'permission' => null, 'reference' => 'notifications', 'is_active' => true],
                ],
            ],
            [
                'ordre'      => 8,
                'libelle'    => 'Impressions',
                'icon'       => 'bi bi-printer',
                'path'       => '#',
                'code'       => '800',
                'permission' => '1,2,3,4',
                'reference'  => 'main_impressions',
                'is_active'  => true,
                'sousMenus'  => [
                    ['ordre' => 1, 'libelle' => 'Fiche de notes',      'icon' => 'bi bi-file-earmark-text',       'path' => '/impressions/fiche-notes',                     'code' => '800', 'permission' => null, 'reference' => 'fiche_notes',                      'is_active' => true],
                    ['ordre' => 2, 'libelle' => 'Liste de présence',   'icon' => 'bi bi-list-check',              'path' => '/impressions/liste-presence',                  'code' => '800', 'permission' => null, 'reference' => 'liste_presence',                   'is_active' => true],
                    ['ordre' => 3, 'libelle' => 'Fiche bilan',         'icon' => 'bi bi-file-earmark-bar-graph',  'path' => '/impressions/bilan-annuel',                    'code' => '800', 'permission' => null, 'reference' => 'fiche_bilan_annuel',              'is_active' => true],
                    ['ordre' => 4, 'libelle' => 'Fiche sacramentel',   'icon' => 'bi bi-clipboard-pulse',         'path' => '/impressions/suivi-sacramentel',               'code' => '800', 'permission' => null, 'reference' => 'fiche_suivi_sacramentel',          'is_active' => true],
                    ['ordre' => 5, 'libelle' => 'Fiche Baptême',       'icon' => 'bi bi-file-earmark-person',     'path' => '/impressions/renseignements-bapteme',          'code' => '800', 'permission' => null, 'reference' => 'renseignements_bapteme',          'is_active' => true],
                    ['ordre' => 6, 'libelle' => 'Fiche Communion',     'icon' => 'bi bi-file-earmark-person',     'path' => '/impressions/renseignements-premiere-communion','code' => '800', 'permission' => null, 'reference' => 'renseignements_premiere_communion','is_active' => true],
                    ['ordre' => 7, 'libelle' => 'Fiche Confirmation',  'icon' => 'bi bi-file-earmark-person',     'path' => '/impressions/renseignements-confirmation',     'code' => '800', 'permission' => null, 'reference' => 'renseignements_confirmation',     'is_active' => true],
                ],
            ],
            [
                'ordre'      => 9,
                'libelle'    => 'Documents',
                'icon'       => 'bi bi-file-earmark-richtext',
                'path'       => '#',
                'code'       => '900',
                'permission' => '1,2,3,4',
                'reference'  => 'main_documents_officiels',
                'is_active'  => true,
                'sousMenus'  => [
                    ['ordre' => 1, 'libelle' => 'Modèles documents',    'icon' => 'bi bi-file-earmark',      'path' => '/modeles-documents',    'code' => '900', 'permission' => null, 'reference' => 'modeles_documents',    'is_active' => true],
                    ['ordre' => 2, 'libelle' => 'Génération documents', 'icon' => 'bi bi-file-earmark-plus', 'path' => '/generation-documents', 'code' => '900', 'permission' => null, 'reference' => 'generation_documents', 'is_active' => true],
                ],
            ],
            [
                'ordre'      => 10,
                'libelle'    => 'Rapports',
                'icon'       => 'bi bi-file-earmark-bar-graph',
                'path'       => '/rapports',
                'code'       => '1000',
                'permission' => '1,2,3,4',
                'reference'  => 'main_rapports_statistiques',
                'is_active'  => true,
                'sousMenus'  => [],
            ],
            [
                'ordre'      => 11,
                'libelle'    => 'Organisation',
                'icon'       => 'bi bi-building',
                'path'       => '#',
                'code'       => '1100',
                'permission' => '1,2,3,4',
                'reference'  => 'main_organisation',
                'is_active'  => true,
                'sousMenus'  => [
                    ['ordre' => 1,  'libelle' => 'Années pastorales',        'icon' => 'bi bi-calendar-range',   'path' => '/annees-pastorales',        'code' => '1100', 'permission' => null, 'reference' => 'annees_pastorales',        'is_active' => true],
                    ['ordre' => 2,  'libelle' => 'Sections',                 'icon' => 'bi bi-diagram-2',        'path' => '/sections',                 'code' => '1100', 'permission' => null, 'reference' => 'sections',                 'is_active' => true],
                    ['ordre' => 3,  'libelle' => 'Niveaux',                  'icon' => 'bi bi-layers',           'path' => '/niveaux',                  'code' => '1100', 'permission' => null, 'reference' => 'niveaux',                  'is_active' => true],
                    ['ordre' => 4,  'libelle' => 'Classes',                  'icon' => 'bi bi-door-open',        'path' => '/classes',                  'code' => '1100', 'permission' => null, 'reference' => 'classes',                  'is_active' => true],
                    ['ordre' => 5,  'libelle' => 'Animateurs',               'icon' => 'bi bi-person-workspace', 'path' => '/animateurs',               'code' => '1100', 'permission' => null, 'reference' => 'animateurs',               'is_active' => true],
                    ['ordre' => 6,  'libelle' => 'Affectation animateurs',   'icon' => 'bi bi-person-check',     'path' => '/affectations-animateurs',  'code' => '1100', 'permission' => null, 'reference' => 'affectations_animateurs',   'is_active' => true],
                    ['ordre' => 7,  'libelle' => 'CEB',                      'icon' => 'bi bi-house-heart',      'path' => '/cebs',                     'code' => '1100', 'permission' => null, 'reference' => 'cebs',                      'is_active' => true],
                    ['ordre' => 8,  'libelle' => 'Mouvements',               'icon' => 'bi bi-people-fill',      'path' => '/mouvements',               'code' => '1100', 'permission' => null, 'reference' => 'mouvements',               'is_active' => true],
                    ['ordre' => 9,  'libelle' => 'Calendrier',               'icon' => 'bi bi-calendar3',        'path' => '/calendrier',               'code' => '1100', 'permission' => null, 'reference' => 'calendrier',               'is_active' => true],
                    ['ordre' => 10, 'libelle' => 'Modules trimestriels',     'icon' => 'bi bi-calendar3-range',  'path' => '/modules-trimestriels',     'code' => '1100', 'permission' => null, 'reference' => 'modules_trimestriels',     'is_active' => true],
                ],
            ],
            [
                'ordre'      => 12,
                'libelle'    => 'Utilisateurs & Sécurité',
                'icon'       => 'bi bi-shield-lock',
                'path'       => '#',
                'code'       => '1300',
                'permission' => '1,2,3,4',
                'reference'  => 'main_utilisateurs_securite',
                'is_active'  => true,
                'sousMenus'  => [
                    ['ordre' => 1, 'libelle' => 'Utilisateurs', 'icon' => 'bi bi-person-fill',        'path' => '/utilisateurs', 'code' => '1300', 'permission' => null, 'reference' => 'utilisateurs', 'is_active' => true],
                    ['ordre' => 2, 'libelle' => 'Profils',      'icon' => 'bi bi-shield-fill-check',  'path' => '/profils',      'code' => '1300', 'permission' => null, 'reference' => 'profils',      'is_active' => true],
                ],
            ],
            [
                'ordre'      => 13,
                'libelle'    => 'Paramètres',
                'icon'       => 'bi bi-gear-wide-connected',
                'path'       => '#',
                'code'       => '1400',
                'permission' => '1,2,3,4',
                'reference'  => 'main_parametres',
                'is_active'  => true,
                'sousMenus'  => [
                    ['ordre' => 1, 'libelle' => 'Configuration', 'icon' => 'bi bi-building-gear',  'path' => '/parametres/configuration', 'code' => '1400', 'permission' => null, 'reference' => 'configuration_paroisse', 'is_active' => true],
                    ['ordre' => 2, 'libelle' => 'Sauvegardes',    'icon' => 'bi bi-database-down',   'path' => '/parametres/sauvegardes',    'code' => '1400', 'permission' => null, 'reference' => 'sauvegardes',            'is_active' => true],
                ],
            ],
        ];

        $existingReferences = [];

        foreach ($menusStructure as $menuData) {
            $sousMenus = $menuData['sousMenus'] ?? [];
            unset($menuData['sousMenus']);

            $existingReferences[] = $menuData['reference'];

            $menu = Menu::updateOrCreate(
                ['reference' => $menuData['reference']],
                $menuData
            );

            foreach ($sousMenus as $smData) {
                $existingReferences[] = $smData['reference'];
                $smData['parent_id'] = $menu->id;

                Menu::updateOrCreate(
                    ['reference' => $smData['reference']],
                    $smData
                );
            }
        }

        // Nettoyage des anciens menus obsolètes qui ne font plus partie du référentiel
        Menu::whereNotIn('reference', $existingReferences)->forceDelete();
    }
}
