<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AnimateurResource;
use App\Models\Animateur;
use App\Models\CatecheseConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AnimateurController extends Controller
{
    /**
     * Liste des animateurs (catéchistes) de la paroisse du tenant connecté.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user() ?? auth('sanctum')->user();
        $paroisseId = $user?->paroisse_configuration_id 
            ?? $request->input('paroisse_configuration_id')
            ?? $request->header('X-Paroisse-Id');

        if (!$paroisseId) {
            return response()->json([
                'status' => 'success',
                'data'   => [],
                'meta'   => ['total' => 0],
            ]);
        }

        $query = Animateur::where('paroisse_configuration_id', (int) $paroisseId);

        if ($request->filled('statut')) {
            $statut = strtolower($request->statut);
            $query->whereRaw('LOWER(statut) = ?', [$statut]);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenoms', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('telephone', 'like', "%{$search}%")
                  ->orWhere('profession', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->get('per_page', 15);
        $animateurs = $query->orderBy('nom')->orderBy('prenoms')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data'   => AnimateurResource::collection($animateurs->items()),
            'meta'   => [
                'current_page' => $animateurs->currentPage(),
                'last_page'    => $animateurs->lastPage(),
                'per_page'     => $animateurs->perPage(),
                'total'        => $animateurs->total(),
            ],
        ]);
    }

    /**
     * Enregistrement d'un nouvel animateur (connexion directe dans la table `animateurs`).
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user() ?? auth('sanctum')->user();
        $paroisseId = $user?->paroisse_configuration_id 
            ?? $request->input('paroisse_configuration_id')
            ?? $request->header('X-Paroisse-Id');

        if (!$paroisseId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'L\'identifiant de la paroisse est obligatoire.',
            ], 422);
        }

        $validated = $request->validate([
            'nom'        => ['required', 'string', 'max:255'],
            'prenoms'    => ['required', 'string', 'max:255'],
            'sexe'       => ['required', 'string', 'in:M,F,m,f'],
            'telephone'  => ['nullable', 'string', 'max:30'],
            'email'      => ['nullable', 'string', 'email', 'max:255'],
            'profession' => ['nullable', 'string', 'max:255'],
            'statut'     => ['nullable', 'string'],
            'password'   => ['nullable', 'string', 'min:6'],
        ]);

        $validated['sexe'] = strtoupper($validated['sexe']);
        $validated['statut'] = strtolower($validated['statut'] ?? 'actif');
        $validated['paroisse_configuration_id'] = $paroisseId;

        // Mot de passe pour connexion directe dans la table animateurs
        $rawPassword = $validated['password'] ?? '12345678';
        $validated['password'] = Hash::make($rawPassword);

        $animateur = Animateur::create($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Animateur créé avec succès. Mot de passe de connexion initial : ' . $rawPassword,
            'data'    => new AnimateurResource($animateur),
        ], 201);
    }

    /**
     * Affichage d'un animateur.
     */
    public function show(Request $request, mixed $animateur): JsonResponse
    {
        $user = $request->user() ?? auth('sanctum')->user();
        $model = $this->resolveAnimateur($animateur);
        $this->authorizeTenant($user?->paroisse_configuration_id, $model->paroisse_configuration_id);

        return response()->json([
            'status' => 'success',
            'data'   => new AnimateurResource($model),
        ]);
    }

    /**
     * Mise à jour d'un animateur.
     */
    public function update(Request $request, mixed $animateur): JsonResponse
    {
        $user = $request->user() ?? auth('sanctum')->user();
        $model = $this->resolveAnimateur($animateur);
        $this->authorizeTenant($user?->paroisse_configuration_id, $model->paroisse_configuration_id);

        $validated = $request->validate([
            'nom'        => ['sometimes', 'required', 'string', 'max:255'],
            'numero'     => ['sometimes', 'nullable', 'string', 'max:50'],
            'prenoms'    => ['sometimes', 'required', 'string', 'max:255'],
            'sexe'       => ['sometimes', 'required', 'string', 'in:M,F,m,f'],
            'telephone'  => ['nullable', 'string', 'max:30'],
            'email'      => ['nullable', 'string', 'email', 'max:255'],
            'profession' => ['nullable', 'string', 'max:255'],
            'statut'     => ['nullable', 'string'],
            'password'   => ['nullable', 'string', 'min:6'],
        ]);

        if (isset($validated['sexe'])) {
            $validated['sexe'] = strtoupper($validated['sexe']);
        }
        if (isset($validated['statut'])) {
            $validated['statut'] = strtolower($validated['statut']);
        }
        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $model->update($validated);
        $model->refresh();

        return response()->json([
            'status'  => 'success',
            'message' => 'Animateur mis à jour avec succès.',
            'data'    => new AnimateurResource($model),
        ]);
    }

    /**
     * Activer / Désactiver un animateur (ou bascule statut).
     */
    public function updateStatus(Request $request, mixed $animateur): JsonResponse
    {
        $user = $request->user() ?? auth('sanctum')->user();
        $model = $this->resolveAnimateur($animateur);
        $this->authorizeTenant($user?->paroisse_configuration_id, $model->paroisse_configuration_id);

        if ($request->filled('statut')) {
            $nouveauStatut = strtolower($request->statut);
        } else {
            $current = strtolower($model->statut ?? 'actif');
            $nouveauStatut = ($current === 'actif') ? 'inactif' : 'actif';
        }

        $model->update(['statut' => $nouveauStatut]);
        $model->refresh();

        return response()->json([
            'status'  => 'success',
            'message' => "Le statut de l'animateur est désormais {$nouveauStatut}.",
            'data'    => new AnimateurResource($model),
        ]);
    }

    /**
     * Suppression d'un animateur.
     */
    public function destroy(Request $request, mixed $animateur): JsonResponse
    {
        $user = $request->user() ?? auth('sanctum')->user();
        $model = $this->resolveAnimateur($animateur);
        $this->authorizeTenant($user?->paroisse_configuration_id, $model->paroisse_configuration_id);

        $model->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Animateur supprimé avec succès.',
        ]);
    }

    /**
     * Résout l'instance du modèle depuis un objet injecté, un UUID ou un ID numérique.
     */
    private function resolveAnimateur(mixed $animateur): Animateur
    {
        if ($animateur instanceof Animateur && $animateur->exists) {
            return $animateur;
        }

        $identifier = is_object($animateur) ? ($animateur->uuid ?? $animateur->id ?? null) : $animateur;

        return Animateur::where('uuid', $identifier)
            ->orWhere('id', $identifier)
            ->firstOrFail();
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}

