<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreUserRequest;
use App\Http\Requests\Api\V1\UpdateUserRequest;
use App\Http\Requests\Api\V1\UpdateUserStatusRequest;
use App\Http\Resources\Api\V1\UserResource;
use App\Models\Profil;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    /**
     * Liste des utilisateurs de la paroisse du tenant connecté.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $query = User::with(['paroisse', 'profil']);

        if ($paroisseId) {
            $query->where('paroisse_configuration_id', $paroisseId);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nom', 'like', "%{$search}%")
                  ->orWhere('prenoms', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => $users,
        ]);
    }

    /**
     * Création d'un nouvel utilisateur.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;
        $validated = $request->validated();

        $profil = Profil::where('uuid', $validated['profil_id'])->firstOrFail();

        $name = $validated['name'] ?? trim(($validated['nom'] ?? '') . ' ' . ($validated['prenoms'] ?? ''));
        if (empty($name)) {
            $name = 'Utilisateur';
        }

        $nom = $validated['nom'] ?? $validated['name'] ?? 'Utilisateur';
        $prenoms = $validated['prenoms'] ?? '';

        $user = User::create([
            'paroisse_configuration_id' => $paroisseId,
            'profil_id'                 => $profil->id,
            'name'                      => $name,
            'email'                     => $validated['email'],
            'password'                  => Hash::make($validated['password']),
            'telephone'                 => $validated['telephone'] ?? null,
            'statut'                    => $validated['statut'] ?? 'actif',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Utilisateur créé avec succès.',
            'data'    => new UserResource($user->load(['paroisse', 'profil'])),
        ], 201);
    }

    /**
     * Détails d'un utilisateur.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $user->paroisse_configuration_id);

        return response()->json([
            'status' => 'success',
            'data'   => new UserResource($user->load(['paroisse', 'profil'])),
        ]);
    }

    /**
     * Mise à jour des informations d'un utilisateur.
     */
    public function update(UpdateUserRequest $request, User $user): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $user->paroisse_configuration_id);
        $validated = $request->validated();

        if (isset($validated['profil_id'])) {
            $profil = Profil::where('uuid', $validated['profil_id'])->firstOrFail();
            $user->profil_id = $profil->id;
        }

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $name = $validated['name'] ?? null;
        if (!$name && (isset($validated['nom']) || isset($validated['prenoms']))) {
            $name = trim(($validated['nom'] ?? '') . ' ' . ($validated['prenoms'] ?? ''));
        }

        if (!empty($name)) {
            $user->name = $name;
        }

        $user->fill([
            'email'     => $validated['email'] ?? $user->email,
            'telephone' => array_key_exists('telephone', $validated) ? $validated['telephone'] : $user->telephone,
            'statut'    => $validated['statut'] ?? $user->statut,
        ]);

        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Utilisateur mis à jour avec succès.',
            'data' => new UserResource($user->load(['paroisse', 'profil'])),
        ]);
    }

    /**
     * Modification du statut d'un utilisateur via FormRequest.
     */
    public function updateStatus(UpdateUserStatusRequest $request, User $user): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $user->paroisse_configuration_id);
        $validated = $request->validated();

        $user->statut = $validated['statut'] ?? ($validated['is_active'] ? 'actif' : 'inactif');
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Statut utilisateur mis à jour.',
            'data' => new UserResource($user->load(['paroisse', 'profil'])),
        ]);
    }

    /**
     * Suppression (Soft Delete) d'un utilisateur.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $user->paroisse_configuration_id);

        if ($user->id === $request->user()->id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Vous ne pouvez pas supprimer votre propre compte.',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Utilisateur supprimé avec succès.',
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
