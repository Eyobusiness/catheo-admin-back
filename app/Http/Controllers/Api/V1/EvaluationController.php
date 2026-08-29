<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\EvaluationResource;
use App\Http\Resources\Api\V1\NoteResource;
use App\Models\AnneeCatechese;
use App\Models\CatecheseConfiguration;
use App\Models\Catechumene;
use App\Models\Classe;
use App\Models\Evaluation;
use App\Models\InscriptionAnnuelle;
use App\Models\ModuleTrimestriel;
use App\Models\Note;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EvaluationController extends Controller
{
    /**
     * Liste des évaluations avec filtres (recherche, type, statut, classe, année).
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id ?? CatecheseConfiguration::first()?->id;

        $query = Evaluation::with(['anneeCatechese', 'moduleTrimestriel', 'classe.niveau.section', 'notes.catechumene'])
            ->where('paroisse_configuration_id', $paroisseId);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('titre', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $type = $request->input('type_eval') ?? $request->input('type');
        if ($type && strtolower($type) !== 'tous') {
            $query->where('type_eval', strtolower($type));
        }

        $statut = $request->input('statut') ?? $request->input('status');
        if ($statut && strtolower($statut) !== 'tous') {
            $st = strtolower($statut) === 'inactif' ? 'inactif' : 'actif';
            $query->where('statut', $st);
        }

        $anneeParam = $request->input('annee_catechese_id') ?? $request->input('anneePastorale') ?? $request->input('annee_pastorale');
        if ($anneeParam) {
            $anneeId = AnneeCatechese::where('uuid', $anneeParam)
                ->orWhere('libelle', $anneeParam)
                ->orWhere('id', $anneeParam)
                ->value('id');
            if ($anneeId) {
                $query->where('annee_catechese_id', $anneeId);
            }
        }

        $classeParam = $request->input('classe_id') ?? $request->input('classe');
        if ($classeParam) {
            $classeId = Classe::where('uuid', $classeParam)
                ->orWhere('nom', $classeParam)
                ->orWhere('id', $classeParam)
                ->value('id');
            if ($classeId) {
                $query->where('classe_id', $classeId);
            }
        }

        $evaluations = $query->orderBy('date_evaluation', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'meta'   => [
                'total_elements' => $evaluations->count(),
            ],
            'data'   => EvaluationResource::collection($evaluations),
        ]);
    }

    /**
     * Créer une nouvelle évaluation.
     */
    public function store(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id ?? CatecheseConfiguration::first()?->id;

        $data = $request->all();

        // Normalisations
        if (isset($data['nom']) && !isset($data['titre'])) {
            $data['titre'] = $data['nom'];
        }
        if (isset($data['observation']) && !isset($data['description'])) {
            $data['description'] = $data['observation'];
        }
        if (isset($data['type']) && !isset($data['type_eval'])) {
            $data['type_eval'] = strtolower($data['type']);
        }
        if (isset($data['bareme']) && !isset($data['note_max'])) {
            $data['note_max'] = $data['bareme'];
        }
        if (isset($data['date']) && !isset($data['date_evaluation'])) {
            $data['date_evaluation'] = $data['date'];
        }
        if (isset($data['anneePastorale']) && !isset($data['annee_catechese_id'])) {
            $data['annee_catechese_id'] = $data['anneePastorale'];
        }

        $request->merge($data);

        $validated = $request->validate([
            'annee_catechese_id'    => ['nullable', 'string'],
            'module_trimestriel_id' => ['nullable', 'string'],
            'classe_id'             => ['nullable', 'string'],
            'titre'                 => ['required', 'string', 'max:255'],
            'description'           => ['nullable', 'string'],
            'type_eval'             => ['nullable', 'string'],
            'coefficient'           => ['nullable', 'numeric', 'min:0.1', 'max:20'],
            'note_max'              => ['nullable', 'numeric', 'min:1', 'max:100'],
            'date_evaluation'       => ['required', 'date'],
            'statut'                => ['nullable', 'string'],
            'periode'               => ['nullable', 'string'],
        ]);

        $anneeId = null;
        if (!empty($validated['annee_catechese_id'])) {
            $annee = AnneeCatechese::where('uuid', $validated['annee_catechese_id'])
                ->orWhere('libelle', $validated['annee_catechese_id'])
                ->orWhere('id', $validated['annee_catechese_id'])
                ->first();
            $anneeId = $annee?->id;
        }
        if (!$anneeId) {
            $annee = AnneeCatechese::getAnneeCourante($paroisseId) ?? AnneeCatechese::first();
            $anneeId = $annee?->id;
        }

        $moduleId = null;
        if (!empty($validated['module_trimestriel_id'])) {
            $module = ModuleTrimestriel::where('uuid', $validated['module_trimestriel_id'])
                ->orWhere('id', $validated['module_trimestriel_id'])
                ->first();
            $moduleId = $module?->id;
        } elseif (!empty($validated['periode'])) {
            $periode = $validated['periode'];
            $trimNum = 1;
            if (str_contains($periode, '2')) $trimNum = 2;
            if (str_contains($periode, '3')) $trimNum = 3;
            $module = ModuleTrimestriel::where('numero_trimestre', $trimNum)->first();
            $moduleId = $module?->id;
        }

        $classeId = null;
        if (!empty($validated['classe_id'])) {
            $classe = Classe::where('uuid', $validated['classe_id'])
                ->orWhere('nom', $validated['classe_id'])
                ->orWhere('id', $validated['classe_id'])
                ->first();
            $classeId = $classe?->id;
        }

        $statut = strtolower($validated['statut'] ?? 'actif') === 'inactif' ? 'inactif' : 'actif';
        $typeEval = strtolower($validated['type_eval'] ?? 'interrogation');

        $evaluation = Evaluation::create([
            'paroisse_configuration_id' => $paroisseId,
            'annee_catechese_id'        => $anneeId,
            'module_trimestriel_id'     => $moduleId,
            'classe_id'                 => $classeId,
            'titre'                     => $validated['titre'],
            'description'               => $validated['description'] ?? null,
            'type_eval'                 => $typeEval,
            'coefficient'               => $validated['coefficient'] ?? 1.0,
            'note_max'                  => $validated['note_max'] ?? 20.0,
            'date_evaluation'           => $validated['date_evaluation'],
            'statut'                    => $statut,
        ]);

        $evaluation->load(['anneeCatechese', 'moduleTrimestriel', 'classe', 'notes.catechumene']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Évaluation créée avec succès.',
            'data'    => new EvaluationResource($evaluation),
        ], 201);
    }

    /**
     * Obtenir les détails d'une évaluation.
     */
    public function show(Request $request, mixed $evaluation): JsonResponse
    {
        $model = $this->resolveEvaluation($evaluation);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $model->paroisse_configuration_id);

        $model->load(['anneeCatechese', 'moduleTrimestriel', 'classe', 'notes.catechumene']);

        return response()->json([
            'status' => 'success',
            'data'   => new EvaluationResource($model),
        ]);
    }

    /**
     * Mettre à jour une évaluation existante.
     */
    public function update(Request $request, mixed $evaluation): JsonResponse
    {
        $model = $this->resolveEvaluation($evaluation);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $model->paroisse_configuration_id);

        $data = $request->all();

        if (isset($data['nom']) && !isset($data['titre'])) {
            $data['titre'] = $data['nom'];
        }
        if (isset($data['observation']) && !isset($data['description'])) {
            $data['description'] = $data['observation'];
        }
        if (isset($data['type']) && !isset($data['type_eval'])) {
            $data['type_eval'] = strtolower($data['type']);
        }
        if (isset($data['bareme']) && !isset($data['note_max'])) {
            $data['note_max'] = $data['bareme'];
        }
        if (isset($data['date']) && !isset($data['date_evaluation'])) {
            $data['date_evaluation'] = $data['date'];
        }
        if (isset($data['anneePastorale']) && !isset($data['annee_catechese_id'])) {
            $data['annee_catechese_id'] = $data['anneePastorale'];
        }

        $request->merge($data);

        $validated = $request->validate([
            'annee_catechese_id'    => ['sometimes', 'nullable', 'string'],
            'module_trimestriel_id' => ['sometimes', 'nullable', 'string'],
            'classe_id'             => ['sometimes', 'nullable', 'string'],
            'titre'                 => ['sometimes', 'required', 'string', 'max:255'],
            'description'           => ['nullable', 'string'],
            'type_eval'             => ['nullable', 'string'],
            'coefficient'           => ['nullable', 'numeric', 'min:0.1', 'max:20'],
            'note_max'              => ['nullable', 'numeric', 'min:1', 'max:100'],
            'date_evaluation'       => ['sometimes', 'required', 'date'],
            'statut'                => ['nullable', 'string'],
            'periode'               => ['nullable', 'string'],
        ]);

        $updateData = [];

        if (isset($validated['titre'])) {
            $updateData['titre'] = $validated['titre'];
        }
        if (array_key_exists('description', $validated)) {
            $updateData['description'] = $validated['description'];
        }
        if (isset($validated['type_eval'])) {
            $updateData['type_eval'] = strtolower($validated['type_eval']);
        }
        if (isset($validated['coefficient'])) {
            $updateData['coefficient'] = $validated['coefficient'];
        }
        if (isset($validated['note_max'])) {
            $updateData['note_max'] = $validated['note_max'];
        }
        if (isset($validated['date_evaluation'])) {
            $updateData['date_evaluation'] = $validated['date_evaluation'];
        }
        if (isset($validated['statut'])) {
            $updateData['statut'] = strtolower($validated['statut']) === 'inactif' ? 'inactif' : 'actif';
        }

        if (array_key_exists('annee_catechese_id', $validated)) {
            if (!empty($validated['annee_catechese_id'])) {
                $annee = AnneeCatechese::where('uuid', $validated['annee_catechese_id'])
                    ->orWhere('libelle', $validated['annee_catechese_id'])
                    ->orWhere('id', $validated['annee_catechese_id'])
                    ->first();
                $updateData['annee_catechese_id'] = $annee?->id;
            }
        }

        if (array_key_exists('classe_id', $validated)) {
            if (!empty($validated['classe_id'])) {
                $classe = Classe::where('uuid', $validated['classe_id'])
                    ->orWhere('nom', $validated['classe_id'])
                    ->orWhere('id', $validated['classe_id'])
                    ->first();
                $updateData['classe_id'] = $classe?->id;
            } else {
                $updateData['classe_id'] = null;
            }
        }

        if (array_key_exists('module_trimestriel_id', $validated)) {
            if (!empty($validated['module_trimestriel_id'])) {
                $module = ModuleTrimestriel::where('uuid', $validated['module_trimestriel_id'])
                    ->orWhere('id', $validated['module_trimestriel_id'])
                    ->first();
                $updateData['module_trimestriel_id'] = $module?->id;
            }
        } elseif (!empty($validated['periode'])) {
            $periode = $validated['periode'];
            $trimNum = 1;
            if (str_contains($periode, '2')) $trimNum = 2;
            if (str_contains($periode, '3')) $trimNum = 3;
            $module = ModuleTrimestriel::where('numero_trimestre', $trimNum)->first();
            $updateData['module_trimestriel_id'] = $module?->id;
        }

        $model->update($updateData);
        $model->refresh();
        $model->load(['anneeCatechese', 'moduleTrimestriel', 'classe', 'notes.catechumene']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Évaluation mise à jour avec succès.',
            'data'    => new EvaluationResource($model),
        ]);
    }

    /**
     * Basculer le statut d'une évaluation (Actif / Inactif).
     */
    public function toggleStatus(Request $request, mixed $evaluation): JsonResponse
    {
        $model = $this->resolveEvaluation($evaluation);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $model->paroisse_configuration_id);

        $statusInput = $request->input('statut') ?? $request->input('status');
        if ($statusInput) {
            $nouveauStatut = strtolower($statusInput) === 'inactif' ? 'inactif' : 'actif';
        } else {
            $nouveauStatut = ($model->statut === 'actif') ? 'inactif' : 'actif';
        }

        $model->update(['statut' => $nouveauStatut]);
        $model->refresh();
        $model->load(['anneeCatechese', 'moduleTrimestriel', 'classe', 'notes.catechumene']);

        return response()->json([
            'status'  => 'success',
            'message' => "Le statut de l'évaluation '{$model->titre}' est désormais " . ucfirst($nouveauStatut) . ".",
            'data'    => new EvaluationResource($model),
        ]);
    }

    /**
     * Supprimer une évaluation.
     */
    public function destroy(Request $request, mixed $evaluation): JsonResponse
    {
        $model = $this->resolveEvaluation($evaluation);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $model->paroisse_configuration_id);

        DB::transaction(function () use ($model) {
            $model->notes()->delete();
            $model->delete();
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Évaluation supprimée avec succès.',
        ]);
    }

    /**
     * Grille complète des catéchumènes de la classe pour la saisie des notes.
     */
    public function notesGrid(Request $request, mixed $evaluation): JsonResponse
    {
        $model = $this->resolveEvaluation($evaluation);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $model->paroisse_configuration_id);

        $inscriptions = InscriptionAnnuelle::with('catechumene')
            ->when($model->classe_id, fn($q) => $q->where('classe_id', $model->classe_id))
            ->get();

        $existingNotes = Note::where('evaluation_id', $model->id)
            ->get()
            ->keyBy('catechumene_id');

        $grid = $inscriptions->map(function ($inscr) use ($model, $existingNotes) {
            $cat = $inscr->catechumene;
            $note = $cat ? $existingNotes->get($cat->id) : null;
            $noteVal = $note ? (float) $note->note_obtenue : null;
            $appr = $note ? ($note->appreciation ?: Evaluation::calculateAppreciation($noteVal, (float) $model->note_max)) : null;

            return [
                'catechumene_id'   => $cat?->uuid,
                'catechumeneId'    => $cat?->uuid,
                'matricule'        => $cat?->matricule,
                'code_catechumene' => $cat?->matricule,
                'nom'              => $cat?->nom,
                'prenoms'          => $cat?->prenoms,
                'nom_prenoms'      => $cat ? trim("{$cat->nom} {$cat->prenoms}") : null,
                'nomPrenoms'       => $cat ? trim("{$cat->nom} {$cat->prenoms}") : null,
                'note_obtenue'     => $noteVal,
                'note'             => $noteVal,
                'appreciation'     => $appr,
                'note_id'          => $note?->uuid,
            ];
        });

        if ($request->filled('search')) {
            $search = strtolower($request->input('search'));
            $grid = $grid->filter(function ($item) use ($search) {
                return str_contains(strtolower($item['matricule'] ?? ''), $search)
                    || str_contains(strtolower($item['code_catechumene'] ?? ''), $search)
                    || str_contains(strtolower($item['nom_prenoms'] ?? ''), $search);
            })->values();
        }

        return response()->json([
            'status'     => 'success',
            'evaluation' => new EvaluationResource($model->load(['anneeCatechese', 'moduleTrimestriel', 'classe'])),
            'data'       => $grid,
        ]);
    }

    /**
     * Liste des notes d'une évaluation.
     */
    public function getNotes(Request $request, mixed $evaluation): JsonResponse
    {
        $model = $this->resolveEvaluation($evaluation);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $model->paroisse_configuration_id);

        $notes = $model->notes()->with(['catechumene'])->get();

        return response()->json([
            'status' => 'success',
            'data'   => NoteResource::collection($notes),
        ]);
    }

    /**
     * Saisie en lot des notes pour une évaluation.
     */
    public function notes(Request $request, mixed $evaluation): JsonResponse
    {
        $model = $this->resolveEvaluation($evaluation);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $model->paroisse_configuration_id);
        $paroisseId = $request->user()->paroisse_configuration_id ?? $model->paroisse_configuration_id ?? CatecheseConfiguration::first()?->id;

        $notesInput = $request->input('notes', []);
        $noteMax = (float) $model->note_max;

        DB::transaction(function () use ($paroisseId, $model, $notesInput, $noteMax) {
            foreach ($notesInput as $item) {
                $catId = $item['catechumene_id'] ?? $item['catechumeneId'] ?? null;
                if (!$catId) continue;

                $catechumene = Catechumene::where('uuid', $catId)
                    ->orWhere('id', $catId)
                    ->first();
                if (!$catechumene) {
                    continue;
                }

                $rawNote = $item['note_obtenue'] ?? $item['note'] ?? null;
                if ($rawNote === null || $rawNote === '') {
                    // Supprimer la note si vidée
                    Note::where('evaluation_id', $model->id)
                        ->where('catechumene_id', $catechumene->id)
                        ->delete();
                    continue;
                }

                $noteVal = (float) $rawNote;
                $appr = $item['appreciation'] ?? Evaluation::calculateAppreciation($noteVal, $noteMax);

                Note::updateOrCreate(
                    [
                        'paroisse_configuration_id' => $paroisseId,
                        'evaluation_id'             => $model->id,
                        'catechumene_id'            => $catechumene->id,
                    ],
                    [
                        'note_obtenue' => $noteVal,
                        'appreciation' => $appr,
                    ]
                );
            }
        });

        $model->load(['anneeCatechese', 'moduleTrimestriel', 'classe', 'notes.catechumene']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Notes de l\'évaluation enregistrées avec succès.',
            'data'    => new EvaluationResource($model),
        ]);
    }

    /**
     * Bouton "Simuler des notes" : préremplit des notes de démonstration.
     */
    public function simuler(Request $request, mixed $evaluation): JsonResponse
    {
        $model = $this->resolveEvaluation($evaluation);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $model->paroisse_configuration_id);
        $paroisseId = $request->user()->paroisse_configuration_id ?? $model->paroisse_configuration_id ?? CatecheseConfiguration::first()?->id;

        $inscriptions = InscriptionAnnuelle::when($model->classe_id, fn($q) => $q->where('classe_id', $model->classe_id))->get();
        $noteMax = (float) $model->note_max;

        DB::transaction(function () use ($paroisseId, $model, $inscriptions, $noteMax) {
            foreach ($inscriptions as $inscr) {
                $randNote = round(rand(80, 195) / 10.0, 1);
                if ($noteMax != 20.0) {
                    $randNote = round(($randNote / 20.0) * $noteMax, 1);
                }

                $appreciation = Evaluation::calculateAppreciation($randNote, $noteMax);

                Note::updateOrCreate(
                    [
                        'paroisse_configuration_id' => $paroisseId,
                        'evaluation_id'             => $model->id,
                        'catechumene_id'            => $inscr->catechumene_id,
                    ],
                    [
                        'note_obtenue' => $randNote,
                        'appreciation' => $appreciation,
                    ]
                );
            }
        });

        $model->load(['anneeCatechese', 'moduleTrimestriel', 'classe', 'notes.catechumene']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Notes simulées générées avec succès.',
            'data'    => new EvaluationResource($model),
        ]);
    }

    /**
     * Résout l'instance du modèle depuis un objet, UUID ou ID.
     */
    private function resolveEvaluation(mixed $evaluation): Evaluation
    {
        if ($evaluation instanceof Evaluation && $evaluation->exists) {
            return $evaluation;
        }

        $identifier = is_object($evaluation) ? ($evaluation->uuid ?? $evaluation->id ?? null) : $evaluation;

        return Evaluation::where('uuid', $identifier)
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

