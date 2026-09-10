<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ResponsableCatecheseResource;
use App\Models\ResponsableCatechese;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResponsableCatecheseController extends Controller
{
    /**
     * Liste des responsables de la catéchèse du tenant connecté.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user() ?? auth('sanctum')->user();
        $paroisseId = $user?->paroisse_configuration_id 
            ?? $request->input('paroisse_configuration_id')
            ?? $request->input('paroisse_id')
            ?? $request->header('X-Paroisse-Id')
            ?? $request->header('X-Paroisse-Configuration-Id');

        if (!$paroisseId) {
            return response()->json([
                'status' => 'success',
                'data'   => [],
            ]);
        }

        $responsables = ResponsableCatechese::where('paroisse_configuration_id', (int) $paroisseId)
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => ResponsableCatecheseResource::collection($responsables),
        ]);
    }

    /**
     * Ajouter un responsable de catéchèse.
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
            'nom_prenoms' => ['required', 'string', 'max:255'],
            'fonction'    => ['required', 'string', 'max:255'],
            'telephone'   => ['nullable', 'string', 'max:50'],
            'statut'      => ['nullable', 'in:actif,inactif'],
        ]);

        $validated['paroisse_configuration_id'] = (int) $paroisseId;
        $validated['statut'] = $validated['statut'] ?? 'actif';

        $responsable = ResponsableCatechese::create($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Responsable ajouté avec succès.',
            'data'    => new ResponsableCatecheseResource($responsable),
        ], 201);
    }

    /**
     * Afficher un responsable par son UUID ou ID.
     */
    public function show(Request $request, mixed $responsable): JsonResponse
    {
        $model = $this->resolveResponsable($responsable);
        $this->authorizeTenant($request->user()?->paroisse_configuration_id, $model->paroisse_configuration_id);

        return response()->json([
            'status' => 'success',
            'data'   => new ResponsableCatecheseResource($model),
        ]);
    }

    /**
     * Mettre à jour un responsable de catéchèse.
     */
    public function update(Request $request, mixed $responsable): JsonResponse
    {
        $model = $this->resolveResponsable($responsable);
        $this->authorizeTenant($request->user()?->paroisse_configuration_id, $model->paroisse_configuration_id);

        $validated = $request->validate([
            'nom_prenoms' => ['sometimes', 'required', 'string', 'max:255'],
            'fonction'    => ['sometimes', 'required', 'string', 'max:255'],
            'telephone'   => ['nullable', 'string', 'max:50'],
            'statut'      => ['nullable', 'in:actif,inactif'],
        ]);

        $model->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Responsable mis à jour avec succès.',
            'data'    => new ResponsableCatecheseResource($model),
        ]);
    }

    /**
     * Supprimer un responsable de catéchèse (Soft Delete avec traçabilité deleted_by).
     */
    public function destroy(Request $request, mixed $responsable): JsonResponse
    {
        $model = $this->resolveResponsable($responsable);
        $this->authorizeTenant($request->user()?->paroisse_configuration_id, $model->paroisse_configuration_id);

        $model->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Responsable supprimé avec succès.',
        ]);
    }

    /**
     * Résout l'instance du modèle depuis un objet injecté, un UUID ou un ID numérique.
     */
    private function resolveResponsable(mixed $responsable): ResponsableCatechese
    {
        if ($responsable instanceof ResponsableCatechese && $responsable->exists) {
            return $responsable;
        }

        $identifier = is_object($responsable) ? ($responsable->uuid ?? $responsable->id ?? null) : $responsable;

        return ResponsableCatechese::where('uuid', $identifier)
            ->orWhere('id', $identifier)
            ->firstOrFail();
    }

    private function authorizeTenant(?int $userParoisseId, ?int $targetParoisseId): void
    {
        if ($userParoisseId && $targetParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json([
                'status'  => 'error',
                'message' => 'Accès refusé. Cette ressource appartient à une autre entité.',
            ], 403));
        }
    }
}
