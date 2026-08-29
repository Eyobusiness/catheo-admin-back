<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCampagnePreinscriptionRequest;
use App\Http\Requests\Api\V1\UpdateCampagnePreinscriptionRequest;
use App\Http\Resources\Api\V1\CampagnePreinscriptionResource;
use App\Models\AnneeCatechese;
use App\Models\CampagnePreinscription;
use App\Models\CatecheseConfiguration;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampagnePreinscriptionController extends Controller
{
    /**
     * Liste des campagnes de préinscription.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()?->paroisse_configuration_id ?? CatecheseConfiguration::first()?->id;

        $query = CampagnePreinscription::with('anneeCatechese')
            ->withCount('preinscriptions')
            ->where('paroisse_configuration_id', $paroisseId);

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('annee_catechese_id')) {
            $anneeId = AnneeCatechese::where('uuid', $request->annee_catechese_id)
                ->orWhere('id', $request->annee_catechese_id)
                ->value('id');
            if ($anneeId) {
                $query->where('annee_catechese_id', $anneeId);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('titre', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $campagnes = $query->latest('date_debut')->get();

        return response()->json([
            'status' => 'success',
            'meta'   => [
                'total_elements' => $campagnes->count(),
            ],
            'data'   => CampagnePreinscriptionResource::collection($campagnes),
        ]);
    }

    /**
     * Consultation publique d'une campagne de préinscription (Pour le formulaire web des parents).
     */
    public function showPublic(string $uuid): JsonResponse
    {
        $campagne = CampagnePreinscription::where('uuid', $uuid)
            ->orWhere('id', $uuid)
            ->firstOrFail();

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
            $sectionsQuery->where(function ($q) use ($campagne) {
                $q->whereIn('uuid', $campagne->sections_autorisees)
                  ->orWhereIn('nom', $campagne->sections_autorisees);
            });
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
        $paroisseId = $request->user()?->paroisse_configuration_id ?? CatecheseConfiguration::first()?->id;
        $validated = $request->validated();

        $anneeParam = $validated['annee_catechese_id'] ?? null;
        $anneeId = null;
        if ($anneeParam) {
            $anneeId = AnneeCatechese::where('uuid', $anneeParam)
                ->orWhere('id', $anneeParam)
                ->orWhere('libelle', $anneeParam)
                ->value('id');
        }
        if (!$anneeId) {
            $anneeId = AnneeCatechese::getAnneeCourante($paroisseId)?->id ?? AnneeCatechese::first()?->id;
        }

        $validated['annee_catechese_id'] = $anneeId;
        $validated['paroisse_configuration_id'] = $paroisseId;
        $validated['titre'] = $validated['titre'] ?? $validated['nom'] ?? 'Campagne de préinscription';
        $validated['statut'] = $validated['statut'] ?? 'ouverte';
        unset($validated['nom']);

        $campagne = CampagnePreinscription::create($validated);
        $campagne->load('anneeCatechese')->loadCount('preinscriptions');

        return response()->json([
            'status'  => 'success',
            'message' => 'Campagne de préinscription créée avec succès.',
            'data'    => new CampagnePreinscriptionResource($campagne),
        ], 201);
    }

    /**
     * Détails d'une campagne.
     */
    public function show(Request $request, mixed $campagne): JsonResponse
    {
        $model = $this->resolveCampagne($campagne);
        $this->authorizeTenant($request->user()?->paroisse_configuration_id, $model->paroisse_configuration_id);

        $model->load('anneeCatechese')->loadCount('preinscriptions');

        return response()->json([
            'status' => 'success',
            'data'   => new CampagnePreinscriptionResource($model),
        ]);
    }

    /**
     * Mise à jour d'une campagne.
     */
    public function update(UpdateCampagnePreinscriptionRequest $request, mixed $campagne): JsonResponse
    {
        $model = $this->resolveCampagne($campagne);
        $this->authorizeTenant($request->user()?->paroisse_configuration_id, $model->paroisse_configuration_id);
        $validated = $request->validated();

        if (isset($validated['nom'])) {
            $validated['titre'] = $validated['nom'];
            unset($validated['nom']);
        }

        if (!empty($validated['annee_catechese_id'])) {
            $anneeId = AnneeCatechese::where('uuid', $validated['annee_catechese_id'])
                ->orWhere('id', $validated['annee_catechese_id'])
                ->value('id');
            if ($anneeId) {
                $validated['annee_catechese_id'] = $anneeId;
            }
        }

        $model->update($validated);
        $model->load('anneeCatechese')->loadCount('preinscriptions');

        return response()->json([
            'status'  => 'success',
            'message' => 'Campagne mise à jour avec succès.',
            'data'    => new CampagnePreinscriptionResource($model),
        ]);
    }

    /**
     * Basculer le statut d'une campagne.
     */
    public function updateStatus(Request $request, mixed $campagne): JsonResponse
    {
        $model = $this->resolveCampagne($campagne);
        $this->authorizeTenant($request->user()?->paroisse_configuration_id, $model->paroisse_configuration_id);

        $statut = $request->input('statut') ?? $request->input('status');
        if ($request->has('est_ouverte')) {
            $statut = $request->boolean('est_ouverte') ? 'ouverte' : 'fermee';
        }

        if (!$statut) {
            $statut = $model->statut === 'ouverte' ? 'fermee' : 'ouverte';
        }

        $model->update(['statut' => $statut]);
        $model->load('anneeCatechese')->loadCount('preinscriptions');

        return response()->json([
            'status'  => 'success',
            'message' => 'Statut de la campagne mis à jour avec succès.',
            'data'    => new CampagnePreinscriptionResource($model),
        ]);
    }

    /**
     * Suppression d'une campagne.
     */
    public function destroy(Request $request, mixed $campagne): JsonResponse
    {
        $model = $this->resolveCampagne($campagne);
        $this->authorizeTenant($request->user()?->paroisse_configuration_id, $model->paroisse_configuration_id);

        $model->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Campagne supprimée avec succès.',
        ]);
    }

    private function resolveCampagne(mixed $campagne): CampagnePreinscription
    {
        if ($campagne instanceof CampagnePreinscription && $campagne->exists) {
            return $campagne;
        }

        $identifier = is_object($campagne) ? ($campagne->uuid ?? $campagne->id ?? null) : $campagne;

        return CampagnePreinscription::where('uuid', $identifier)
            ->orWhere('id', $identifier)
            ->firstOrFail();
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}

