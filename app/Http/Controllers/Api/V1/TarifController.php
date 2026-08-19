<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTarifRequest;
use App\Http\Resources\Api\V1\TarifResource;
use App\Models\AnneeCatechese;
use App\Models\Niveau;
use App\Models\Tarif;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TarifController extends Controller
{
    /**
     * Liste de la grille tarifaire.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $query = Tarif::with(['anneeCatechese', 'niveau'])
            ->where('paroisse_configuration_id', $paroisseId);

        if ($request->filled('type_tarif')) {
            $query->where('type_tarif', $request->type_tarif);
        }

        $tarifs = $query->latest()->get();

        return response()->json([
            'status' => 'success',
            'data' => TarifResource::collection($tarifs),
        ]);
    }

    /**
     * Créer un élément tarifaire.
     */
    public function store(StoreTarifRequest $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;
        $validated = $request->validated();

        $annee = AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->firstOrFail();

        $validated['paroisse_configuration_id'] = $paroisseId;
        $validated['annee_catechese_id'] = $annee->id;

        if (!empty($validated['niveau_id'])) {
            $niveau = Niveau::where('uuid', $validated['niveau_id'])->firstOrFail();
            $validated['niveau_id'] = $niveau->id;
        }


        $tarif = Tarif::create($validated);
        $tarif->load(['anneeCatechese', 'niveau']);

        return response()->json([
            'status' => 'success',
            'message' => 'Tarif créé avec succès.',
            'data' => new TarifResource($tarif),
        ], 201);
    }

    /**
     * Détails d'un tarif.
     */
    public function show(Request $request, Tarif $tarif): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $tarif->paroisse_configuration_id);

        $tarif->load(['anneeCatechese', 'niveau']);

        return response()->json([
            'status' => 'success',
            'data' => new TarifResource($tarif),
        ]);
    }

    /**
     * Mettre à jour un tarif.
     */
    public function update(Request $request, Tarif $tarif): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $tarif->paroisse_configuration_id);

        $validated = $request->validate([
            'intitule' => ['sometimes', 'required', 'string', 'max:255'],
            'montant' => ['sometimes', 'required', 'numeric', 'min:0'],
            'type_tarif' => ['sometimes', 'required', 'string', 'in:inscription,manuel,uniforme,examen,autre'],
        ]);

        $tarif->update($validated);
        $tarif->load(['anneeCatechese', 'niveau']);

        return response()->json([
            'status' => 'success',
            'message' => 'Tarif mis à jour avec succès.',
            'data' => new TarifResource($tarif),
        ]);
    }

    /**
     * Supprimer un tarif.
     */
    public function destroy(Request $request, Tarif $tarif): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $tarif->paroisse_configuration_id);

        $tarif->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Tarif supprimé avec succès.',
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
