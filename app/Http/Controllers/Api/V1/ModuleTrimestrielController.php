<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ModuleTrimestrielResource;
use App\Models\AnneeCatechese;
use App\Models\ModuleTrimestriel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleTrimestrielController extends Controller
{
    /**
     * Liste des modules trimestriels pour l'année pastorale active ou filtrée.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $query = ModuleTrimestriel::with('anneeCatechese')->where('paroisse_configuration_id', $paroisseId);

        if ($request->filled('annee_catechese_id')) {
            $anneeId = AnneeCatechese::where('uuid', $request->annee_catechese_id)->value('id');
            if ($anneeId) {
                $query->where('annee_catechese_id', $anneeId);
            }
        }

        $modules = $query->orderBy('numero_trimestre')->get();

        return response()->json([
            'status' => 'success',
            'data' => ModuleTrimestrielResource::collection($modules),
        ]);
    }

    /**
     * Création d'un module trimestriel.
     */
    public function store(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $validated = $request->validate([
            'annee_catechese_id' => ['required', 'string', 'exists:annee_catecheses,uuid'],
            'nom' => ['required', 'string', 'max:255'],
            'numero_trimestre' => ['required', 'integer', 'min:1', 'max:4'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date', 'after_or_equal:date_debut'],
        ]);

        $annee = AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->firstOrFail();
        $validated['annee_catechese_id'] = $annee->id;
        $validated['paroisse_configuration_id'] = $paroisseId;

        $module = ModuleTrimestriel::create($validated);
        $module->load('anneeCatechese');

        return response()->json([
            'status' => 'success',
            'message' => 'Module trimestriel créé avec succès.',
            'data' => new ModuleTrimestrielResource($module),
        ], 201);
    }

    /**
     * Détails d'un module trimestriel.
     */
    public function show(Request $request, ModuleTrimestriel $moduleTrimestriel): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $moduleTrimestriel->paroisse_configuration_id);

        $moduleTrimestriel->load('anneeCatechese');

        return response()->json([
            'status' => 'success',
            'data' => new ModuleTrimestrielResource($moduleTrimestriel),
        ]);
    }

    /**
     * Mise à jour d'un module trimestriel.
     */
    public function update(Request $request, ModuleTrimestriel $moduleTrimestriel): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $moduleTrimestriel->paroisse_configuration_id);

        $validated = $request->validate([
            'nom' => ['sometimes', 'required', 'string', 'max:255'],
            'numero_trimestre' => ['sometimes', 'required', 'integer', 'min:1', 'max:4'],
            'date_debut' => ['nullable', 'date'],
            'date_fin' => ['nullable', 'date'],
        ]);

        $moduleTrimestriel->update($validated);
        $moduleTrimestriel->load('anneeCatechese');

        return response()->json([
            'status' => 'success',
            'message' => 'Module trimestriel mis à jour avec succès.',
            'data' => new ModuleTrimestrielResource($moduleTrimestriel),
        ]);
    }

    /**
     * Suppression d'un module trimestriel.
     */
    public function destroy(Request $request, ModuleTrimestriel $moduleTrimestriel): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $moduleTrimestriel->paroisse_configuration_id);

        $moduleTrimestriel->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Module trimestriel supprimé avec succès.',
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
