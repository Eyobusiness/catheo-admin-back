<?php

namespace App\Observers;

use App\Models\CatecheseConfiguration;
use App\Models\Menu;
use App\Models\Profil;
use App\Models\ProfilMenuPermission;
use Illuminate\Support\Str;

class CatecheseConfigurationObserver
{
    /**
     * Déclenché après la création d'une catéchèse/paroisse.
     *
     * Crée automatiquement un profil ADMIN lié à cette paroisse
     * et lui attribue toutes les permissions disponibles sur tous les menus actifs.
     *
     * Ce comportement est dynamique : si de nouveaux menus sont ajoutés ultérieurement,
     * ils ne seront PAS automatiquement accordés (il faudra relancer l'attribution),
     * mais la logique ne hardcode aucun ID.
     */
    public function created(CatecheseConfiguration $catechese): void
    {
        $this->createAdminProfilForParoisse($catechese);
    }

    /**
     * Crée (ou retrouve) le profil ADMIN pour la paroisse et lui attribue toutes les permissions.
     *
     * Le code du profil est unique par paroisse pour éviter les collisions entre paroisses.
     * Exemple : ADMIN_SM01 pour la paroisse SM-01.
     */
    public function createAdminProfilForParoisse(CatecheseConfiguration $catechese): Profil
    {
        // Générer un code unique par paroisse à partir du code_paroisse
        $codeParoisse = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $catechese->code_paroisse));
        $profilCode   = 'ADMIN_' . $codeParoisse;

        // Création idempotente du profil ADMIN pour cette paroisse
        $profil = Profil::firstOrCreate(
            ['code' => $profilCode],
            [
                'nom'         => 'Administrateur – ' . $catechese->nom_paroisse,
                'description' => 'Profil administrateur créé automatiquement pour la catéchèse : ' . $catechese->nom_paroisse . '. Accès complet à toutes les fonctionnalités.',
                'statut'      => 'actif',
                'permissions' => ['*'],
                'is_system'   => true,
            ]
        );

        // Attribution de toutes les permissions disponibles sur tous les menus actifs
        // Note : dynamique — récupère les menus au moment de la création.
        $this->assignAllPermissionsToProfilFromMenus($profil);

        return $profil;
    }

    /**
     * Attribue toutes les permissions (CRUD + restore + force_delete) au profil
     * pour chaque menu existant dans la base.
     *
     * Ne hardcode aucun ID de menu — utilise les menus dynamiquement.
     * Utilise updateOrCreate pour être idempotent (sûr en cas de double appel).
     */
    public function assignAllPermissionsToProfilFromMenus(Profil $profil): void
    {
        $allMenus = Menu::all();

        foreach ($allMenus as $menu) {
            ProfilMenuPermission::updateOrCreate(
                [
                    'profil_id' => $profil->id,
                    'menu_id'   => $menu->id,
                ],
                [
                    'can_read'         => true,
                    'can_create'       => true,
                    'can_update'       => true,
                    'can_delete'       => true,
                    'can_restore'      => true,
                    'can_force_delete' => true,
                ]
            );
        }
    }
}
