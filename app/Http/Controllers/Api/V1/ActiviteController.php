<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreActiviteRequest;
use App\Http\Resources\Api\V1\ActiviteResource;
use App\Models\Activite;
use App\Models\Animateur;
use App\Models\AnneeCatechese;
use App\Models\Classe;
use App\Models\Niveau;
use App\Models\Section;
use App\Models\TypeActivite;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActiviteController extends Controller
{
    /**
     * Liste des activités (calendrier/agenda) avec filtres (date, type, section, niveau, classe, animateur).
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $query = Activite::with(['typeActivite', 'anneeCatechese', 'sections', 'niveaux', 'classes', 'animateurs'])
            ->where('paroisse_configuration_id', $paroisseId);

        if ($request->filled('annee_catechese_id')) {
            $anneeId = AnneeCatechese::where('uuid', $request->annee_catechese_id)->value('id');
            if ($anneeId) {
                $query->where('annee_catechese_id', $anneeId);
            }
        }

        if ($request->filled('type_activite_id')) {
            $typeId = TypeActivite::where('uuid', $request->type_activite_id)->value('id');
            if ($typeId) {
                $query->where('type_activite_id', $typeId);
            }
        }

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('date_debut')) {
            $query->whereDate('date_debut', '>=', $request->date_debut);
        }

        if ($request->filled('date_fin')) {
            $query->whereDate('date_debut', '<=', $request->date_fin);
        }

        $perPage = (int) $request->get('per_page', 15);
        $activites = $query->latest('date_debut')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => ActiviteResource::collection($activites->items()),
            'meta' => [
                'current_page' => $activites->currentPage(),
                'last_page' => $activites->lastPage(),
                'per_page' => $activites->perPage(),
                'total' => $activites->total(),
            ],
        ]);
    }

    /**
     * Créer une activité ou événement au calendrier.
     */
    public function store(StoreActiviteRequest $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;
        $validated = $request->validated();

        $annee = AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->firstOrFail();
        $type = TypeActivite::where('uuid', $validated['type_activite_id'])->firstOrFail();

        $validated['paroisse_configuration_id'] = $paroisseId;
        $validated['annee_catechese_id'] = $annee->id;
        $validated['type_activite_id'] = $type->id;

        $sectionsUuids = $validated['sections_uuids'] ?? [];
        $niveauxUuids = $validated['niveaux_uuids'] ?? [];
        $classesUuids = $validated['classes_uuids'] ?? [];
        $animateursUuids = $validated['animateurs_uuids'] ?? [];

        unset($validated['sections_uuids'], $validated['niveaux_uuids'], $validated['classes_uuids'], $validated['animateurs_uuids']);

        $activite = Activite::create($validated);

        if (!empty($sectionsUuids)) {
            $sectionIds = Section::whereIn('uuid', $sectionsUuids)->pluck('id');
            $activite->sections()->sync($sectionIds);
        }

        if (!empty($niveauxUuids)) {
            $niveauIds = Niveau::whereIn('uuid', $niveauxUuids)->pluck('id');
            $activite->niveaux()->sync($niveauIds);
        }

        if (!empty($classesUuids)) {
            $classeIds = Classe::whereIn('uuid', $classesUuids)->pluck('id');
            $activite->classes()->sync($classeIds);
        }

        if (!empty($animateursUuids)) {
            $animateurIds = Animateur::whereIn('uuid', $animateursUuids)->pluck('id');
            $activite->animateurs()->sync($animateurIds);
        }

        $activite->load(['typeActivite', 'anneeCatechese', 'sections', 'niveaux', 'classes', 'animateurs']);

        return response()->json([
            'status' => 'success',
            'message' => 'Activité / Événement planifié avec succès.',
            'data' => new ActiviteResource($activite),
        ], 201);
    }

    /**
     * Détails d'une activité.
     */
    public function show(Request $request, Activite $activite): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $activite->paroisse_configuration_id);

        $activite->load(['typeActivite', 'anneeCatechese', 'sections', 'niveaux', 'classes', 'animateurs']);

        return response()->json([
            'status' => 'success',
            'data' => new ActiviteResource($activite),
        ]);
    }

    /**
     * Mettre à jour une activité.
     */
    public function update(StoreActiviteRequest $request, Activite $activite): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $activite->paroisse_configuration_id);
        $validated = $request->validated();

        if (!empty($validated['annee_catechese_id'])) {
            $annee = AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->firstOrFail();
            $validated['annee_catechese_id'] = $annee->id;
        }

        if (!empty($validated['type_activite_id'])) {
            $type = TypeActivite::where('uuid', $validated['type_activite_id'])->firstOrFail();
            $validated['type_activite_id'] = $type->id;
        }

        $sectionsUuids = $validated['sections_uuids'] ?? null;
        $niveauxUuids = $validated['niveaux_uuids'] ?? null;
        $classesUuids = $validated['classes_uuids'] ?? null;
        $animateursUuids = $validated['animateurs_uuids'] ?? null;

        unset($validated['sections_uuids'], $validated['niveaux_uuids'], $validated['classes_uuids'], $validated['animateurs_uuids']);

        $activite->update($validated);

        if (is_array($sectionsUuids)) {
            $sectionIds = Section::whereIn('uuid', $sectionsUuids)->pluck('id');
            $activite->sections()->sync($sectionIds);
        }

        if (is_array($niveauxUuids)) {
            $niveauIds = Niveau::whereIn('uuid', $niveauxUuids)->pluck('id');
            $activite->niveaux()->sync($niveauIds);
        }

        if (is_array($classesUuids)) {
            $classeIds = Classe::whereIn('uuid', $classesUuids)->pluck('id');
            $activite->classes()->sync($classeIds);
        }

        if (is_array($animateursUuids)) {
            $animateurIds = Animateur::whereIn('uuid', $animateursUuids)->pluck('id');
            $activite->animateurs()->sync($animateurIds);
        }

        $activite->load(['typeActivite', 'anneeCatechese', 'sections', 'niveaux', 'classes', 'animateurs']);

        return response()->json([
            'status' => 'success',
            'message' => 'Activité mise à jour avec succès.',
            'data' => new ActiviteResource($activite),
        ]);
    }

    /**
     * Activer / Désactiver / Changer le statut d'une activité.
     */
    public function updateStatus(Request $request, Activite $activite): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $activite->paroisse_configuration_id);

        $validated = $request->validate([
            'statut' => ['required', 'string', 'in:planifiee,en_cours,terminee,annulee'],
        ]);

        $activite->update(['statut' => $validated['statut']]);

        return response()->json([
            'status' => 'success',
            'message' => 'Statut de l\'activité mis à jour.',
            'data' => new ActiviteResource($activite),
        ]);
    }

    /**
     * Supprimer une activité.
     */
    public function destroy(Request $request, Activite $activite): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $activite->paroisse_configuration_id);

        $activite->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Activité supprimée avec succès.',
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
