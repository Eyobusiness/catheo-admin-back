<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAnnonceRequest;
use App\Http\Requests\Api\V1\UpdateAnnonceRequest;
use App\Http\Resources\Api\V1\AnnonceResource;
use App\Models\AnneeCatechese;
use App\Models\Annonce;
use App\Models\Classe;
use App\Models\Niveau;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnnonceController extends Controller
{
    /**
     * Liste des annonces paroissiales.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $query = Annonce::with(['anneeCatechese', 'section', 'niveau', 'classe'])
            ->where('paroisse_configuration_id', $paroisseId);

        if ($request->filled('cible')) {
            $query->where('cible', $request->cible);
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        $annonces = $query->latest('date_publication')->get();

        return response()->json([
            'status' => 'success',
            'data' => AnnonceResource::collection($annonces),
        ]);
    }

    /**
     * Publier une nouvelle annonce.
     */
    public function store(StoreAnnonceRequest $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;
        $validated = $request->validated();

        $annee = AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->firstOrFail();

        $validated['paroisse_configuration_id'] = $paroisseId;
        $validated['annee_catechese_id'] = $annee->id;

        if (!empty($validated['section_id'])) {
            $section = Section::where('uuid', $validated['section_id'])->firstOrFail();
            $validated['section_id'] = $section->id;
        }

        if (!empty($validated['niveau_id'])) {
            $niveau = Niveau::where('uuid', $validated['niveau_id'])->firstOrFail();
            $validated['niveau_id'] = $niveau->id;
        }

        if (!empty($validated['classe_id'])) {
            $classe = Classe::where('uuid', $validated['classe_id'])->firstOrFail();
            $validated['classe_id'] = $classe->id;
        }


        $annonce = Annonce::create($validated);
        $annonce->load(['anneeCatechese', 'section', 'niveau', 'classe']);

        return response()->json([
            'status' => 'success',
            'message' => 'Annonce créée avec succès.',
            'data' => new AnnonceResource($annonce),
        ], 201);
    }

    /**
     * Détails d'une annonce.
     */
    public function show(Request $request, Annonce $annonce): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $annonce->paroisse_configuration_id);

        $annonce->load(['anneeCatechese', 'section', 'niveau', 'classe']);

        return response()->json([
            'status' => 'success',
            'data' => new AnnonceResource($annonce),
        ]);
    }

    /**
     * Mettre à jour une annonce.
     */
    public function update(UpdateAnnonceRequest $request, Annonce $annonce): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $annonce->paroisse_configuration_id);
        $validated = $request->validated();

        $annonce->update($validated);
        $annonce->load(['anneeCatechese', 'section', 'niveau', 'classe']);

        return response()->json([
            'status' => 'success',
            'message' => 'Annonce mise à jour avec succès.',
            'data' => new AnnonceResource($annonce),
        ]);
    }

    /**
     * Supprimer une annonce.
     */
    public function destroy(Request $request, Annonce $annonce): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $annonce->paroisse_configuration_id);

        $annonce->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Annonce supprimée avec succès.',
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
