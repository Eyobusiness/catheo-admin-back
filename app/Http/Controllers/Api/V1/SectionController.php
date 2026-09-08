<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SectionResource;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    /**
     * Liste des sections avec filtres (recherche & statut) conforme à la capture d'écran.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()?->paroisse_configuration_id ?? \App\Models\CatecheseConfiguration::first()?->id;

        $query = Section::with('niveaux')
            ->where('paroisse_configuration_id', $paroisseId);

        // Recherche par nom ou code ("Rechercher une section...")
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filtre par statut ("Tous", "Actif", "Inactif")
        if ($request->filled('statut') && strtolower($request->input('statut')) !== 'tous') {
            $query->where('statut', strtolower($request->input('statut')));
        }

        $sections = $query->orderBy('ordre_affichage')->get();

        return response()->json([
            'status' => 'success',
            'meta' => [
                'total_elements' => $sections->count(),
            ],
            'data' => SectionResource::collection($sections),
        ]);
    }

    /**
     * Création d'une nouvelle section (+ Nouveau).
     */
    public function store(Request $request): JsonResponse
    {
        $paroisseId = $request->user()?->paroisse_configuration_id ?? \App\Models\CatecheseConfiguration::first()?->id;

        $validated = $request->validate([
            'nom' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'statut' => ['nullable', 'string', 'in:actif,inactif'],
            'ordre_affichage' => ['nullable', 'integer'],
        ]);

        $validated['paroisse_configuration_id'] = $paroisseId;
        $validated['statut'] = $validated['statut'] ?? 'actif';

        $section = Section::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Section créée avec succès.',
            'data' => new SectionResource($section),
        ], 201);
    }

    /**
     * Détails d'une section par son UUID.
     */
    public function show(Request $request, Section $section): JsonResponse
    {
        $this->authorizeTenant($request->user()?->paroisse_configuration_id ?? \App\Models\CatecheseConfiguration::first()?->id, $section->paroisse_configuration_id);

        $section->load('niveaux');

        return response()->json([
            'status' => 'success',
            'data' => new SectionResource($section),
        ]);
    }

    /**
     * Mise à jour d'une section (Action Modifier ✏️).
     */
    public function update(Request $request, Section $section): JsonResponse
    {
        $this->authorizeTenant($request->user()?->paroisse_configuration_id ?? \App\Models\CatecheseConfiguration::first()?->id, $section->paroisse_configuration_id);

        $validated = $request->validate([
            'nom' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => ['sometimes', 'required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'statut' => ['nullable', 'string', 'in:actif,inactif'],
            'ordre_affichage' => ['nullable', 'integer'],
        ]);

        $section->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Section mise à jour avec succès.',
            'data' => new SectionResource($section),
        ]);
    }

    /**
     * Basculer le statut d'une section (Action Toggle 🔘 Actif <-> Inactif).
     */
    public function toggleStatus(Request $request, Section $section): JsonResponse
    {
        $this->authorizeTenant($request->user()?->paroisse_configuration_id ?? \App\Models\CatecheseConfiguration::first()?->id, $section->paroisse_configuration_id);

        $nouveauStatut = ($section->statut === 'actif') ? 'inactif' : 'actif';
        $section->update(['statut' => $nouveauStatut]);

        return response()->json([
            'status' => 'success',
            'message' => "Le statut de la section '{$section->nom}' est désormais " . ucfirst($nouveauStatut) . ".",
            'data' => new SectionResource($section),
        ]);
    }

    /**
     * Suppression (SoftDelete) d'une section (Action Supprimer 🗑️).
     */
    public function destroy(Request $request, Section $section): JsonResponse
    {
        $this->authorizeTenant($request->user()?->paroisse_configuration_id ?? \App\Models\CatecheseConfiguration::first()?->id, $section->paroisse_configuration_id);

        if ($section->niveaux()->count() > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Impossible de supprimer une section contenant des niveaux rattachés.',
            ], 422);
        }

        $section->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Section supprimée avec succès.',
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
