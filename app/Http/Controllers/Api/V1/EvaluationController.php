<?php

namespace App\Http\Controllers\Api\V1;

use App\DTO\Evaluation\CreateEvaluationDTO;
use App\DTO\Evaluation\SaveNotesBatchDTO;
use App\DTO\Evaluation\UpdateEvaluationDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SaveNotesBatchRequest;
use App\Http\Requests\Api\V1\StoreEvaluationRequest;
use App\Http\Requests\Api\V1\UpdateEvaluationRequest;
use App\Http\Resources\Api\V1\EvaluationResource;
use App\Http\Resources\Api\V1\NoteResource;
use App\Models\AnneeCatechese;
use App\Models\CatecheseConfiguration;
use App\Models\Evaluation;
use App\Models\InscriptionAnnuelle;
use App\Models\ModuleTrimestriel;
use App\Models\Note;
use App\Services\EvaluationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EvaluationController extends Controller
{
    public function __construct(
        protected EvaluationService $evaluationService
    ) {}

    /**
     * Liste des évaluations avec filtrage contextuel (Session, Niveau, Classe, Année, Type, Statut).
     */
    public function index(Request $request): JsonResponse
    {
        $evaluations = $this->evaluationService->getEvaluations($request->user(), $request->all());

        return response()->json([
            'status' => 'success',
            'meta'   => [
                'total_elements' => $evaluations->count(),
            ],
            'data'   => EvaluationResource::collection($evaluations),
        ]);
    }

    /**
     * Créer une nouvelle évaluation avec validation de contexte.
     */
    public function store(StoreEvaluationRequest $request): JsonResponse
    {
        $user = $request->user() ?? auth('sanctum')->user();
        $paroisseId = $user?->paroisse_configuration_id 
            ?? $request->input('paroisse_configuration_id')
            ?? $request->header('X-Paroisse-Id');

        if (!$paroisseId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'L\'identifiant de la paroisse est obligatoire.',
            ], 422);
        }

        $validated = $request->validated();

        // Résolution de l'année pastorale
        $anneeId = null;
        if (!empty($validated['annee_catechese_id'])) {
            $annee = AnneeCatechese::where('paroisse_configuration_id', $paroisseId)
                ->where(function ($q) use ($validated) {
                    $q->where('uuid', $validated['annee_catechese_id'])
                      ->orWhere('libelle', $validated['annee_catechese_id'])
                      ->orWhere('id', $validated['annee_catechese_id']);
                })->first();
            $anneeId = $annee?->id;
        }
        if (!$anneeId) {
            $annee = AnneeCatechese::getAnneeCourante($paroisseId) ?? AnneeCatechese::where('paroisse_configuration_id', $paroisseId)->first();
            $anneeId = $annee?->id;
        }

        // Résolution de la classe
        $classeId = null;
        if (!empty($validated['classe_id'])) {
            $classeId = $this->evaluationService->resolveClasseId($validated['classe_id'], $paroisseId);
        }

        // Résolution du module trimestriel (si renseigné)
        $moduleId = null;
        if (!empty($validated['module_trimestriel_id'])) {
            $moduleId = $this->evaluationService->resolveModuleId($validated['module_trimestriel_id'], $paroisseId);
        } elseif (!empty($validated['periode'])) {
            $periode = $validated['periode'];
            $trimNum = 1;
            if (str_contains($periode, '2')) $trimNum = 2;
            if (str_contains($periode, '3')) $trimNum = 3;
            $module = ModuleTrimestriel::where('paroisse_configuration_id', $paroisseId)
                ->where('numero_trimestre', $trimNum)
                ->first();
            $moduleId = $module?->id;
        }

        $this->checkPeriodOrBilanLock($paroisseId, $moduleId, $validated['date_evaluation'] ?? $validated['date'] ?? null, $classeId);
        $dto = CreateEvaluationDTO::fromArray($validated, $paroisseId, $anneeId, $classeId, $moduleId);
        $evaluation = $this->evaluationService->createEvaluation($user, $dto);

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

        $model->load(['anneeCatechese', 'moduleTrimestriel', 'classe.niveau.section', 'notes.catechumene']);

        return response()->json([
            'status' => 'success',
            'data'   => new EvaluationResource($model),
        ]);
    }

    /**
     * Mettre à jour une évaluation.
     */
    public function update(UpdateEvaluationRequest $request, mixed $evaluation): JsonResponse
    {
        $model = $this->resolveEvaluation($evaluation);
        $user = $request->user();
        $paroisseId = $user->paroisse_configuration_id ?? $model->paroisse_configuration_id;
        $validated = $request->validated();

        $anneeId = null;
        if (!empty($validated['annee_catechese_id'])) {
            $anneeId = AnneeCatechese::where('paroisse_configuration_id', $paroisseId)
                ->where(function ($q) use ($validated) {
                    $q->where('uuid', $validated['annee_catechese_id'])
                      ->orWhere('libelle', $validated['annee_catechese_id'])
                      ->orWhere('id', $validated['annee_catechese_id']);
                })->value('id');
        }

        $classeId = null;
        if (!empty($validated['classe_id'])) {
            $classeId = $this->evaluationService->resolveClasseId($validated['classe_id'], $paroisseId);
        }

        $moduleId = null;
        if (!empty($validated['module_trimestriel_id'])) {
            $moduleId = $this->evaluationService->resolveModuleId($validated['module_trimestriel_id'], $paroisseId);
        } elseif (!empty($validated['periode'])) {
            $trimNum = 1;
            if (str_contains($validated['periode'], '2')) $trimNum = 2;
            if (str_contains($validated['periode'], '3')) $trimNum = 3;
            $module = ModuleTrimestriel::where('paroisse_configuration_id', $paroisseId)
                ->where('numero_trimestre', $trimNum)
                ->first();
            $moduleId = $module?->id;
        }

        $this->checkPeriodOrBilanLock($paroisseId, $moduleId ?? $model->module_trimestriel_id, $validated['date_evaluation'] ?? $validated['date'] ?? $model->date_evaluation?->toDateString(), $classeId ?? $model->classe_id);
        $dto = UpdateEvaluationDTO::fromArray($validated, $anneeId, $classeId, $moduleId);
        $updatedModel = $this->evaluationService->updateEvaluation($user, $model, $dto);

        return response()->json([
            'status'  => 'success',
            'message' => 'Évaluation mise à jour avec succès.',
            'data'    => new EvaluationResource($updatedModel),
        ]);
    }

    /**
     * Basculer le statut d'une évaluation.
     */
    public function toggleStatus(Request $request, mixed $evaluation): JsonResponse
    {
        $model = $this->resolveEvaluation($evaluation);
        $statusInput = $request->input('statut') ?? $request->input('status');

        $updated = $this->evaluationService->toggleStatus($request->user(), $model, $statusInput);

        return response()->json([
            'status'  => 'success',
            'message' => "Le statut de l'évaluation '{$updated->titre}' est désormais " . ucfirst($updated->statut) . ".",
            'data'    => new EvaluationResource($updated),
        ]);
    }

    /**
     * Supprimer une évaluation.
     */
    public function destroy(Request $request, mixed $evaluation): JsonResponse
    {
        $model = $this->resolveEvaluation($evaluation);
        $this->checkPeriodOrBilanLock($model->paroisse_configuration_id, $model->module_trimestriel_id, $model->date_evaluation?->toDateString(), $model->classe_id);
        $this->evaluationService->deleteEvaluation($request->user(), $model);

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
        $grid = $this->evaluationService->getNotesGrid($request->user(), $model, $request->input('search'));

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
     * Saisie en lot des notes pour une évaluation avec validation stricte et recalcul automatique.
     */
    public function notes(SaveNotesBatchRequest $request, mixed $evaluation): JsonResponse
    {
        $model = $this->resolveEvaluation($evaluation);
        $dto = SaveNotesBatchDTO::fromArray($request->validated());

        $this->checkPeriodOrBilanLock($model->paroisse_configuration_id, $model->module_trimestriel_id, $model->date_evaluation?->toDateString(), $model->classe_id);
        $updated = $this->evaluationService->saveBatchNotes($request->user(), $model, $dto);

        return response()->json([
            'status'  => 'success',
            'message' => 'Notes de l\'évaluation enregistrées avec succès.',
            'data'    => new EvaluationResource($updated),
        ]);
    }

    /**
     * Calcul automatique et synthèse des moyennes de toute une classe.
     */
    public function classeMoyennes(Request $request, mixed $classe): JsonResponse
    {
        $result = $this->evaluationService->getClasseMoyennes(
            $request->user(),
            $classe,
            $request->input('annee_catechese_id') ?? $request->input('anneePastorale'),
            $request->input('module_trimestriel_id') ?? $request->input('periode') ?? $request->input('trimestre')
        );

        return response()->json([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * Synthèse des évaluations et moyenne d'un catéchumène individuel.
     */
    public function catechumeneSynthese(Request $request, mixed $catechumene): JsonResponse
    {
        $result = $this->evaluationService->getCatechumeneSynthese(
            $request->user(),
            $catechumene,
            $request->input('annee_catechese_id') ?? $request->input('anneePastorale')
        );

        return response()->json([
            'status' => 'success',
            'data'   => $result,
        ]);
    }

    /**
     * Bouton "Simuler des notes" (Démonstration).
     */
    public function simuler(Request $request, mixed $evaluation): JsonResponse
    {
        $model = $this->resolveEvaluation($evaluation);
        $user = $request->user() ?? auth('sanctum')->user();
        $this->authorizeTenant($user?->paroisse_configuration_id, $model->paroisse_configuration_id);
        $paroisseId = $user?->paroisse_configuration_id ?? $model->paroisse_configuration_id;

        $inscriptions = InscriptionAnnuelle::where('paroisse_configuration_id', $paroisseId)
            ->when($model->classe_id, fn($q) => $q->where('classe_id', $model->classe_id))
            ->get();
            
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

        $model->load(['anneeCatechese', 'moduleTrimestriel', 'classe.niveau.section', 'notes.catechumene']);

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
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé pour cette paroisse.'], 403));
        }
    }

    /**
     * Vérifie si le trimestre ou le bilan de la classe est clôturé / validé.
     */
    private function checkPeriodOrBilanLock(?int $paroisseId, ?int $moduleId, ?string $date, ?int $classeId): void
    {
        if (!$paroisseId) return;

        // 1. Module trimestriel terminé ou clôturé
        if ($moduleId) {
            $module = ModuleTrimestriel::where('paroisse_configuration_id', $paroisseId)
                ->where('id', $moduleId)
                ->first();
            if ($module) {
                $st = strtolower(str_replace(' ', '_', $module->statut ?? ''));
                if (in_array($st, ['termine', 'cloture', 'clos'])) {
                    abort(response()->json([
                        'status'  => 'error',
                        'message' => 'Aucune modification n\'est à effectuer car le bilan est déjà validé.',
                    ], 403));
                }
            }
        }

        // 2. Date comprise dans un trimestre terminé
        if ($date) {
            $dateStr = substr($date, 0, 10);
            $closedModule = ModuleTrimestriel::where('paroisse_configuration_id', $paroisseId)
                ->whereIn('statut', ['termine', 'cloture', 'clos'])
                ->whereDate('date_debut', '<=', $dateStr)
                ->whereDate('date_fin', '>=', $dateStr)
                ->exists();
            if ($closedModule) {
                abort(response()->json([
                    'status'  => 'error',
                    'message' => 'Aucune modification n\'est à effectuer car le bilan est déjà validé.',
                ], 403));
            }
        }

        // 3. Bilan annuel / décisions déjà validées pour la classe
        if ($classeId) {
            $hasBilan = DB::table('decisions_fin_annee')
                ->join('inscriptions_annuelles', 'decisions_fin_annee.inscription_annuelle_id', '=', 'inscriptions_annuelles.id')
                ->where('inscriptions_annuelles.classe_id', $classeId)
                ->whereNull('decisions_fin_annee.deleted_at')
                ->exists();
            if ($hasBilan) {
                abort(response()->json([
                    'status'  => 'error',
                    'message' => 'Aucune modification n\'est à effectuer car le bilan est déjà validé.',
                ], 403));
            }
        }
    }
}
