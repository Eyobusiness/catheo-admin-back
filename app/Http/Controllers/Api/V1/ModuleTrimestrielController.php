<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ModuleTrimestrielResource;
use App\Models\AnneeCatechese;
use App\Models\ModuleTrimestriel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ModuleTrimestrielController extends Controller
{
    /**
     * Liste des modules trimestriels pour l'année pastorale active ou filtrée.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user() ?? auth('sanctum')->user();
        $paroisseId = $user?->paroisse_configuration_id 
            ?? $request->input('paroisse_configuration_id')
            ?? $request->header('X-Paroisse-Id');

        if (!$paroisseId) {
            return response()->json([
                'status' => 'success',
                'meta'   => ['total_elements' => 0],
                'data'   => [],
            ]);
        }

        $query = ModuleTrimestriel::with('anneeCatechese')->where('paroisse_configuration_id', (int) $paroisseId);

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
            $query->where('nom', 'like', "%{$search}%");
        }

        $modules = $query->orderBy('numero_trimestre')->get();

        return response()->json([
            'status' => 'success',
            'meta'   => [
                'total_elements' => $modules->count(),
            ],
            'data'   => ModuleTrimestrielResource::collection($modules),
        ]);
    }

    /**
     * Création d'un module trimestriel.
     */
    public function store(Request $request): JsonResponse
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

        $data = $request->all();

        $anneeInput = $data['annee_catechese_id'] ?? $data['annee_id'] ?? $data['anneeCatecheseId'] ?? ($data['annee_catechese']['id'] ?? null);
        if ($anneeInput) {
            $data['annee_catechese_id'] = $anneeInput;
        }

        $nomInput = $data['nom'] ?? $data['nom_trimestre'] ?? $data['libelle'] ?? $data['titre'] ?? null;
        if ($nomInput !== null) {
            $data['nom'] = $nomInput;
        }

        $numInput = $data['numero_trimestre'] ?? $data['numero'] ?? $data['trimestre'] ?? null;
        if ($numInput !== null) {
            $data['numero_trimestre'] = (int) $numInput;
        }

        $request->merge($data);

        $validated = $request->validate([
            'annee_catechese_id' => ['nullable', 'string'],
            'nom'                => ['required', 'string', 'max:255'],
            'numero_trimestre'   => ['nullable', 'integer', 'min:1', 'max:4'],
            'date_debut'         => ['nullable', 'date'],
            'date_fin'           => ['nullable', 'date', 'after_or_equal:date_debut'],
            'statut'             => ['nullable', 'string'],
        ]);

        if (empty($validated['annee_catechese_id'])) {
            $anneeCourante = AnneeCatechese::getAnneeCourante($paroisseId);
            $validated['annee_catechese_id'] = $anneeCourante?->id;
        } else {
            $annee = AnneeCatechese::where('uuid', $validated['annee_catechese_id'])
                ->orWhere('id', $validated['annee_catechese_id'])
                ->firstOrFail();
            $validated['annee_catechese_id'] = $annee->id;
        }

        $validated['numero_trimestre'] = $validated['numero_trimestre'] ?? (ModuleTrimestriel::where('paroisse_configuration_id', $paroisseId)->where('annee_catechese_id', $validated['annee_catechese_id'])->count() + 1);
        $validated['paroisse_configuration_id'] = $paroisseId;
        $validated['statut'] = strtolower(str_replace(' ', '_', $validated['statut'] ?? 'en_cours'));

        $module = ModuleTrimestriel::create($validated);
        $module->load('anneeCatechese');

        return response()->json([
            'status'  => 'success',
            'message' => 'Module trimestriel créé avec succès.',
            'data'    => new ModuleTrimestrielResource($module),
        ], 201);
    }

    /**
     * Détails d'un module trimestriel.
     */
    public function show(Request $request, mixed $module): JsonResponse
    {
        $model = $this->resolveModule($module);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $model->paroisse_configuration_id);

        $model->load('anneeCatechese');

        return response()->json([
            'status' => 'success',
            'data'   => new ModuleTrimestrielResource($model),
        ]);
    }

    /**
     * Mise à jour d'un module trimestriel.
     */
    public function update(Request $request, mixed $module): JsonResponse
    {
        $model = $this->resolveModule($module);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $model->paroisse_configuration_id);

        $data = $request->all();

        $anneeInput = $data['annee_catechese_id'] ?? $data['annee_id'] ?? $data['anneeCatecheseId'] ?? ($data['annee_catechese']['id'] ?? null);
        if ($anneeInput) {
            $data['annee_catechese_id'] = $anneeInput;
        }

        $nomInput = $data['nom'] ?? $data['nom_trimestre'] ?? $data['libelle'] ?? $data['titre'] ?? null;
        if ($nomInput !== null) {
            $data['nom'] = $nomInput;
        }

        $numInput = $data['numero_trimestre'] ?? $data['numero'] ?? $data['trimestre'] ?? null;
        if ($numInput !== null) {
            $data['numero_trimestre'] = (int) $numInput;
        }

        $request->merge($data);

        $validated = $request->validate([
            'annee_catechese_id' => ['nullable', 'string'],
            'nom'                => ['sometimes', 'required', 'string', 'max:255'],
            'numero_trimestre'   => ['sometimes', 'nullable', 'integer', 'min:1', 'max:4'],
            'date_debut'         => ['nullable', 'date'],
            'date_fin'           => ['nullable', 'date'],
            'statut'             => ['nullable', 'string'],
        ]);

        if (!empty($validated['annee_catechese_id'])) {
            $annee = AnneeCatechese::where('uuid', $validated['annee_catechese_id'])
                ->orWhere('id', $validated['annee_catechese_id'])
                ->first();
            if ($annee) {
                $validated['annee_catechese_id'] = $annee->id;
            }
        }

        if (isset($validated['statut'])) {
            $validated['statut'] = strtolower(str_replace(' ', '_', $validated['statut']));
        }

        $model->update($validated);
        $model->refresh();
        $model->load('anneeCatechese');

        return response()->json([
            'status'  => 'success',
            'message' => 'Module trimestriel mis à jour avec succès.',
            'data'    => new ModuleTrimestrielResource($model),
        ]);
    }

    /**
     * Basculer le statut d'un module trimestriel (en_cours <-> termine).
     */
    public function toggleStatus(Request $request, mixed $module): JsonResponse
    {
        $model = $this->resolveModule($module);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $model->paroisse_configuration_id);

        $current = strtolower($model->statut ?? 'en_cours');
        $nouveauStatut = ($current === 'en_cours' || $current === 'en cours') ? 'termine' : 'en_cours';

        $model->update(['statut' => $nouveauStatut]);
        $model->refresh();
        $model->load('anneeCatechese');

        return response()->json([
            'status'  => 'success',
            'message' => "Le statut du module trimestriel est désormais {$nouveauStatut}.",
            'data'    => new ModuleTrimestrielResource($model),
        ]);
    }

    /**
     * Suppression d'un module trimestriel.
     */
    public function destroy(Request $request, mixed $module): JsonResponse
    {
        $model = $this->resolveModule($module);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $model->paroisse_configuration_id);

        $model->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Module trimestriel supprimé avec succès.',
        ]);
    }

    /**
     * Résout l'instance du modèle depuis un objet injecté, un UUID ou un ID numérique.
     */
    private function resolveModule(mixed $module): ModuleTrimestriel
    {
        if ($module instanceof ModuleTrimestriel && $module->exists) {
            return $module;
        }

        $identifier = is_object($module) ? ($module->uuid ?? $module->id ?? null) : $module;

        return ModuleTrimestriel::where('uuid', $identifier)
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

