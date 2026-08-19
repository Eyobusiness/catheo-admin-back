<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreProfilRequest;
use App\Http\Requests\Api\V1\UpdateProfilRequest;
use App\Http\Resources\Api\V1\ProfilResource;
use App\Models\Menu;
use App\Models\Profil;
use App\Models\ProfilMenuPermission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProfilController extends Controller
{
    /**
     * Arbre complet des 13 menus officiels, sous-menus et matrice CRUD + Restore + ForceDelete.
     */
    public function permissionsTree(): JsonResponse
    {
        $menus = Menu::roots()->where('is_active', true)->with(['sousMenus' => function ($q) {
            $q->where('is_active', true)->orderBy('ordre', 'asc');
        }])->orderBy('ordre', 'asc')->get();

        $actions = [
            ['key' => 'read',         'label' => 'Lire / Consulter'],
            ['key' => 'create',       'label' => 'Créer'],
            ['key' => 'update',       'label' => 'Modifier'],
            ['key' => 'delete',       'label' => 'Supprimer (Logique)'],
            ['key' => 'restore',      'label' => 'Restaurer'],
            ['key' => 'force_delete', 'label' => 'Supprimer Définitivement'],
        ];

        $tree = [];
        foreach ($menus as $menu) {
            $sousMenusList = [];
            foreach ($menu->sousMenus as $sm) {
                $sousMenusList[] = [
                    'uuid'      => $sm->uuid,
                    'libelle'   => $sm->libelle,
                    'reference' => $sm->reference,
                    'path'      => $sm->path,
                    'icon'      => $sm->icon,
                    'ordre'     => $sm->ordre,
                    'actions'   => $actions,
                ];
            }

            $permissionsList = [];
            foreach ($actions as $act) {
                $permissionsList[] = [
                    'key'   => "{$menu->reference}.{$act['key']}",
                    'label' => $act['label'],
                ];
            }

            $tree[] = [
                'uuid'          => $menu->uuid,
                'menu'          => $menu->libelle,
                'libelle'       => $menu->libelle,
                'reference'     => $menu->reference,
                'code'          => $menu->reference,
                'path'          => $menu->path,
                'icon'          => $menu->icon,
                'ordre'         => $menu->ordre,
                'total_actions' => count($actions),
                'actions'       => $actions,
                'permissions'   => $permissionsList,
                'sousMenus'     => $sousMenusList,
            ];
        }

        return response()->json([
            'status' => 'success',
            'data'   => $tree,
        ]);
    }

    /**
     * Liste de tous les profils / rôles du système.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Profil::withCount('users');

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('statut') && strtolower($request->input('statut')) !== 'tous') {
            $query->where('statut', strtolower($request->input('statut')));
        }

        $profils = $query->get();

        return response()->json([
            'status' => 'success',
            'meta' => [
                'total_elements' => $profils->count(),
            ],
            'data' => ProfilResource::collection($profils),
        ]);
    }

    /**
     * Création d'un rôle / profil personnalisé avec permissions relationnelles.
     */
    public function store(StoreProfilRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $nom = $validated['nom'] ?? $validated['libelle'] ?? $validated['code'];
        $libelle = $validated['libelle'] ?? $validated['nom'] ?? $validated['code'];

        $profil = Profil::create([
            'code'        => $validated['code'],
            'nom'         => $nom,
            'libelle'     => $libelle,
            'description' => $validated['description'] ?? null,
            'statut'      => $validated['statut'] ?? 'actif',
            'permissions' => $validated['permissions'] ?? [],
            'is_system'   => false,
        ]);

        // Synchronisation des permissions relationnelles par menu/sous-menu si fournies
        $this->syncMenuPermissions($profil, $request->input('menu_permissions', $request->input('menus', [])));

        return response()->json([
            'status'  => 'success',
            'message' => 'Profil créé avec succès.',
            'data'    => new ProfilResource($profil->loadCount('users')),
        ], 201);
    }

    /**
     * Obtenir les détails d'un profil par son ID (UUID).
     */
    public function show(Request $request, Profil $profil): JsonResponse
    {
        $profil->loadCount('users');

        return response()->json([
            'status' => 'success',
            'data'   => new ProfilResource($profil),
        ]);
    }

    /**
     * Mettre à jour les informations et permissions d'un profil.
     */
    public function update(UpdateProfilRequest $request, Profil $profil): JsonResponse
    {
        if ($profil->is_system) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Les profils système ne peuvent pas être modifiés.',
            ], 403);
        }

        $validated = $request->validated();

        if (isset($validated['nom'])) {
            $validated['libelle'] = $validated['nom'];
        }

        $profil->update($validated);

        // Synchronisation des permissions par menu/sous-menu si fournies
        if ($request->has('menu_permissions') || $request->has('menus')) {
            $this->syncMenuPermissions($profil, $request->input('menu_permissions', $request->input('menus', [])));
        }

        $profil->loadCount('users');

        return response()->json([
            'status'  => 'success',
            'message' => 'Profil mis à jour avec succès.',
            'data'    => new ProfilResource($profil),
        ]);
    }

    /**
     * Basculer le statut d'un profil (Actif / Inactif).
     */
    public function toggleStatus(Request $request, Profil $profil): JsonResponse
    {
        if ($profil->is_system) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Le statut des profils système ne peut pas être modifié.',
            ], 403);
        }

        $nouveauStatut = ($profil->statut === 'actif') ? 'inactif' : 'actif';
        $profil->update(['statut' => $nouveauStatut]);

        return response()->json([
            'status'  => 'success',
            'message' => "Le statut du profil '{$profil->nom}' est désormais " . ucfirst($nouveauStatut) . ".",
            'data'    => new ProfilResource($profil),
        ]);
    }

    /**
     * Supprimer un profil personnalisé.
     */
    public function destroy(Request $request, Profil $profil): JsonResponse
    {
        if ($profil->is_system) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Les profils système ne peuvent pas être supprimés.',
            ], 403);
        }

        if ($profil->users()->count() > 0) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Impossible de supprimer un profil auquel des utilisateurs sont rattachés.',
            ], 422);
        }

        $profil->menuPermissions()->delete();
        $profil->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Profil supprimé avec succès.',
        ]);
    }

    /**
     * Synchronise la matrice de permissions relationnelles pour un profil.
     */
    protected function syncMenuPermissions(Profil $profil, array $menuPermissions): void
    {
        if (empty($menuPermissions)) {
            return;
        }

        foreach ($menuPermissions as $item) {
            $menuId = null;

            if (isset($item['menu_id'])) {
                $menu = is_numeric($item['menu_id']) 
                    ? Menu::find($item['menu_id']) 
                    : Menu::where('uuid', $item['menu_id'])->first();
                $menuId = $menu?->id;
            } elseif (isset($item['uuid'])) {
                $menu = Menu::where('uuid', $item['uuid'])->first();
                $menuId = $menu?->id;
            } elseif (isset($item['reference'])) {
                $menu = Menu::where('reference', $item['reference'])->first();
                $menuId = $menu?->id;
            }

            if (!$menuId) {
                continue;
            }

            $perms = $item['permissions'] ?? $item;

            ProfilMenuPermission::updateOrCreate(
                [
                    'profil_id' => $profil->id,
                    'menu_id'   => $menuId,
                ],
                [
                    'can_read'         => (bool) ($perms['can_read'] ?? $perms['read'] ?? false),
                    'can_create'       => (bool) ($perms['can_create'] ?? $perms['create'] ?? false),
                    'can_update'       => (bool) ($perms['can_update'] ?? $perms['update'] ?? false),
                    'can_delete'       => (bool) ($perms['can_delete'] ?? $perms['delete'] ?? false),
                    'can_restore'      => (bool) ($perms['can_restore'] ?? $perms['restore'] ?? false),
                    'can_force_delete' => (bool) ($perms['can_force_delete'] ?? $perms['force_delete'] ?? false),
                ]
            );

            // Traitement récursif pour les sous-menus s'ils sont inclus
            if (!empty($item['sousMenus']) && is_array($item['sousMenus'])) {
                $this->syncMenuPermissions($profil, $item['sousMenus']);
            }
        }
    }
}
