<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DecisionFinAnneeResource;
use App\Models\DecisionFinAnnee;
use App\Models\InscriptionAnnuelle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DecisionFinAnneeController extends Controller
{
    /**
     * Liste des décisions de fin d'année.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $query = DecisionFinAnnee::with(['inscriptionAnnuelle.catechumene', 'inscriptionAnnuelle.niveau'])
            ->where('paroisse_configuration_id', $paroisseId);

        if ($request->filled('decision')) {
            $query->where('decision', $request->decision);
        }

        $decisions = $query->latest('date_decision')->get();

        return response()->json([
            'status' => 'success',
            'data' => DecisionFinAnneeResource::collection($decisions),
        ]);
    }

    /**
     * Enregistrer une décision de fin d'année pour un catéchumène.
     */
    public function store(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $validated = $request->validate([
            'inscription_annuelle_id' => ['required', 'string', 'exists:inscriptions_annuelles,uuid'],
            'moyenne_annuelle' => ['nullable', 'numeric', 'min:0', 'max:20'],
            'decision' => ['required', 'string', 'in:admis,redouble,exclu,sacrement_valide'],
            'mention' => ['nullable', 'string', 'max:255'],
            'sacrement_recu' => ['nullable', 'boolean'],
            'date_decision' => ['required', 'date'],
            'observations' => ['nullable', 'string'],
        ]);

        $inscription = InscriptionAnnuelle::where('uuid', $validated['inscription_annuelle_id'])->firstOrFail();

        $validated['paroisse_configuration_id'] = $paroisseId;
        $validated['inscription_annuelle_id'] = $inscription->id;

        $decision = DecisionFinAnnee::updateOrCreate(
            [
                'paroisse_configuration_id' => $paroisseId,
                'inscription_annuelle_id' => $inscription->id,
            ],
            $validated
        );

        $decision->load(['inscriptionAnnuelle.catechumene']);

        return response()->json([
            'status' => 'success',
            'message' => 'Décision de fin d\'année enregistrée avec succès.',
            'data' => new DecisionFinAnneeResource($decision),
        ], 201);
    }

    /**
     * Afficher les détails d'une décision.
     */
    public function show(Request $request, DecisionFinAnnee $decision): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $decision->paroisse_configuration_id);

        $decision->load(['inscriptionAnnuelle.catechumene', 'inscriptionAnnuelle.niveau']);

        return response()->json([
            'status' => 'success',
            'data' => new DecisionFinAnneeResource($decision),
        ]);
    }

    /**
     * Supprimer une décision.
     */
    public function destroy(Request $request, DecisionFinAnnee $decision): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $decision->paroisse_configuration_id);

        $decision->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Décision supprimée avec succès.',
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
