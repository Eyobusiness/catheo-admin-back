<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AffectationAnimateurResource;
use App\Models\AffectationAnimateur;
use App\Models\Animateur;
use App\Models\AnneeCatechese;
use App\Models\Classe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AffectationAnimateurController extends Controller
{
    /**
     * Liste des affectations d'animateurs avec filtres par année et classe.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $query = AffectationAnimateur::with(['animateur', 'anneeCatechese', 'classe'])
            ->where('paroisse_configuration_id', $paroisseId);

        if ($request->filled('annee_catechese_id')) {
            $anneeId = AnneeCatechese::where('uuid', $request->annee_catechese_id)->value('id');
            if ($anneeId) {
                $query->where('annee_catechese_id', $anneeId);
            }
        }

        if ($request->filled('classe_id')) {
            $classeId = Classe::where('uuid', $request->classe_id)->value('id');
            if ($classeId) {
                $query->where('classe_id', $classeId);
            }
        }

        if ($request->filled('animateur_id')) {
            $animateurId = Animateur::where('uuid', $request->animateur_id)->value('id');
            if ($animateurId) {
                $query->where('animateur_id', $animateurId);
            }
        }

        $affectations = $query->latest()->get();

        return response()->json([
            'status' => 'success',
            'data' => AffectationAnimateurResource::collection($affectations),
        ]);
    }

    /**
     * Affecter un animateur à une classe pour une année pastorale.
     */
    public function store(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $validated = $request->validate([
            'animateur_id' => ['required', 'string', 'exists:animateurs,uuid'],
            'annee_catechese_id' => ['required', 'string', 'exists:annee_catecheses,uuid'],
            'classe_id' => ['nullable', 'string', 'exists:classes,uuid'],
            'role_animateur' => ['nullable', 'string', 'in:principal,adjoint'],
        ]);

        $animateur = Animateur::where('uuid', $validated['animateur_id'])->firstOrFail();
        $annee = AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->firstOrFail();

        $validated['animateur_id'] = $animateur->id;
        $validated['annee_catechese_id'] = $annee->id;
        $validated['paroisse_configuration_id'] = $paroisseId;

        if (!empty($validated['classe_id'])) {
            $classe = Classe::where('uuid', $validated['classe_id'])->firstOrFail();
            $validated['classe_id'] = $classe->id;
        }


        $affectation = AffectationAnimateur::create($validated);
        $affectation->load(['animateur', 'anneeCatechese', 'classe']);

        return response()->json([
            'status' => 'success',
            'message' => 'Animateur affecté avec succès.',
            'data' => new AffectationAnimateurResource($affectation),
        ], 201);
    }

    /**
     * Supprimer une affectation.
     */
    public function destroy(Request $request, AffectationAnimateur $affectation): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $affectation->paroisse_configuration_id);

        $affectation->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Affectation supprimée avec succès.',
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
