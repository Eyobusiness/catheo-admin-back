<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MutationCatechumeneResource;
use App\Models\AnneeCatechese;
use App\Models\Catechumene;
use App\Models\MutationCatechumene;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MutationCatechumeneController extends Controller
{
    /**
     * Liste des mutations.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $query = MutationCatechumene::with(['catechumene', 'anneeCatechese'])
            ->where('paroisse_configuration_id', $paroisseId);

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        $mutations = $query->latest()->get();

        return response()->json([
            'status' => 'success',
            'data' => MutationCatechumeneResource::collection($mutations),
        ]);
    }

    /**
     * Demander un transfert / mutation.
     */
    public function store(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $validated = $request->validate([
            'catechumene_id' => ['required', 'string', 'exists:catechumenes,uuid'],
            'annee_catechese_id' => ['required', 'string', 'exists:annee_catecheses,uuid'],
            'paroisse_origine_nom' => ['required', 'string', 'max:255'],
            'paroisse_destination_nom' => ['required', 'string', 'max:255'],
            'motif' => ['nullable', 'string'],
            'date_mutation' => ['required', 'date'],
        ]);

        $catechumene = Catechumene::where('uuid', $validated['catechumene_id'])->firstOrFail();
        $annee = AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->firstOrFail();

        $validated['paroisse_configuration_id'] = $paroisseId;
        $validated['catechumene_id'] = $catechumene->id;
        $validated['annee_catechese_id'] = $annee->id;
        $validated['statut'] = 'demande';


        $mutation = MutationCatechumene::create($validated);
        $mutation->load(['catechumene', 'anneeCatechese']);

        return response()->json([
            'status' => 'success',
            'message' => 'Demande de mutation enregistrée avec succès.',
            'data' => new MutationCatechumeneResource($mutation),
        ], 201);
    }

    /**
     * Traiter la mutation (Approuver ou Refuser).
     */
    public function update(Request $request, MutationCatechumene $mutation): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $mutation->paroisse_configuration_id);

        $validated = $request->validate([
            'statut' => ['required', 'string', 'in:approuve,refuse'],
        ]);

        $mutation->update($validated);

        if ($validated['statut'] === 'approuve') {
            $mutation->catechumene->update(['statut' => 'transfere']);
        }

        $mutation->load(['catechumene', 'anneeCatechese']);

        return response()->json([
            'status' => 'success',
            'message' => "La mutation a été traitée avec le statut : {$mutation->statut}.",
            'data' => new MutationCatechumeneResource($mutation),
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
