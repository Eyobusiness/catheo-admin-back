<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ParrainMarraineResource;
use App\Models\Catechumene;
use App\Models\ParrainMarraine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParrainMarraineController extends Controller
{
    /**
     * Liste des parrains / marraines pour un catéchumène donné.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $query = ParrainMarraine::with('catechumene')
            ->where('paroisse_configuration_id', $paroisseId);

        if ($request->filled('catechumene_id')) {
            $catechumeneId = Catechumene::where('uuid', $request->catechumene_id)->value('id');
            if ($catechumeneId) {
                $query->where('catechumene_id', $catechumeneId);
            }
        }

        $list = $query->latest()->get();

        return response()->json([
            'status' => 'success',
            'data'   => ParrainMarraineResource::collection($list),
        ]);
    }

    /**
     * Ajouter un parrain ou une marraine.
     */
    public function store(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $validated = $request->validate([
            'catechumene_id'         => ['required', 'string', 'exists:catechumenes,uuid'],
            'type'                   => ['required', 'string', 'in:parrain,marraine'],
            'nom_prenoms'            => ['required', 'string', 'max:255'],
            'telephone'              => ['nullable', 'string', 'max:30'],
            'email'                  => ['nullable', 'string', 'email', 'max:255'],
            'domicile'               => ['nullable', 'string', 'max:255'],
            'paroisse_origine'       => ['nullable', 'string', 'max:255'],
            'representant_nom'       => ['nullable', 'string', 'max:255'],
            'representant_contact'   => ['nullable', 'string', 'max:30'],
            'sacrement_confirmation' => ['nullable', 'boolean'],
        ]);

        $catechumene = Catechumene::where('uuid', $validated['catechumene_id'])->firstOrFail();

        $validated['paroisse_configuration_id'] = $paroisseId;
        $validated['catechumene_id'] = $catechumene->id;

        $parrainMarraine = ParrainMarraine::create($validated);
        $parrainMarraine->load('catechumene');

        return response()->json([
            'status'  => 'success',
            'message' => 'Parrain / Marraine ajouté(e) avec succès.',
            'data'    => new ParrainMarraineResource($parrainMarraine),
        ], 201);
    }

    /**
     * Détails d'un parrain/marraine.
     */
    public function show(Request $request, ParrainMarraine $parrainMarraine): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $parrainMarraine->paroisse_configuration_id);

        $parrainMarraine->load('catechumene');

        return response()->json([
            'status' => 'success',
            'data'   => new ParrainMarraineResource($parrainMarraine),
        ]);
    }

    /**
     * Mettre à jour un parrain/marraine.
     */
    public function update(Request $request, ParrainMarraine $parrainMarraine): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $parrainMarraine->paroisse_configuration_id);

        $validated = $request->validate([
            'type'                   => ['sometimes', 'required', 'string', 'in:parrain,marraine'],
            'nom_prenoms'            => ['sometimes', 'required', 'string', 'max:255'],
            'telephone'              => ['nullable', 'string', 'max:30'],
            'email'                  => ['nullable', 'string', 'email', 'max:255'],
            'domicile'               => ['nullable', 'string', 'max:255'],
            'paroisse_origine'       => ['nullable', 'string', 'max:255'],
            'representant_nom'       => ['nullable', 'string', 'max:255'],
            'representant_contact'   => ['nullable', 'string', 'max:30'],
            'sacrement_confirmation' => ['nullable', 'boolean'],
        ]);

        $parrainMarraine->update($validated);
        $parrainMarraine->load('catechumene');

        return response()->json([
            'status'  => 'success',
            'message' => 'Informations mises à jour avec succès.',
            'data'    => new ParrainMarraineResource($parrainMarraine),
        ]);
    }

    /**
     * Supprimer un parrain/marraine.
     */
    public function destroy(Request $request, ParrainMarraine $parrainMarraine): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $parrainMarraine->paroisse_configuration_id);

        $parrainMarraine->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Parrain / Marraine supprimé(e) avec succès.',
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
