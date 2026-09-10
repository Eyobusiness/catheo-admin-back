<?php

namespace App\Models;

use App\Traits\Auditable;

use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Profil extends Model
{
    use Auditable, HasAuditFields, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'profils';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'nom',
        'code',
        'description',
        'statut',
        'permissions',
        'is_system',
    ];

    public function paroisse(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CatecheseConfiguration::class, 'paroisse_configuration_id');
    }

    protected $casts = [
        'permissions' => 'array',
        'is_system'   => 'boolean',
    ];

    /**
     * Résolution robuste pour Route Model Binding (UUID ou ID numérique).
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return is_numeric($value)
            ? $this->where('id', $value)->first()
            : $this->where('uuid', $value)->first()
            ?? parent::resolveRouteBinding($value, $field);
    }

    /**
     * Utilisateurs possédant ce profil.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'profil_id');
    }

    /**
     * Permissions fines par menu associées à ce profil.
     */
    public function menuPermissions(): HasMany
    {
        return $this->hasMany(ProfilMenuPermission::class, 'profil_id');
    }

    /**
     * Menus associés au profil via la table pivot.
     */
    public function menus(): BelongsToMany
    {
        return $this->belongsToMany(Menu::class, 'profil_menu_permissions', 'profil_id', 'menu_id')
            ->withPivot([
                'uuid',
                'can_read',
                'can_create',
                'can_update',
                'can_delete',
                'can_restore',
                'can_force_delete',
            ])
            ->withTimestamps();
    }

    /**
     * Vérifier si le profil possède une permission donnée (supporte legacy string et nouvelle matrice).
     */
    public function hasPermission(string $permission): bool
    {
        // 1. Super Admin & Admin : accès total
        if ($this->is_system && (in_array($this->code, ['SUPER_ADMIN', 'ADMIN'], true) || in_array('*', (array) $this->permissions, true))) {
            return true;
        }

        // 2. Vérification dans le tableau JSON permissions historique
        $perms = (array) ($this->permissions ?? []);
        if (in_array('*', $perms, true) || in_array($permission, $perms, true)) {
            return true;
        }

        $parts = explode('.', $permission);
        $module = $parts[0] ?? '';
        $action = $parts[1] ?? 'view';

        if (in_array("{$module}.manage", $perms, true)) {
            return true;
        }

        // 3. Vérification dans la table relationnelle profil_menu_permissions
        return $this->hasMenuActionPermission($module, $action);
    }

    /**
     * Vérifie les droits relationnels dans profil_menu_permissions.
     */
    public function hasMenuActionPermission(string $menuReference, string $action): bool
    {
        if ($this->is_system && (in_array($this->code, ['SUPER_ADMIN', 'ADMIN'], true) || in_array('*', (array) $this->permissions, true))) {
            return true;
        }

        // Normalisation de l'action
        $column = match (strtolower($action)) {
            'create', 'store', 'post'          => 'can_create',
            'read', 'view', 'show', 'index', 'get' => 'can_read',
            'update', 'edit', 'patch', 'put'   => 'can_update',
            'delete', 'destroy'                => 'can_delete',
            'restore'                          => 'can_restore',
            'force_delete', 'force'            => 'can_force_delete',
            'manage'                           => 'can_read',
            default                            => 'can_read',
        };

        // Recherche du menu ou sous-menu par référence exacte, préfixe main_ ou chemin
        $cleanRef = strtolower(trim($menuReference));
        $menus = Menu::where('reference', $cleanRef)
            ->orWhere('reference', "main_{$cleanRef}")
            ->orWhere('reference', 'like', "%{$cleanRef}%")
            ->orWhere('path', '/' . ltrim($cleanRef, '/'))
            ->get();

        if ($menus->isEmpty()) {
            return false;
        }

        $menuIds = $menus->pluck('id')->toArray();

        // Récupérer aussi les IDs des sous-menus si un menu parent a été trouvé
        $subMenusIds = Menu::whereIn('parent_id', $menuIds)->pluck('id')->toArray();
        $allTargetIds = array_unique(array_merge($menuIds, $subMenusIds));

        // Vérifier si l'une des permissions associées accorde le droit requis
        return $this->menuPermissions()
            ->whereIn('menu_id', $allTargetIds)
            ->where($column, true)
            ->exists();
    }

    /**
     * Génère l'arborescence complète des menus et sous-menus autorisés pour Angular.
     */
    public function getAccessibleMenusTree(): array
    {
        $isFullAccess = $this->is_system && (in_array($this->code, ['SUPER_ADMIN', 'ADMIN'], true) || in_array('*', (array) $this->permissions, true));
        $allPermissions = $this->menuPermissions()->with('menu')->get()->keyBy('menu_id');

        $rootMenus = Menu::roots()->where('is_active', true)->with(['sousMenus' => function ($q) {
            $q->where('is_active', true)->orderBy('ordre', 'asc');
        }])->get();

        $tree = [];

        foreach ($rootMenus as $root) {
            $rootPivot = $allPermissions->get($root->id);

            $rootPerms = $isFullAccess ? [
                'create'       => true,
                'read'         => true,
                'update'       => true,
                'delete'       => true,
                'restore'      => true,
                'force_delete' => true,
            ] : ($rootPivot ? $rootPivot->toPermissionsArray() : [
                'create'       => false,
                'read'         => false,
                'update'       => false,
                'delete'       => false,
                'restore'      => false,
                'force_delete' => false,
            ]);

            $sousMenusList = [];
            foreach ($root->sousMenus as $sousMenu) {
                $subPivot = $allPermissions->get($sousMenu->id);
                $subPerms = $isFullAccess ? [
                    'create'       => true,
                    'read'         => true,
                    'update'       => true,
                    'delete'       => true,
                    'restore'      => true,
                    'force_delete' => true,
                ] : ($subPivot ? $subPivot->toPermissionsArray() : [
                    'create'       => false,
                    'read'         => false,
                    'update'       => false,
                    'delete'       => false,
                    'restore'      => false,
                    'force_delete' => false,
                ]);

                // Si le sous-menu a au moins une permission active ou si SuperAdmin / Admin
                if ($isFullAccess || in_array(true, $subPerms, true)) {
                    $sousMenusList[] = [
                        'order'       => $sousMenu->ordre,
                        'id'          => $sousMenu->uuid,
                        'uuid'        => $sousMenu->uuid,
                        'libelle'     => $sousMenu->libelle,
                        'icon'        => $sousMenu->icon,
                        'path'        => $sousMenu->path,
                        'code'        => $sousMenu->code ?? '',
                        'permission'  => $sousMenu->permission,
                        'reference'   => $sousMenu->reference,
                        'ordre'       => $sousMenu->ordre,
                        'permissions' => $subPerms,
                    ];
                }
            }

            // Si le menu racine a au moins un droit de lecture/action ou des sous-menus accessibles
            if ($isFullAccess || $rootPerms['read'] || in_array(true, $rootPerms, true) || !empty($sousMenusList)) {
                // Si des sous-menus sont autorisés, le parent doit au moins avoir read: true
                if (!empty($sousMenusList) && !$rootPerms['read']) {
                    $rootPerms['read'] = true;
                }

                $tree[] = [
                    'order'       => $root->ordre,
                    'id'          => $root->uuid,
                    'uuid'        => $root->uuid,
                    'libelle'     => $root->libelle,
                    'icon'        => $root->icon,
                    'path'        => $root->path,
                    'code'        => $root->code ?? '',
                    'permission'  => $root->permission,
                    'reference'   => $root->reference,
                    'ordre'       => $root->ordre,
                    'permissions' => $rootPerms,
                    'sousMenus'   => $sousMenusList,
                ];
            }
        }

        return $tree;
    }
}
