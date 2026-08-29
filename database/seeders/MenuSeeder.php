<?php

namespace Database\Seeders;

use App\Models\Menu;
use Illuminate\Database\Seeder;

class MenuSeeder extends Seeder
{
    /**
     * Insère les 13 menus officiels et leurs sous-menus dans la table menus.
     */
    public function run(): void
    {
        $menusStructure = [
            [
                'libelle'   => 'Tableau de bord',
                'icon'      => 'bi bi-speedometer2',
                'path'      => '/dashboard',
                'reference' => 'dashboard',
                'ordre'     => 1,
                'sousMenus' => [],
            ],
            [
                'libelle'   => 'Catéchumènes',
                'icon'      => 'bi bi-people',
                'path'      => '#',
                'reference' => 'main_catechumenes',
                'ordre'     => 2,
                'sousMenus' => [
                    ['libelle' => 'Campagnes de préinscription', 'path' => '/campagnes-preinscriptions', 'reference' => 'campagnes_preinscriptions', 'icon' => 'bi bi-calendar-event', 'ordre' => 1],
                    ['libelle' => 'Préinscriptions',            'path' => '/preinscriptions',            'reference' => 'preinscriptions',            'icon' => 'bi bi-person-plus',     'ordre' => 2],
                    ['libelle' => 'Inscriptions annuelles',      'path' => '/inscriptions-annuelles',      'reference' => 'inscriptions_annuelles',      'icon' => 'bi bi-card-checklist',  'ordre' => 3],
                    ['libelle' => 'Affectations',                'path' => '/affectations',                'reference' => 'affectations_catechumenes',  'icon' => 'bi bi-arrow-left-right', 'ordre' => 4],
                    ['libelle' => 'Mutations',                   'path' => '/mutations',                   'reference' => 'mutations_catechumenes',     'icon' => 'bi bi-send',             'ordre' => 5],
                    ['libelle' => 'Liste des catéchumènes',      'path' => '/catechumenes',               'reference' => 'liste_catechumenes',         'icon' => 'bi bi-person-lines-fill','ordre' => 6],
                ],
            ],
            [
                'libelle'   => 'Gestion des présences',
                'icon'      => 'bi bi-calendar-check',
                'path'      => '#',
                'reference' => 'main_presences',
                'ordre'     => 3,
                'sousMenus' => [
                    ['libelle' => 'Séances', 'path' => '/seances', 'reference' => 'seances', 'icon' => 'bi bi-calendar3', 'ordre' => 1],
                ],
            ],
            [
                'libelle'   => 'Évaluations',
                'icon'      => 'bi bi-journal-check',
                'path'      => '#',
                'reference' => 'main_evaluations',
                'ordre'     => 4,
                'sousMenus' => [
                    ['libelle' => 'Évaluations',    'path' => '/evaluations',     'reference' => 'evaluations',     'icon' => 'bi bi-pencil-square', 'ordre' => 1],
                    ['libelle' => 'Notes',          'path' => '/notes',           'reference' => 'notes',           'icon' => 'bi bi-123',           'ordre' => 2],
                    ['libelle' => 'Bilans annuels', 'path' => '/bilans-annuels',  'reference' => 'bilans_annuels',  'icon' => 'bi bi-trophy',        'ordre' => 3],
                    ['libelle' => 'Bulletins',      'path' => '/bulletins',       'reference' => 'bulletins',       'icon' => 'bi bi-file-earmark-spreadsheet', 'ordre' => 4],
                ],
            ],
            [
                'libelle'   => 'Sacrements',
                'icon'      => 'bi bi-award',
                'path'      => '#',
                'reference' => 'main_sacrements',
                'ordre'     => 5,
                'sousMenus' => [
                    ['libelle' => 'Baptême',               'path' => '/sacrements/bapteme',            'reference' => 'sacrement_bapteme',            'icon' => 'bi bi-droplet',     'ordre' => 1],
                    ['libelle' => 'Première Communion',    'path' => '/sacrements/premiere-communion', 'reference' => 'sacrement_premiere_communion', 'icon' => 'bi bi-brightness-high', 'ordre' => 2],
                    ['libelle' => 'Confirmation',          'path' => '/sacrements/confirmation',       'reference' => 'sacrement_confirmation',       'icon' => 'bi bi-fire',        'ordre' => 3],
                    ['libelle' => 'Exceptions pastorales', 'path' => '/sacrements/exceptions',         'reference' => 'sacrement_exceptions',         'icon' => 'bi bi-shield-exclamation', 'ordre' => 4],
                ],
            ],
            [
                'libelle'   => 'Finances',
                'icon'      => 'bi bi-cash-stack',
                'path'      => '#',
                'reference' => 'main_finances',
                'ordre'     => 6,
                'sousMenus' => [
                    ['libelle' => 'Tarification',           'path' => '/tarifs',               'reference' => 'tarification',           'icon' => 'bi bi-tags',          'ordre' => 1],
                    ['libelle' => 'Opérations financières', 'path' => '/operations-paiements', 'reference' => 'operations_financieres', 'icon' => 'bi bi-wallet2',       'ordre' => 2],
                    ['libelle' => 'Caisse',                 'path' => '/caisse-paroissiale',   'reference' => 'caisse_paroissiale',     'icon' => 'bi bi-safe',          'ordre' => 3],
                    ['libelle' => 'Versements',             'path' => '/versements',           'reference' => 'versements',             'icon' => 'bi bi-bank',          'ordre' => 4],
                ],
            ],
            [
                'libelle'   => 'Communication',
                'icon'      => 'bi bi-chat-dots',
                'path'      => '#',
                'reference' => 'main_communication',
                'ordre'     => 7,
                'sousMenus' => [
                    ['libelle' => 'SMS',           'path' => '/communication/sms', 'reference' => 'communication_sms', 'icon' => 'bi bi-phone',     'ordre' => 1],
                    ['libelle' => 'Notifications', 'path' => '/notifications-log', 'reference' => 'notifications_log', 'icon' => 'bi bi-bell',      'ordre' => 2],
                ],
            ],
            [
                'libelle'   => 'Impressions',
                'icon'      => 'bi bi-printer',
                'path'      => '#',
                'reference' => 'main_impressions',
                'ordre'     => 8,
                'sousMenus' => [
                    ['libelle' => 'Fiche de notes',                          'path' => '/impressions/fiche-notes',                'reference' => 'imp_fiche_notes',                'icon' => 'bi bi-file-earmark-ruled', 'ordre' => 1],
                    ['libelle' => 'Liste de présence',                       'path' => '/impressions/liste-presence',             'reference' => 'imp_liste_presence',             'icon' => 'bi bi-file-earmark-check', 'ordre' => 2],
                    ['libelle' => 'Fiche de bilan annuel',                   'path' => '/impressions/fiche-bilan-annuel',         'reference' => 'imp_fiche_bilan_annuel',         'icon' => 'bi bi-file-earmark-text',  'ordre' => 3],
                    ['libelle' => 'Fiche de suivi sacramentel',              'path' => '/impressions/suivi-sacramental',          'reference' => 'imp_suivi_sacramental',          'icon' => 'bi bi-journal-bookmark',   'ordre' => 4],
                    ['libelle' => 'Fiche de renseignements Baptême',         'path' => '/impressions/fiche-bapteme',              'reference' => 'imp_fiche_bapteme',              'icon' => 'bi bi-file-person',        'ordre' => 5],
                    ['libelle' => 'Fiche de renseignements 1ère Communion',  'path' => '/impressions/fiche-premiere-communion',   'reference' => 'imp_fiche_premiere_communion',   'icon' => 'bi bi-file-person',        'ordre' => 6],
                    ['libelle' => 'Fiche de renseignements Confirmation',    'path' => '/impressions/fiche-confirmation',         'reference' => 'imp_fiche_confirmation',         'icon' => 'bi bi-file-person',        'ordre' => 7],
                ],
            ],
            [
                'libelle'   => 'Documents officiels',
                'icon'      => 'bi bi-file-earmark-text',
                'path'      => '#',
                'reference' => 'main_documents',
                'ordre'     => 9,
                'sousMenus' => [
                    ['libelle' => 'Modèles de documents',    'path' => '/documents/modeles',    'reference' => 'modeles_documents',    'icon' => 'bi bi-layout-text-window', 'ordre' => 1],
                    ['libelle' => 'Génération de documents', 'path' => '/documents/generation', 'reference' => 'generation_documents', 'icon' => 'bi bi-file-earmark-arrow-down', 'ordre' => 2],
                ],
            ],
            [
                'libelle'   => 'Rapports & Statistiques',
                'icon'      => 'bi bi-bar-chart-line',
                'path'      => '#',
                'reference' => 'main_rapports',
                'ordre'     => 10,
                'sousMenus' => [
                    ['libelle' => 'Statistiques', 'path' => '/statistiques', 'reference' => 'statistiques', 'icon' => 'bi bi-pie-chart', 'ordre' => 1],
                    ['libelle' => 'Rapports',     'path' => '/rapports',     'reference' => 'rapports',     'icon' => 'bi bi-graph-up',  'ordre' => 2],
                ],
            ],
            [
                'libelle'   => 'Organisation',
                'icon'      => 'bi bi-diagram-3',
                'path'      => '#',
                'reference' => 'main_organisation',
                'ordre'     => 11,
                'sousMenus' => [
                    ['libelle' => 'Années pastorales',           'path' => '/annee-catecheses',        'reference' => 'annee_catecheses',        'icon' => 'bi bi-calendar-range', 'ordre' => 1],
                    ['libelle' => 'Sections',                    'path' => '/sections',                'reference' => 'sections',                'icon' => 'bi bi-folder2-open',   'ordre' => 2],
                    ['libelle' => 'Niveaux',                     'path' => '/niveaux',                 'reference' => 'niveaux',                 'icon' => 'bi bi-layers',         'ordre' => 3],
                    ['libelle' => 'Classes',                     'path' => '/classes',                 'reference' => 'classes',                 'icon' => 'bi bi-easel',          'ordre' => 4],
                    ['libelle' => 'Animateurs',                  'path' => '/animateurs',              'reference' => 'animateurs',              'icon' => 'bi bi-person-badge',   'ordre' => 5],
                    ['libelle' => 'Affectation des animateurs',  'path' => '/affectations-animateurs', 'reference' => 'affectations_animateurs', 'icon' => 'bi bi-person-check',   'ordre' => 6],
                    ['libelle' => 'CEB',                         'path' => '/cebs',                    'reference' => 'cebs',                    'icon' => 'bi bi-house-door',     'ordre' => 7],
                    ['libelle' => 'Mouvements',                  'path' => '/mouvements',              'reference' => 'mouvements',              'icon' => 'bi bi-flag',           'ordre' => 8],
                    ['libelle' => 'Calendrier',                  'path' => '/activites',               'reference' => 'calendrier_activites',    'icon' => 'bi bi-calendar4-week', 'ordre' => 9],
                    ['libelle' => 'Modules trimestriels',        'path' => '/modules-trimestriels',    'reference' => 'modules_trimestriels',    'icon' => 'bi bi-grid-3x3',       'ordre' => 10],
                ],
            ],
            [
                'libelle'   => 'Utilisateurs & Sécurité',
                'icon'      => 'bi bi-shield-lock',
                'path'      => '#',
                'reference' => 'main_users_security',
                'ordre'     => 12,
                'sousMenus' => [
                    ['libelle' => 'Utilisateurs', 'path' => '/users',   'reference' => 'utilisateurs', 'icon' => 'bi bi-people-fill', 'ordre' => 1],
                    ['libelle' => 'Profils',      'path' => '/profils', 'reference' => 'profils',      'icon' => 'bi bi-person-gear', 'ordre' => 2],
                ],
            ],
            [
                'libelle'   => 'Paramètres',
                'icon'      => 'bi bi-gear',
                'path'      => '#',
                'reference' => 'main_settings',
                'ordre'     => 13,
                'sousMenus' => [
                    ['libelle' => 'Configuration de la paroisse', 'path' => '/paroisse-configuration', 'reference' => 'paroisse_config',      'icon' => 'bi bi-building',   'ordre' => 1],
                    ['libelle' => 'Responsables de la paroisse',  'path' => '/responsables-paroisse',  'reference' => 'responsables_paroisse', 'icon' => 'bi bi-person-lines-fill', 'ordre' => 2],
                    ['libelle' => 'Apparence',                    'path' => '/apparence-configuration', 'reference' => 'apparence_config',     'icon' => 'bi bi-palette',    'ordre' => 3],
                    ['libelle' => 'Sauvegardes',                  'path' => '/sauvegardes',             'reference' => 'sauvegardes',           'icon' => 'bi bi-hdd-network','ordre' => 4],
                ],
            ],
        ];

        foreach ($menusStructure as $menuData) {
            $sousMenus = $menuData['sousMenus'] ?? [];
            unset($menuData['sousMenus']);

            $menu = Menu::updateOrCreate(
                ['reference' => $menuData['reference']],
                $menuData
            );

            foreach ($sousMenus as $smData) {
                $smData['parent_id'] = $menu->id;
                Menu::updateOrCreate(
                    ['reference' => $smData['reference']],
                    $smData
                );
            }
        }
    }
}
