<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\NiveauResource;
use App\Models\Niveau;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NiveauController extends Controller
{
    /**
     * Liste des niveaux avec filtres (recherche, section et statut) conformes à la capture d'écran.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $query = Niveau::with('section')->where('paroisse_configuration_id', $paroisseId);

        // Recherche textuelle ("Rechercher un niveau...")
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filtre par section ("Toutes les sections" ou section_id)
        if ($request->filled('section_id') && $request->input('section_id') !== 'all') {
            $sectionId = Section::where('uuid', $request->input('section_id'))->value('id');
            if ($sectionId) {
                $query->where('section_id', $sectionId);
            }
        }

        // Filtre par statut ("Tous", "Actif", "Inactif")
        if ($request->filled('statut') && strtolower($request->input('statut')) !== 'tous') {
            $query->where('statut', strtolower($request->input('statut')));
        }

        $niveaux = $query->orderBy('ordre_affichage')->get();

        return response()->json([
            'status' => 'success',
            'meta' => [
                'total_elements' => $niveaux->count(),
            ],
            'data' => NiveauResource::collection($niveaux),
        ]);
    }

    /**
     * Création d'un niveau (+ Nouveau).
     */
    public function store(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $validated = $request->validate([
            'section_id' => ['required', 'string', 'exists:sections,uuid'],
            'nom' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'statut' => ['nullable', 'string', 'in:actif,inactif'],
            'duree_annees' => ['nullable', 'integer', 'min:1'],
            'ordre_affichage' => ['nullable', 'integer'],
        ]);

        $section = Section::where('uuid', $validated['section_id'])->firstOrFail();
        $validated['section_id'] = $section->id;
        $validated['paroisse_configuration_id'] = $paroisseId;
        $validated['statut'] = $validated['statut'] ?? 'actif';

        $niveau = Niveau::create($validated);
        $niveau->load('section');

        return response()->json([
            'status' => 'success',
            'message' => 'Niveau créé avec succès.',
            'data' => new NiveauResource($niveau),
        ], 201);
    }

    /**
     * Détails d'un niveau.
     */
    public function show(Request $request, Niveau $niveau): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $niveau->paroisse_configuration_id);

        $niveau->load('section');

        return response()->json([
            'status' => 'success',
            'data' => new NiveauResource($niveau),
        ]);
    }

    /**
     * Mise à jour d'un niveau (Action Modifier ✏️).
     */
    public function update(Request $request, Niveau $niveau): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $niveau->paroisse_configuration_id);

        $validated = $request->validate([
            'section_id' => ['sometimes', 'required', 'string', 'exists:sections,uuid'],
            'nom' => ['sometimes', 'required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'statut' => ['nullable', 'string', 'in:actif,inactif'],
            'duree_annees' => ['nullable', 'integer', 'min:1'],
            'ordre_affichage' => ['nullable', 'integer'],
        ]);

        if (!empty($validated['section_id'])) {
            $section = Section::where('uuid', $validated['section_id'])->firstOrFail();
            $validated['section_id'] = $section->id;
        }

        $niveau->update($validated);
        $niveau->load('section');

        return response()->json([
            'status' => 'success',
            'message' => 'Niveau mis à jour avec succès.',
            'data' => new NiveauResource($niveau),
        ]);
    }

    /**
     * Basculer le statut d'un niveau (Action Toggle 🔘 Actif <-> Inactif).
     */
    public function toggleStatus(Request $request, Niveau $niveau): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $niveau->paroisse_configuration_id);

        $nouveauStatut = ($niveau->statut === 'actif') ? 'inactif' : 'actif';
        $niveau->update(['statut' => $nouveauStatut]);
        $niveau->load('section');

        return response()->json([
            'status' => 'success',
            'message' => "Le statut du niveau '{$niveau->nom}' est désormais " . ucfirst($nouveauStatut) . ".",
            'data' => new NiveauResource($niveau),
        ]);
    }

    /**
     * Suppression d'un niveau (Action Supprimer 🗑️).
     */
    public function destroy(Request $request, Niveau $niveau): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $niveau->paroisse_configuration_id);

        if ($niveau->classes()->count() > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Impossible de supprimer un niveau auquel des classes sont rattachées.',
            ], 422);
        }

        $niveau->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Niveau supprimé avec succès.',
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
