<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AffectationAnimateurResource;
use App\Models\AffectationAnimateur;
use App\Models\Animateur;
use App\Models\AnneeCatechese;
use App\Models\CatecheseConfiguration;
use App\Models\Classe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AffectationAnimateurController extends Controller
{
    /**
     * Liste des affectations d'animateurs avec filtres par année, classe, animateur ou recherche.
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

        $query = AffectationAnimateur::with(['animateur', 'anneeCatechese', 'classe'])
            ->where('paroisse_configuration_id', (int) $paroisseId);

        if ($request->filled('annee_catechese_id')) {
            $anneeId = AnneeCatechese::where('uuid', $request->annee_catechese_id)
                ->orWhere('id', $request->annee_catechese_id)
                ->value('id');
            if ($anneeId) {
                $query->where('annee_catechese_id', $anneeId);
            }
        }

        if ($request->filled('classe_id')) {
            $classeId = Classe::where('uuid', $request->classe_id)
                ->orWhere('id', $request->classe_id)
                ->value('id');
            if ($classeId) {
                $query->where('classe_id', $classeId);
            }
        }

        if ($request->filled('animateur_id')) {
            $animateurId = Animateur::where('uuid', $request->animateur_id)
                ->orWhere('id', $request->animateur_id)
                ->value('id');
            if ($animateurId) {
                $query->where('animateur_id', $animateurId);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('animateur', function ($qa) use ($search) {
                    $qa->where('nom', 'like', "%{$search}%")
                       ->orWhere('prenoms', 'like', "%{$search}%")
                       ->orWhere('telephone', 'like', "%{$search}%");
                })->orWhereHas('classe', function ($qc) use ($search) {
                    $qc->where('nom', 'like', "%{$search}%");
                });
            });
        }

        $affectations = $query->latest()->get();

        return response()->json([
            'status' => 'success',
            'meta'   => [
                'total_elements' => $affectations->count(),
            ],
            'data'   => AffectationAnimateurResource::collection($affectations),
        ]);
    }

    /**
     * Affecter un animateur à une classe pour une année pastorale.
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

        // Normalisation animateur
        $animateurInput = $data['animateur_id'] ?? $data['animateur_uuid'] ?? ($data['animateur']['id'] ?? null);
        if ($animateurInput) {
            $data['animateur_id'] = $animateurInput;
        }

        // Normalisation année
        $anneeInput = $data['annee_catechese_id'] ?? $data['annee_id'] ?? ($data['annee_catechese']['id'] ?? null);
        if ($anneeInput) {
            $data['annee_catechese_id'] = $anneeInput;
        }

        // Normalisation classe
        $classeInput = $data['classe_id'] ?? ($data['classe']['id'] ?? null);
        if ($classeInput) {
            $data['classe_id'] = $classeInput;
        }

        // Normalisation role
        $roleInput = $data['role_animateur'] ?? $data['role'] ?? 'principal';
        $data['role_animateur'] = strtolower($roleInput);

        $request->merge($data);

        $validated = $request->validate([
            'animateur_id'       => ['required', 'string'],
            'annee_catechese_id' => ['nullable', 'string'],
            'classe_id'          => ['nullable', 'string'],
            'role_animateur'     => ['nullable', 'string'],
        ]);

        $animateur = Animateur::where('uuid', $validated['animateur_id'])
            ->orWhere('id', $validated['animateur_id'])
            ->firstOrFail();

        if (empty($validated['annee_catechese_id'])) {
            $annee = AnneeCatechese::getAnneeCourante($paroisseId);
            if (!$annee) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Aucune année pastorale active trouvée pour cette paroisse. Veuillez préciser l\'année pastorale.',
                ], 422);
            }
        } else {
            $annee = AnneeCatechese::where('paroisse_configuration_id', $paroisseId)
                ->where(function ($q) use ($validated) {
                    $q->where('uuid', $validated['annee_catechese_id'])
                      ->orWhere('id', $validated['annee_catechese_id']);
                })
                ->firstOrFail();
        }

        $validated['animateur_id'] = $animateur->id;
        $validated['annee_catechese_id'] = $annee->id;
        $validated['paroisse_configuration_id'] = $paroisseId;
        $validated['role_animateur'] = strtolower($validated['role_animateur'] ?? 'principal');

        if (!empty($validated['classe_id'])) {
            $classe = Classe::where('uuid', $validated['classe_id'])
                ->orWhere('id', $validated['classe_id'])
                ->firstOrFail();
            $validated['classe_id'] = $classe->id;
        } else {
            $validated['classe_id'] = null;
        }

        $affectation = AffectationAnimateur::create($validated);
        $affectation->load(['animateur', 'anneeCatechese', 'classe']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Animateur affecté avec succès.',
            'data'    => new AffectationAnimateurResource($affectation),
        ], 201);
    }

    /**
     * Détails d'une affectation.
     */
    public function show(Request $request, mixed $affectation): JsonResponse
    {
        $model = $this->resolveAffectation($affectation);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $model->paroisse_configuration_id);

        $model->load(['animateur', 'anneeCatechese', 'classe']);

        return response()->json([
            'status' => 'success',
            'data'   => new AffectationAnimateurResource($model),
        ]);
    }

    /**
     * Mise à jour d'une affectation.
     */
    public function update(Request $request, mixed $affectation): JsonResponse
    {
        $model = $this->resolveAffectation($affectation);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $model->paroisse_configuration_id);

        $data = $request->all();

        if (isset($data['role']) && !isset($data['role_animateur'])) {
            $data['role_animateur'] = $data['role'];
        }
        if (isset($data['classe']['id']) && !isset($data['classe_id'])) {
            $data['classe_id'] = $data['classe']['id'];
        }
        if (isset($data['animateur']['id']) && !isset($data['animateur_id'])) {
            $data['animateur_id'] = $data['animateur']['id'];
        }
        if (isset($data['annee_catechese']['id']) && !isset($data['annee_catechese_id'])) {
            $data['annee_catechese_id'] = $data['annee_catechese']['id'];
        }

        $request->merge($data);

        $validated = $request->validate([
            'animateur_id'       => ['nullable', 'string'],
            'annee_catechese_id' => ['nullable', 'string'],
            'classe_id'          => ['nullable', 'string'],
            'role_animateur'     => ['nullable', 'string', 'max:50'],
        ]);

        if (array_key_exists('animateur_id', $validated)) {
            if (!empty($validated['animateur_id'])) {
                $animateur = Animateur::where('uuid', $validated['animateur_id'])
                    ->orWhere('id', $validated['animateur_id'])
                    ->first();
                $validated['animateur_id'] = $animateur?->id;
            } else {
                unset($validated['animateur_id']);
            }
        }

        if (array_key_exists('annee_catechese_id', $validated)) {
            if (!empty($validated['annee_catechese_id'])) {
                $annee = AnneeCatechese::where('uuid', $validated['annee_catechese_id'])
                    ->orWhere('id', $validated['annee_catechese_id'])
                    ->first();
                $validated['annee_catechese_id'] = $annee?->id;
            } else {
                unset($validated['annee_catechese_id']);
            }
        }

        if (array_key_exists('classe_id', $validated)) {
            if (!empty($validated['classe_id'])) {
                $classe = Classe::where('uuid', $validated['classe_id'])
                    ->orWhere('id', $validated['classe_id'])
                    ->first();
                $validated['classe_id'] = $classe?->id;
            } else {
                $validated['classe_id'] = null;
            }
        }

        if (isset($validated['role_animateur'])) {
            $validated['role_animateur'] = strtolower($validated['role_animateur']);
        }

        $model->update($validated);
        $model->refresh();
        $model->load(['animateur', 'anneeCatechese', 'classe']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Affectation mise à jour avec succès.',
            'data'    => new AffectationAnimateurResource($model),
        ]);
    }

    /**
     * Supprimer une affectation.
     */
    public function destroy(Request $request, mixed $affectation): JsonResponse
    {
        $model = $this->resolveAffectation($affectation);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $model->paroisse_configuration_id);

        $model->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Affectation supprimée avec succès.',
        ]);
    }

    /**
     * Résout l'instance du modèle depuis un objet injecté, un UUID ou un ID numérique.
     */
    private function resolveAffectation(mixed $affectation): AffectationAnimateur
    {
        if ($affectation instanceof AffectationAnimateur && $affectation->exists) {
            return $affectation;
        }

        $identifier = is_object($affectation) ? ($affectation->uuid ?? $affectation->id ?? null) : $affectation;

        return AffectationAnimateur::where('uuid', $identifier)
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

