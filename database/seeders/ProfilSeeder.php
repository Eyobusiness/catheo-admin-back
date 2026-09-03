<?php

namespace Database\Seeders;

use App\Models\Menu;
use App\Models\Profil;
use App\Models\ProfilMenuPermission;
use Illuminate\Database\Seeder;

class ProfilSeeder extends Seeder
{
    /**
     * Crée les profils et leur assigne leurs matrices de permissions fines.
     */
    public function run(): void
    {
        $allMenus = Menu::all()->keyBy('reference');

        // Définition des profils
        $profilsConfig = [
            [
                'code'        => 'SUPER_ADMIN',
                'nom'         => 'Super Administrateur',
                'description' => 'Accès complet et sans restriction à l\'ensemble de la plateforme Catheo.',
                'permissions' => ['*'],
                'is_system'   => true,
                'rules'       => 'all_full',
            ],
            [
                'code'        => 'ADMIN_PAROISSE',
                'nom'         => 'Administrateur Paroissial',
                'description' => 'Gestion globale de la paroisse : catéchèse, organisation, finances et utilisateurs.',
                'permissions' => [
                    'dashboard.view',
                    'users.manage',
                    'settings.manage',
                    'organisation.manage',
                    'catechumenes.manage',
                    'presences.manage',
                    'evaluations.manage',
                    'finances.manage',
                    'impressions.view',
                    'impressions.generate',
                ],
                'is_system'   => true,
                'rules'       => 'all_no_force',
            ],
            [
                'code'        => 'SECRETAIRE',
                'nom'         => 'Secrétaire Paroissiale',
                'description' => 'Gestion des inscriptions, registres de catéchumènes, attestations et impressions officielles.',
                'permissions' => [
                    'dashboard.view',
                    'catechumenes.view',
                    'catechumenes.create',
                    'catechumenes.edit',
                    'organisation.view',
                    'impressions.view',
                    'impressions.generate',
                ],
                'is_system'   => false,
                'rules'       => 'secretaire',
            ],
            [
                'code'        => 'RESPONSABLE_CATECHESE',
                'nom'         => 'Responsable de la Catéchèse',
                'description' => 'Supervision pastorale : calendrier, séances, pointage des présences, évaluations et bulletins.',
                'permissions' => [
                    'dashboard.view',
                    'organisation.view',
                    'organisation.create',
                    'organisation.edit',
                    'presences.manage',
                    'evaluations.view',
                    'evaluations.create',
                    'evaluations.edit',
                    'catechumenes.view',
                    'impressions.view',
                    'impressions.generate',
                ],
                'is_system'   => false,
                'rules'       => 'resp_catechese',
            ],
            [
                'code'        => 'ANIMATEUR',
                'nom'         => 'Animateur / Catéchiste',
                'description' => 'Accès opérationnel : émargement des présences et saisie des notes de ses classes.',
                'permissions' => [
                    'dashboard.view',
                    'organisation.view',
                    'presences.manage',
                    'evaluations.view',
                    'evaluations.edit',
                ],
                'is_system'   => false,
                'rules'       => 'animateur',
            ],
            [
                'code'        => 'COMPTABLE',
                'nom'         => 'Comptable Paroissial',
                'description' => 'Gestion financière complète : encaissements, caisse, grille tarifaire et versements.',
                'permissions' => [
                    'dashboard.view',
                    'finances.view',
                    'finances.create',
                    'finances.edit',
                    'finances.delete',
                    'catechumenes.view',
                ],
                'is_system'   => false,
                'rules'       => 'comptable',
            ],
            [
                'code'        => 'LECTEUR',
                'nom'         => 'Lecteur Uniquement',
                'description' => 'Consultation en lecture seule des données statistiques, listes et registres autorisés.',
                'permissions' => [
                    'dashboard.view',
                    'organisation.view',
                    'catechumenes.view',
                    'finances.view',
                ],
                'is_system'   => false,
                'rules'       => 'lecteur',
            ],
            [
                'code'        => 'CATECHISTE',
                'nom'         => 'Catéchiste Paroissial',
                'description' => 'Saisie des présences et évaluations des catéchumènes.',
                'permissions' => [
                    'dashboard.view',
                    'organisation.view',
                    'presences.manage',
                    'evaluations.manage',
                ],
                'is_system'   => true,
                'rules'       => 'animateur',
            ],
            [
                'code'        => 'PARENT',
                'nom'         => 'Parent / Tuteur',
                'description' => 'Espace parent : consultation du carnet, présences, notes et bulletins.',
                'permissions' => [
                    'dashboard.view',
                    'presences.view',
                    'evaluations.view',
                    'bulletins.view',
                    'finances.view',
                ],
                'is_system'   => true,
                'rules'       => 'lecteur',
            ],
        ];

        foreach ($profilsConfig as $pData) {
            $rules = $pData['rules'];
            unset($pData['rules']);

            $profil = Profil::updateOrCreate(
                ['code' => $pData['code']],
                $pData
            );

            // Création des permissions relationnelles pour chaque menu
            foreach ($allMenus as $menu) {
                $perms = $this->getPermissionsForRules($rules, $menu->reference);

                ProfilMenuPermission::updateOrCreate(
                    [
                        'profil_id' => $profil->id,
                        'menu_id'   => $menu->id,
                    ],
                    $perms
                );
            }
        }
    }

