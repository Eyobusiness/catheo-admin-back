<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCampagnePreinscriptionRequest;
use App\Http\Requests\Api\V1\UpdateCampagnePreinscriptionRequest;
use App\Http\Resources\Api\V1\CampagnePreinscriptionResource;
use App\Models\AnneeCatechese;
use App\Models\CampagnePreinscription;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampagnePreinscriptionController extends Controller
{
    /**
     * Liste des campagnes de préinscription avec pagination et filtres.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $query = CampagnePreinscription::with('anneeCatechese')
            ->withCount('preinscriptions')
            ->where('paroisse_configuration_id', $paroisseId);

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('titre', 'like', "%{$search}%");
        }

        $perPage = (int) $request->get('per_page', 15);
        $campagnes = $query->latest()->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data'   => CampagnePreinscriptionResource::collection($campagnes->items()),
            'meta'   => [
                'current_page' => $campagnes->currentPage(),
                'last_page'    => $campagnes->lastPage(),
                'per_page'     => $campagnes->perPage(),
                'total'        => $campagnes->total(),
            ],
        ]);
    }

    /**
     * Consultation publique d'une campagne de préinscription (Pour le formulaire web des parents).
     */
    public function showPublic(string $uuid): JsonResponse
    {
        $campagne = CampagnePreinscription::where('uuid', $uuid)->firstOrFail();

        if ($campagne->statut !== 'ouverte') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Cette campagne de préinscription est actuellement fermée ou suspendue.',
            ], 422);
        }

        $campagne->load(['anneeCatechese', 'paroisse']);

        // Récupérer les sections et niveaux autorisés pour le formulaire parent
        $sectionsQuery = Section::where('paroisse_configuration_id', $campagne->paroisse_configuration_id)
            ->where('statut', 'actif')
            ->with(['niveaux' => function ($q) {
                $q->where('statut', 'actif')->orderBy('ordre_affichage');
            }]);

        if (!empty($campagne->sections_autorisees)) {
            $sectionsQuery->whereIn('uuid', $campagne->sections_autorisees);
        }

        $sections = $sectionsQuery->orderBy('ordre_affichage')->get();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'campagne' => new CampagnePreinscriptionResource($campagne),
                'paroisse' => [
                    'nom'      => $campagne->paroisse?->nom,
                    'commune'  => $campagne->paroisse?->commune,
                    'ville'    => $campagne->paroisse?->ville,
                    'logo_url' => $campagne->paroisse?->logo_url,
                ],
                'sections' => $sections,
            ],
        ]);
    }

    /**
     * Créer une campagne de préinscription.
     */
    public function store(StoreCampagnePreinscriptionRequest $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;
        $validated = $request->validated();

        $annee = AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->firstOrFail();
        $validated['annee_catechese_id'] = $annee->id;
        $validated['paroisse_configuration_id'] = $paroisseId;
        $validated['titre'] = $validated['titre'] ?? $validated['nom'] ?? 'Campagne de préinscription';
        $validated['statut'] = $validated['statut'] ?? 'ouverte';
        unset($validated['nom']);

        $campagne = CampagnePreinscription::create($validated);
        $campagne->load('anneeCatechese');

        return response()->json([
            'status'  => 'success',
            'message' => 'Campagne de préinscription créée avec succès.',
            'data'    => new CampagnePreinscriptionResource($campagne),
        ], 201);
    }

    /**
     * Détails d'une campagne.
     */
    public function show(Request $request, CampagnePreinscription $campagne): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $campagne->paroisse_configuration_id);

        $campagne->load('anneeCatechese')->loadCount('preinscriptions');

        return response()->json([
            'status' => 'success',
            'data'   => new CampagnePreinscriptionResource($campagne),
        ]);
    }

    /**
     * Mise à jour d'une campagne.
     */
    public function update(UpdateCampagnePreinscriptionRequest $request, CampagnePreinscription $campagne): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $campagne->paroisse_configuration_id);
        $validated = $request->validated();

        if (isset($validated['nom'])) {
            $validated['titre'] = $validated['nom'];
            unset($validated['nom']);
        }

        $campagne->update($validated);
        $campagne->load('anneeCatechese');

        return response()->json([
            'status'  => 'success',
            'message' => 'Campagne mise à jour avec succès.',
            'data'    => new CampagnePreinscriptionResource($campagne),
        ]);
    }

    /**
     * Suppression d'une campagne.
     */
    public function destroy(Request $request, CampagnePreinscription $campagne): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $campagne->paroisse_configuration_id);

        $campagne->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Campagne supprimée avec succès.',
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
