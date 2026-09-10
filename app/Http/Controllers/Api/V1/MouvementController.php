<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MouvementResource;
use App\Models\Mouvement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MouvementController extends Controller
{
    /**
     * Liste de tous les mouvements paroissiaux avec filtres.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user() ?? auth('sanctum')->user();
        $paroisseId = $user?->paroisse_configuration_id 
            ?? $request->input('paroisse_configuration_id')
            ?? $request->input('paroisse_id')
            ?? $request->header('X-Paroisse-Id');

        if (!$paroisseId) {
            return response()->json([
                'status' => 'success',
                'meta'   => ['total_elements' => 0],
                'data'   => [],
            ]);
        }

        $query = Mouvement::withCount('inscriptionsAnnuelles')
            ->where('paroisse_configuration_id', (int) $paroisseId);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('responsable', 'like', "%{$search}%")
                  ->orWhere('telephone', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('statut') && strtolower($request->input('statut')) !== 'tous') {
            $query->where('statut', ucfirst(strtolower($request->input('statut'))));
        }

        $mouvements = $query->orderBy('nom', 'asc')->get();

        return response()->json([
            'status' => 'success',
            'meta'   => [
                'total_elements' => $mouvements->count(),
            ],
            'data'   => MouvementResource::collection($mouvements),
        ]);
    }

    /**
     * Création d'un nouveau mouvement paroissial.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user() ?? auth('sanctum')->user();
        $paroisseId = $user?->paroisse_configuration_id 
            ?? $request->input('paroisse_configuration_id')
            ?? $request->input('paroisse_id')
            ?? $request->header('X-Paroisse-Id');

        if (!$paroisseId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'L\'identifiant de la paroisse est obligatoire.',
            ], 422);
        }

        $validated = $request->validate([
            'nom'         => ['required', 'string', 'max:150'],
            'responsable' => ['nullable', 'string', 'max:150'],
            'telephone'   => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'statut'      => ['nullable', 'string', 'in:Active,Inactive,active,inactive'],
        ]);

        $validated['paroisse_configuration_id'] = (int) $paroisseId;
        $validated['statut'] = ucfirst(strtolower($validated['statut'] ?? 'Active'));

        $mouvement = Mouvement::create($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Mouvement créé avec succès.',
            'data'    => new MouvementResource($mouvement),
        ], 201);
    }

    /**
     * Détails d'un mouvement.
     */
    public function show(Request $request, Mouvement $mouvement): JsonResponse
    {
        $user = $request->user() ?? auth('sanctum')->user();
        $this->authorizeTenant($user?->paroisse_configuration_id, $mouvement->paroisse_configuration_id);

        $mouvement->loadCount('inscriptionsAnnuelles');

        return response()->json([
            'status' => 'success',
            'data'   => new MouvementResource($mouvement),
        ]);
    }

    /**
     * Mise à jour d'un mouvement.
     */
    public function update(Request $request, Mouvement $mouvement): JsonResponse
    {
        $user = $request->user() ?? auth('sanctum')->user();
        $this->authorizeTenant($user?->paroisse_configuration_id, $mouvement->paroisse_configuration_id);

        $validated = $request->validate([
            'nom'         => ['sometimes', 'required', 'string', 'max:150'],
            'responsable' => ['nullable', 'string', 'max:150'],
            'telephone'   => ['nullable', 'string', 'max:20'],
            'description' => ['nullable', 'string'],
            'statut'      => ['nullable', 'string', 'in:Active,Inactive,active,inactive'],
        ]);

        if (isset($validated['statut'])) {
            $validated['statut'] = ucfirst(strtolower($validated['statut']));
        }

        $mouvement->update($validated);
        $mouvement->loadCount('inscriptionsAnnuelles');

        return response()->json([
            'status'  => 'success',
            'message' => 'Mouvement mis à jour avec succès.',
            'data'    => new MouvementResource($mouvement),
        ]);
    }

    /**
     * Basculer le statut d'un mouvement (Active <-> Inactive).
     */
    public function toggleStatus(Request $request, Mouvement $mouvement): JsonResponse
    {
        $user = $request->user() ?? auth('sanctum')->user();
        $this->authorizeTenant($user?->paroisse_configuration_id, $mouvement->paroisse_configuration_id);

        $nouveauStatut = (strtolower($mouvement->statut) === 'active') ? 'Inactive' : 'Active';
        $mouvement->update(['statut' => $nouveauStatut]);
        $mouvement->loadCount('inscriptionsAnnuelles');

        return response()->json([
            'status'  => 'success',
            'message' => "Le statut du mouvement '{$mouvement->nom}' est désormais {$nouveauStatut}.",
            'data'    => new MouvementResource($mouvement),
        ]);
    }

    /**
     * Suppression d'un mouvement.
     */
    public function destroy(Request $request, Mouvement $mouvement): JsonResponse
    {
        $user = $request->user() ?? auth('sanctum')->user();
        $this->authorizeTenant($user?->paroisse_configuration_id, $mouvement->paroisse_configuration_id);

        if ($mouvement->inscriptionsAnnuelles()->count() > 0) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Impossible de supprimer un mouvement rattaché à des inscriptions annuelles.',
            ], 422);
        }

        $mouvement->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Mouvement supprimé avec succès.',
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé. Ce mouvement appartient à une autre paroisse.'], 403));
        }
    }
}