    /**
     * Détermine les droits CRUD + Restore + ForceDelete selon le profil et la référence du menu.
     */
    protected function getPermissionsForRules(string $rules, string $ref): array
    {
        $allFull = [
            'can_read'         => true,
            'can_create'       => true,
            'can_update'       => true,
            'can_delete'       => true,
            'can_restore'      => true,
            'can_force_delete' => true,
        ];

        $allNoForce = [
            'can_read'         => true,
            'can_create'       => true,
            'can_update'       => true,
            'can_delete'       => true,
            'can_restore'      => true,
            'can_force_delete' => false,
        ];

        $none = [
            'can_read'         => false,
            'can_create'       => false,
            'can_update'       => false,
            'can_delete'       => false,
            'can_restore'      => false,
            'can_force_delete' => false,
        ];

        if ($rules === 'all_full') {
            return $allFull;
        }

        if ($rules === 'all_no_force') {
            return $allNoForce;
        }

        if ($rules === 'secretaire') {
            // Secrétaire : Dashboard, Catéchumènes (full), Impressions (read/create), Sacrements (read/create/update), Organisation (read)
            if ($ref === 'dashboard') {
                return ['can_read' => true, 'can_create' => false, 'can_update' => false, 'can_delete' => false, 'can_restore' => false, 'can_force_delete' => false];
            }
            if (str_starts_with($ref, 'main_catechumenes') || in_array($ref, ['campagnes_preinscriptions', 'preinscriptions', 'inscriptions_annuelles', 'affectations', 'mutations', 'catechumenes'])) {
                return ['can_read' => true, 'can_create' => true, 'can_update' => true, 'can_delete' => true, 'can_restore' => true, 'can_force_delete' => false];
            }
            if (str_starts_with($ref, 'main_impressions') || in_array($ref, ['fiche_notes', 'liste_presence', 'fiche_bilan_annuel', 'fiche_suivi_sacramentel', 'renseignements_bapteme', 'renseignements_premiere_communion', 'renseignements_confirmation'])) {
                return ['can_read' => true, 'can_create' => true, 'can_update' => false, 'can_delete' => false, 'can_restore' => false, 'can_force_delete' => false];
            }
            if (str_starts_with($ref, 'main_sacrements') || in_array($ref, ['bapteme', 'premiere_communion', 'confirmation', 'exceptions_pastorales'])) {
                return ['can_read' => true, 'can_create' => true, 'can_update' => true, 'can_delete' => false, 'can_restore' => false, 'can_force_delete' => false];
            }
            if (str_starts_with($ref, 'main_documents') || in_array($ref, ['modeles_documents', 'generation_documents'])) {
                return ['can_read' => true, 'can_create' => true, 'can_update' => false, 'can_delete' => false, 'can_restore' => false, 'can_force_delete' => false];
            }
            if (str_starts_with($ref, 'main_organisation') || in_array($ref, ['annees_pastorales', 'sections', 'niveaux', 'classes'])) {
                return ['can_read' => true, 'can_create' => false, 'can_update' => false, 'can_delete' => false, 'can_restore' => false, 'can_force_delete' => false];
            }
            return $none;
        }

        if ($rules === 'resp_catechese') {
            // Resp Catéchèse : Dashboard, Organisation, Présences, Évaluations, Catéchumènes (read), Impressions
            if (in_array($ref, ['dashboard', 'main_presences', 'seances', 'main_evaluations', 'evaluations', 'notes', 'bilans_annuels', 'bulletins'])) {
                return ['can_read' => true, 'can_create' => true, 'can_update' => true, 'can_delete' => true, 'can_restore' => true, 'can_force_delete' => false];
            }
            if (str_starts_with($ref, 'main_organisation') || in_array($ref, ['annees_pastorales', 'sections', 'niveaux', 'classes', 'animateurs', 'affectations_animateurs', 'cebs', 'mouvements', 'calendrier', 'modules_trimestriels'])) {
                return ['can_read' => true, 'can_create' => true, 'can_update' => true, 'can_delete' => false, 'can_restore' => false, 'can_force_delete' => false];
            }
            if (str_starts_with($ref, 'main_catechumenes') || str_starts_with($ref, 'main_impressions') || str_starts_with($ref, 'main_sacrements') || in_array($ref, ['fiche_notes', 'liste_presence', 'fiche_bilan_annuel', 'fiche_suivi_sacramentel', 'renseignements_bapteme', 'renseignements_premiere_communion', 'renseignements_confirmation', 'bapteme', 'premiere_communion', 'confirmation', 'exceptions_pastorales', 'campagnes_preinscriptions', 'preinscriptions', 'inscriptions_annuelles', 'affectations', 'mutations', 'catechumenes'])) {
                return ['can_read' => true, 'can_create' => true, 'can_update' => false, 'can_delete' => false, 'can_restore' => false, 'can_force_delete' => false];
            }
            return $none;
        }

        if ($rules === 'animateur') {
            // Animateur : Dashboard, Séances (read/update), Notes (read/update), Organisation (read)
            if ($ref === 'dashboard') {
                return ['can_read' => true, 'can_create' => false, 'can_update' => false, 'can_delete' => false, 'can_restore' => false, 'can_force_delete' => false];
            }
            if (in_array($ref, ['main_presences', 'seances', 'main_evaluations', 'notes'])) {
                return ['can_read' => true, 'can_create' => true, 'can_update' => true, 'can_delete' => false, 'can_restore' => false, 'can_force_delete' => false];
            }
            if (in_array($ref, ['evaluations', 'bilans_annuels', 'bulletins', 'main_organisation', 'classes', 'calendrier', 'modules_trimestriels'])) {
                return ['can_read' => true, 'can_create' => false, 'can_update' => false, 'can_delete' => false, 'can_restore' => false, 'can_force_delete' => false];
            }
            return $none;
        }

        if ($rules === 'comptable') {
            // Comptable : Dashboard, Finances (full), Rapports (read/create), Catéchumènes (read)
            if ($ref === 'dashboard') {
                return ['can_read' => true, 'can_create' => false, 'can_update' => false, 'can_delete' => false, 'can_restore' => false, 'can_force_delete' => false];
            }
            if (str_starts_with($ref, 'main_finances') || in_array($ref, ['tarifications', 'operations_financieres', 'caisse', 'versements'])) {
                return ['can_read' => true, 'can_create' => true, 'can_update' => true, 'can_delete' => true, 'can_restore' => true, 'can_force_delete' => false];
            }
            if (str_starts_with($ref, 'main_rapports') || in_array($ref, ['catechumenes', 'main_catechumenes'])) {
                return ['can_read' => true, 'can_create' => true, 'can_update' => false, 'can_delete' => false, 'can_restore' => false, 'can_force_delete' => false];
            }
            return $none;
        }

        if ($rules === 'lecteur') {
            // Lecteur : read-only sur tout sauf administration sensible
            if (!in_array($ref, ['main_utilisateurs_securite', 'utilisateurs', 'profils', 'main_parametres', 'configuration_paroisse', 'sauvegardes'])) {
                return ['can_read' => true, 'can_create' => false, 'can_update' => false, 'can_delete' => false, 'can_restore' => false, 'can_force_delete' => false];
            }
            return $none;
        }

        return $none;
    }
}
