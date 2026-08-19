<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BatchNoteRequest;
use App\Http\Requests\Api\V1\StoreEvaluationRequest;
use App\Http\Requests\Api\V1\UpdateEvaluationRequest;
use App\Http\Resources\Api\V1\EvaluationResource;
use App\Models\AnneeCatechese;
use App\Models\Catechumene;
use App\Models\Classe;
use App\Models\Evaluation;
use App\Models\InscriptionAnnuelle;
use App\Models\ModuleTrimestriel;
use App\Models\Note;
use App\Traits\HasPaginatedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EvaluationController extends Controller
{
    use HasPaginatedResponse;
    /**
     * Liste des évaluations / devoirs avec filtres (recherche, type, statut, classe, année).
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $query = Evaluation::with(['anneeCatechese', 'moduleTrimestriel', 'classe'])
            ->where('paroisse_configuration_id', $paroisseId);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('titre', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('type_eval') && strtolower($request->input('type_eval')) !== 'tous') {
            $query->where('type_eval', strtolower($request->input('type_eval')));
        }

        if ($request->filled('statut') && strtolower($request->input('statut')) !== 'tous') {
            $query->where('statut', strtolower($request->input('statut')));
        }

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

        $evaluations = $query->latest('date_evaluation')->paginate($this->getPerPage($request));

        return $this->paginatedResponse($evaluations, EvaluationResource::class);
    }

    /**
     * Créer une nouvelle évaluation.
     */
    public function store(StoreEvaluationRequest $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;
        $validated = $request->validated();

        $annee = AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->firstOrFail();
        $module = ModuleTrimestriel::where('uuid', $validated['module_trimestriel_id'])->firstOrFail();
        $classe = Classe::where('uuid', $validated['classe_id'])->firstOrFail();

        $validated['paroisse_configuration_id'] = $paroisseId;
        $validated['annee_catechese_id'] = $annee->id;
        $validated['module_trimestriel_id'] = $module->id;
        $validated['classe_id'] = $classe->id;

        $evaluation = Evaluation::create($validated);
        $evaluation->load(['anneeCatechese', 'moduleTrimestriel', 'classe']);

        return response()->json([
            'status' => 'success',
            'message' => 'Évaluation créée avec succès.',
            'data' => new EvaluationResource($evaluation),
        ], 201);
    }

    /**
     * Obtenir les détails et KPIs statistiques d'une évaluation.
     */
    public function show(Request $request, Evaluation $evaluation): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $evaluation->paroisse_configuration_id);

        $evaluation->load(['anneeCatechese', 'moduleTrimestriel', 'classe', 'notes.catechumene']);

        return response()->json([
            'status' => 'success',
            'data' => new EvaluationResource($evaluation),
        ]);
    }

    /**
     * Mettre à jour une évaluation existante.
     */
    public function update(UpdateEvaluationRequest $request, Evaluation $evaluation): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $evaluation->paroisse_configuration_id);
        $validated = $request->validated();

        if (isset($validated['annee_catechese_id'])) {
            $validated['annee_catechese_id'] = AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->value('id');
        }
        if (isset($validated['module_trimestriel_id'])) {
            $validated['module_trimestriel_id'] = ModuleTrimestriel::where('uuid', $validated['module_trimestriel_id'])->value('id');
        }
        if (isset($validated['classe_id'])) {
            $validated['classe_id'] = Classe::where('uuid', $validated['classe_id'])->value('id');
        }

        $evaluation->update($validated);
        $evaluation->load(['anneeCatechese', 'moduleTrimestriel', 'classe']);

        return response()->json([
            'status' => 'success',
            'message' => 'Évaluation mise à jour avec succès.',
            'data' => new EvaluationResource($evaluation),
        ]);
    }

    /**
     * Basculer le statut d'une évaluation (Actif / Inactif).
     */
    public function toggleStatus(Request $request, Evaluation $evaluation): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $evaluation->paroisse_configuration_id);

        $nouveauStatut = ($evaluation->statut === 'actif') ? 'inactif' : 'actif';
        $evaluation->update(['statut' => $nouveauStatut]);

        return response()->json([
            'status' => 'success',
            'message' => "Le statut de l'évaluation '{$evaluation->titre}' est désormais " . ucfirst($nouveauStatut) . ".",
            'data' => new EvaluationResource($evaluation),
        ]);
    }

    /**
     * Supprimer une évaluation.
     */
    public function destroy(Request $request, Evaluation $evaluation): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $evaluation->paroisse_configuration_id);

        $evaluation->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Évaluation supprimée avec succès.',
        ]);
    }

    /**
     * Grille complète des catéchumènes de la classe pour la saisie des notes.
     */
    public function notesGrid(Request $request, Evaluation $evaluation): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $evaluation->paroisse_configuration_id);

        $inscriptions = InscriptionAnnuelle::with('catechumene')
            ->where('classe_id', $evaluation->classe_id)
            ->get();

        $existingNotes = Note::where('evaluation_id', $evaluation->id)
            ->get()
            ->keyBy('catechumene_id');

        $grid = $inscriptions->map(function ($inscr) use ($evaluation, $existingNotes) {
            $cat = $inscr->catechumene;
            $note = $cat ? $existingNotes->get($cat->id) : null;
            $noteVal = $note ? (float) $note->note_obtenue : null;
            $appr = $note ? ($note->appreciation ?: Evaluation::calculateAppreciation($noteVal, (float) $evaluation->note_max)) : null;

            return [
                'catechumene_id' => $cat?->uuid,
                'code_catechumene' => $cat?->code_catechumene,
                'nom' => $cat?->nom,
                'prenoms' => $cat?->prenoms,
                'nom_prenoms' => $cat ? trim("{$cat->nom} {$cat->prenoms}") : null,
                'note_obtenue' => $noteVal,
                'appreciation' => $appr,
                'note_id' => $note?->uuid,
            ];
        });

        if ($request->filled('search')) {
            $search = strtolower($request->input('search'));
            $grid = $grid->filter(function ($item) use ($search) {
                return str_contains(strtolower($item['code_catechumene'] ?? ''), $search)
                    || str_contains(strtolower($item['nom_prenoms'] ?? ''), $search);
            })->values();
        }

        return response()->json([
            'status' => 'success',
            'evaluation' => new EvaluationResource($evaluation->load(['anneeCatechese', 'moduleTrimestriel', 'classe'])),
            'data' => $grid,
        ]);
    }

    /**
     * Saisie en lot des notes pour une évaluation.
     */
    public function notes(BatchNoteRequest $request, Evaluation $evaluation): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $evaluation->paroisse_configuration_id);
        $paroisseId = $request->user()->paroisse_configuration_id;

        $validated = $request->validated();
        $noteMax = (float) $evaluation->note_max;

        DB::transaction(function () use ($paroisseId, $evaluation, $validated, $noteMax) {
            foreach ($validated['notes'] as $item) {
                $catechumene = Catechumene::where('uuid', $item['catechumene_id'])->first();
                if (!$catechumene) {
                    continue;
                }

                $noteVal = (float) $item['note_obtenue'];
                $appr = $item['appreciation'] ?? Evaluation::calculateAppreciation($noteVal, $noteMax);

                Note::updateOrCreate(
                    [
                        'paroisse_configuration_id' => $paroisseId,
                        'evaluation_id' => $evaluation->id,
                        'catechumene_id' => $catechumene->id,
                    ],
                    [
                        'note_obtenue' => $noteVal,
                        'appreciation' => $appr,
                    ]
                );
            }
        });

        $evaluation->load(['anneeCatechese', 'moduleTrimestriel', 'classe', 'notes.catechumene']);

        return response()->json([
            'status' => 'success',
            'message' => 'Notes de l\'évaluation enregistrées avec succès.',
            'data' => new EvaluationResource($evaluation),
        ]);
    }

    /**
     * Bouton "Simuler des notes" : préremplit des notes de démonstration.
     */
    public function simuler(Request $request, Evaluation $evaluation): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $evaluation->paroisse_configuration_id);
        $paroisseId = $request->user()->paroisse_configuration_id;

        $inscriptions = InscriptionAnnuelle::where('classe_id', $evaluation->classe_id)->get();
        $noteMax = (float) $evaluation->note_max;

        DB::transaction(function () use ($paroisseId, $evaluation, $inscriptions, $noteMax) {
            foreach ($inscriptions as $inscr) {
                $randNote = round(rand(80, 195) / 10.0, 1);
                if ($noteMax != 20.0) {
                    $randNote = round(($randNote / 20.0) * $noteMax, 1);
                }

                $appreciation = Evaluation::calculateAppreciation($randNote, $noteMax);

                Note::updateOrCreate(
                    [
                        'paroisse_configuration_id' => $paroisseId,
                        'evaluation_id' => $evaluation->id,
                        'catechumene_id' => $inscr->catechumene_id,
                    ],
                    [
                        'note_obtenue' => $randNote,
                        'appreciation' => $appreciation,
                    ]
                );
            }
        });

        $evaluation->load(['anneeCatechese', 'moduleTrimestriel', 'classe', 'notes.catechumene']);

        return response()->json([
            'status' => 'success',
            'message' => 'Notes simulées générées avec succès.',
            'data' => new EvaluationResource($evaluation),
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
