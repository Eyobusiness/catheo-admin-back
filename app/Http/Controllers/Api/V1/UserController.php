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
        $user = $request->user() ?? auth('sanctum')->user();
        $paroisseId = $user?->paroisse_configuration_id 
            ?? $request->input('paroisse_configuration_id')
            ?? $request->input('paroisse_id')
            ?? $request->header('X-Paroisse-Id')
            ?? $request->header('X-Paroisse-Configuration-Id');

        $isSuperAdmin = $user && $user->isSuperAdmin();

        $query = User::with(['paroisse', 'profil']);

        if ($paroisseId) {
            $query->where('paroisse_configuration_id', (int) $paroisseId);
        } elseif (!$isSuperAdmin) {
            $defaultParoisseId = \App\Models\CatecheseConfiguration::value('id');
            if ($defaultParoisseId) {
                $query->where('paroisse_configuration_id', (int) $defaultParoisseId);
            }
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('nom', 'like', "%{$search}%")
                  ->orWhere('prenoms', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('telephone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('statut') && strtolower($request->input('statut')) !== 'tous') {
            $query->where('statut', strtolower($request->input('statut')));
        }

        if ($request->filled('profil_id')) {
            $profilInput = $request->input('profil_id');
            $profil = is_numeric($profilInput)
                ? Profil::find($profilInput)
                : Profil::where('uuid', $profilInput)->orWhere('code', $profilInput)->first();

            if ($profil) {
                $query->where('profil_id', $profil->id);
            }
        }

        $perPage = (int) $request->get('per_page', 15);
        $paginator = $query->latest()->paginate($perPage);

        $resourceCollection = UserResource::collection($paginator);

        return response()->json([
            'status' => 'success',
            'meta'   => [
                'current_page'   => $paginator->currentPage(),
                'per_page'       => $paginator->perPage(),
                'total_elements' => $paginator->total(),
                'total_pages'    => $paginator->lastPage(),
                'has_next'       => $paginator->hasMorePages(),
            ],
            'data'   => $resourceCollection->response()->getData(true),
        ]);
    }

    /**
     * Création d'un nouvel utilisateur.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $currentUser = $request->user() ?? auth('sanctum')->user();
        $paroisseId = $currentUser?->paroisse_configuration_id 
            ?? $request->input('paroisse_configuration_id')
            ?? $request->header('X-Paroisse-Id');

        if (!$paroisseId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'L\'identifiant de la paroisse est obligatoire.',
            ], 422);
        }

        $validated = $request->validated();

        $profilInput = $validated['profil_id'];
        $profil = is_numeric($profilInput)
            ? Profil::find($profilInput)
            : Profil::where('uuid', $profilInput)->orWhere('code', $profilInput)->first();

        if (!$profil) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Le profil sélectionné est introuvable.',
            ], 422);
        }

        if ($profil->paroisse_configuration_id && (int) $profil->paroisse_configuration_id !== (int) $paroisseId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Le profil sélectionné n\'appartient pas à votre catéchèse.',
            ], 422);
        }

        $nom = $validated['nom'] ?? $validated['name'] ?? 'Utilisateur';
        $prenoms = $validated['prenoms'] ?? '';
        $name = $validated['name'] ?? trim("{$nom} {$prenoms}");
        if (empty($name)) {
            $name = 'Utilisateur';
        }

        $statut = strtolower($validated['statut'] ?? (isset($validated['is_active']) && !$validated['is_active'] ? 'inactif' : 'actif'));

        $user = User::create([
            'paroisse_configuration_id' => (int) $paroisseId,
            'profil_id'                 => $profil->id,
            'user_type'                 => 'admin',
            'name'                      => $name,
            'email'                     => $validated['email'],
            'password'                  => Hash::make($validated['password']),
            'telephone'                 => $validated['telephone'] ?? null,
            'statut'                    => $statut,
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
        $currentUser = $request->user() ?? auth('sanctum')->user();
        $this->authorizeTenant($currentUser?->paroisse_configuration_id, $user->paroisse_configuration_id);

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
        $currentUser = $request->user() ?? auth('sanctum')->user();
        $this->authorizeTenant($currentUser?->paroisse_configuration_id, $user->paroisse_configuration_id);
        $validated = $request->validated();

        if (!empty($validated['profil_id'])) {
            $profilInput = $validated['profil_id'];
            $profil = is_numeric($profilInput)
                ? Profil::find($profilInput)
                : Profil::where('uuid', $profilInput)->orWhere('code', $profilInput)->first();

            if ($profil) {
                $user->profil_id = $profil->id;
            }
        }

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $nom = $validated['nom'] ?? null;
        $prenoms = $validated['prenoms'] ?? null;

        if (isset($validated['name'])) {
            $user->name = $validated['name'];
        } elseif ($nom !== null || $prenoms !== null) {
            $currentNom = $nom !== null ? $nom : $user->nom;
            $currentPrenoms = $prenoms !== null ? $prenoms : $user->prenoms;
            $user->name = trim("{$currentNom} {$currentPrenoms}");
        }

        if (isset($validated['email'])) {
            $user->email = $validated['email'];
        }
        if (array_key_exists('telephone', $validated)) {
            $user->telephone = $validated['telephone'];
        }
        if (isset($validated['statut'])) {
            $user->statut = strtolower($validated['statut']);
        } elseif (isset($validated['is_active'])) {
            $user->statut = $validated['is_active'] ? 'actif' : 'inactif';
        }

        $user->save();

        return response()->json([
            'status'  => 'success',
            'message' => 'Utilisateur mis à jour avec succès.',
            'data'    => new UserResource($user->load(['paroisse', 'profil'])),
        ]);
    }

    /**
     * Basculer / Modifier le statut d'un utilisateur.
     */
    public function updateStatus(UpdateUserStatusRequest $request, User $user): JsonResponse
    {
        $currentUser = $request->user() ?? auth('sanctum')->user();
        $this->authorizeTenant($currentUser?->paroisse_configuration_id, $user->paroisse_configuration_id);

        if ($user->profil && $user->profil->code === 'SUPER_ADMIN') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Le statut du compte Super Administrateur ne peut pas être modifié.',
            ], 403);
        }

        $validated = $request->validated();

        if (isset($validated['statut'])) {
            $user->statut = strtolower($validated['statut']);
        } elseif (isset($validated['is_active'])) {
            $user->statut = $validated['is_active'] ? 'actif' : 'inactif';
        } else {
            $user->statut = ($user->statut === 'actif') ? 'inactif' : 'actif';
        }

        $user->save();

        return response()->json([
            'status'  => 'success',
            'message' => "Le statut de l'utilisateur '{$user->name}' est désormais " . ucfirst($user->statut) . ".",
            'data'    => new UserResource($user->load(['paroisse', 'profil'])),
        ]);
    }

    /**
     * Suppression (Soft Delete) d'un utilisateur.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        $currentUser = $request->user() ?? auth('sanctum')->user();
        $this->authorizeTenant($currentUser?->paroisse_configuration_id, $user->paroisse_configuration_id);

        if ($request->user() && $user->id === $request->user()->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Vous ne pouvez pas supprimer votre propre compte connecté.',
            ], 422);
        }

        if ($user->profil && $user->profil->code === 'SUPER_ADMIN') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Le compte Super Administrateur ne peut pas être supprimé.',
            ], 403);
        }

        $user->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Utilisateur supprimé avec succès.',
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, ?int $targetParoisseId): void
    {
        if ($userParoisseId && $targetParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
