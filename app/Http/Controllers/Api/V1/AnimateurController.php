<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AnimateurResource;
use App\Models\Animateur;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnimateurController extends Controller
{
    /**
     * Liste des animateurs (catéchistes) de la paroisse du tenant connecté.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $query = Animateur::with('user')->where('paroisse_configuration_id', $paroisseId);

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenoms', 'like', "%{$search}%")
                  ->orWhere('matricule', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('telephone', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->get('per_page', 15);
        $animateurs = $query->latest()->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => AnimateurResource::collection($animateurs->items()),
            'meta' => [
                'current_page' => $animateurs->currentPage(),
                'last_page' => $animateurs->lastPage(),
                'per_page' => $animateurs->perPage(),
                'total' => $animateurs->total(),
            ],
        ]);
    }

    /**
     * Enregistrement d'un nouvel animateur.
     */
    public function store(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $validated = $request->validate([
            'user_id' => ['nullable', 'string', 'exists:users,uuid'],
            'matricule' => ['nullable', 'string', 'max:50'],
            'nom' => ['required', 'string', 'max:255'],
            'prenoms' => ['required', 'string', 'max:255'],
            'sexe' => ['required', 'string', 'in:M,F'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'profession' => ['nullable', 'string', 'max:255'],
            'statut' => ['nullable', 'string', 'in:actif,inactif'],
        ]);

        if (!empty($validated['user_id'])) {
            $user = User::where('uuid', $validated['user_id'])->firstOrFail();
            $validated['user_id'] = $user->id;
        } else {
            // Auto-création d'un compte Utilisateur Animateur pour la connexion mobile (Téléphone + 12345678)
            $profilCatechisteId = \App\Models\Profil::where('code', 'CATECHISTE')->value('id');
            $name = trim("{$validated['nom']} {$validated['prenoms']}");
            $email = $validated['email'] ?? ('animateur_' . preg_replace('/[^0-9]/', '', $validated['telephone'] ?? uniqid()) . '@catheo.ci');

            $user = User::create([
                'name' => $name,
                'email' => $email,
                'telephone' => $validated['telephone'] ?? null,
                'password' => \Illuminate\Support\Facades\Hash::make('12345678'),
                'user_type' => 'animateur',
                'statut' => 'actif',
                'profil_id' => $profilCatechisteId,
                'paroisse_configuration_id' => $paroisseId,
            ]);
            $validated['user_id'] = $user->id;
        }

        $validated['paroisse_configuration_id'] = $paroisseId;

        $animateur = Animateur::create($validated);
        $animateur->load('user');

        return response()->json([
            'status' => 'success',
            'message' => 'Animateur / Catéchiste créé avec succès. Compte mobile activé (Mot de passe: 12345678).',
            'data' => new AnimateurResource($animateur),
        ], 201);
    }

    /**
     * Affichage d'un animateur.
     */
    public function show(Request $request, Animateur $animateur): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $animateur->paroisse_configuration_id);

        $animateur->load('user');

        return response()->json([
            'status' => 'success',
            'data' => new AnimateurResource($animateur),
        ]);
    }

    /**
     * Mise à jour d'un animateur.
     */
    public function update(Request $request, Animateur $animateur): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $animateur->paroisse_configuration_id);

        $validated = $request->validate([
            'user_id' => ['nullable', 'string', 'exists:users,uuid'],
            'matricule' => ['nullable', 'string', 'max:50'],
            'nom' => ['sometimes', 'required', 'string', 'max:255'],
            'prenoms' => ['sometimes', 'required', 'string', 'max:255'],
            'sexe' => ['sometimes', 'required', 'string', 'in:M,F'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'profession' => ['nullable', 'string', 'max:255'],
            'statut' => ['nullable', 'string', 'in:actif,inactif'],
        ]);

        if (array_key_exists('user_id', $validated)) {
            if ($validated['user_id']) {
                $user = User::where('uuid', $validated['user_id'])->firstOrFail();
                $validated['user_id'] = $user->id;
            } else {
                $validated['user_id'] = null;
            }
        }

        $animateur->update($validated);
        $animateur->load('user');

        return response()->json([
            'status' => 'success',
            'message' => 'Animateur mis à jour avec succès.',
            'data' => new AnimateurResource($animateur),
        ]);
    }

    /**
     * Activer / Désactiver un animateur.
     */
    public function updateStatus(Request $request, Animateur $animateur): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $animateur->paroisse_configuration_id);

        $validated = $request->validate([
            'statut' => ['required', 'string', 'in:actif,inactif'],
        ]);

        $animateur->update(['statut' => $validated['statut']]);

        return response()->json([
            'status' => 'success',
            'message' => 'Statut de l\'animateur mis à jour avec succès.',
            'data' => new AnimateurResource($animateur),
        ]);
    }

    /**
     * Suppression d'un animateur.
     */
    public function destroy(Request $request, Animateur $animateur): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $animateur->paroisse_configuration_id);

        $animateur->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Animateur supprimé avec succès.',
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
