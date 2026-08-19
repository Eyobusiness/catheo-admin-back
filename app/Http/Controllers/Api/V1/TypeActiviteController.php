<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTypeActiviteRequest;
use App\Http\Resources\Api\V1\TypeActiviteResource;
use App\Models\TypeActivite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TypeActiviteController extends Controller
{
    /**
     * Liste des types d'activités.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $types = TypeActivite::where('paroisse_configuration_id', $paroisseId)
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => TypeActiviteResource::collection($types),
        ]);
    }

    /**
     * Créer un type d'activité.
     */
    public function store(StoreTypeActiviteRequest $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;
        $validated = $request->validated();
        $validated['paroisse_configuration_id'] = $paroisseId;

        $typeActivite = TypeActivite::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Type d\'activité créé avec succès.',
            'data' => new TypeActiviteResource($typeActivite),
        ], 201);
    }

    /**
     * Afficher un type d'activité.
     */
    public function show(Request $request, TypeActivite $typeActivite): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $typeActivite->paroisse_configuration_id);

        return response()->json([
            'status' => 'success',
            'data' => new TypeActiviteResource($typeActivite),
        ]);
    }

    /**
     * Mettre à jour un type d'activité.
     */
    public function update(StoreTypeActiviteRequest $request, TypeActivite $typeActivite): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $typeActivite->paroisse_configuration_id);

        $typeActivite->update($request->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Type d\'activité mis à jour avec succès.',
            'data' => new TypeActiviteResource($typeActivite),
        ]);
    }

    /**
     * Supprimer un type d'activité.
     */
    public function destroy(Request $request, TypeActivite $typeActivite): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $typeActivite->paroisse_configuration_id);

        $typeActivite->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Type d\'activité supprimé avec succès.',
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
