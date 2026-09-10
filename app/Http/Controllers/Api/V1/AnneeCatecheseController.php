<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AnneeCatecheseResource;
use App\Models\AnneeCatechese;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnneeCatecheseController extends Controller
{
    /**
     * Liste des annÃ©es pastorales de la paroisse du tenant connectÃ©.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $paroisseId = $user?->paroisse_configuration_id 
            ?? $request->input('paroisse_configuration_id')
            ?? $request->input('paroisse_id')
            ?? $request->header('X-Paroisse-Id')
            ?? $request->header('X-Paroisse-Configuration-Id');

        if (!$paroisseId) {
            return response()->json([
                'status' => 'success',
                'meta'   => [
                    'total_elements' => 0,
                ],
                'data'   => [],
            ]);
        }

        $query = AnneeCatechese::where('paroisse_configuration_id', (int) $paroisseId);

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where('libelle', 'like', "%{$search}%");
        }

        if ($request->filled('statut') && strtolower($request->statut) !== 'tous') {
            $query->where('statut', strtolower($request->statut));
        }

        $annees = $query
            ->withCount(['inscriptionsAnnuelles as total_inscrits' => function ($q) {
                $q->where('statut_inscription', '!=', 'annulee');
            }])
            ->latest('date_debut')
            ->get();

        return response()->json([
            'status' => 'success',
            'meta' => [
                'total_elements' => $annees->count(),
            ],
            'data'   => AnneeCatecheseResource::collection($annees),
        ]);
    }

    /**
     * Obtenir l'année pastorale en cours / active ou celle sélectionnée.
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
        $paroisseId = $user?->paroisse_configuration_id 
            ?? $request->input('paroisse_configuration_id')
            ?? $request->input('paroisse_id')
            ?? $request->header('X-Paroisse-Id')
            ?? $request->header('X-Paroisse-Configuration-Id');

        if (!$paroisseId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Aucune paroisse spécifiée ou rattachée au compte actuel.',
            ], 404);
        }

        $annee = AnneeCatechese::resolveAnnee($request, (int) $paroisseId);

        if (!$annee) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Aucune année pastorale configurée pour cette paroisse.',
            ], 404);
        }

        $annee->loadCount(['inscriptionsAnnuelles as total_inscrits' => function ($q) {
            $q->where('statut_inscription', '!=', 'annulee');
        }]);

        return response()->json([
            'status' => 'success',
            'data'   => new AnneeCatecheseResource($annee),
        ]);
    }

    /**
     * Création d'une nouvelle année pastorale.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $paroisseId = $user?->paroisse_configuration_id 
            ?? $request->input('paroisse_configuration_id')
            ?? $request->input('paroisse_id')
            ?? $request->header('X-Paroisse-Id');

        if (!$paroisseId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'L\'identifiant de la paroisse est obligatoire.',
            ], 422);
        }

        $validated = $request->validate([
            'libelle'    => ['required', 'string', 'max:50'],
            'date_debut' => ['required', 'date'],
            'date_fin'   => ['required', 'date', 'after:date_debut'],
            'statut'     => ['nullable', 'string', 'in:preparation,active,cloturee'],
        ]);

        $validated['paroisse_configuration_id'] = (int) $paroisseId;
        $validated['statut'] = $validated['statut'] ?? 'preparation';

        $annee = DB::transaction(function () use ($validated, $paroisseId) {
            if ($validated['statut'] === 'active') {
                AnneeCatechese::where('paroisse_configuration_id', $paroisseId)
                    ->where('statut', 'active')
                    ->update(['statut' => 'cloturee']);
            }

            return AnneeCatechese::create($validated);
        });

        $annee->loadCount(['inscriptionsAnnuelles as total_inscrits' => function ($q) {
            $q->where('statut_inscription', '!=', 'annulee');
        }]);

        return response()->json([
            'status'  => 'success',
            'message' => 'AnnÃ©e pastorale crÃ©Ã©e avec succÃ¨s.',
            'data'    => new AnneeCatecheseResource($annee),
        ], 201);
    }

    /**
     * Affichage d'une annÃ©e pastorale.
     */
    public function show(Request $request, AnneeCatechese $annee): JsonResponse
    {
        $this->authorizeTenant($request->user()?->paroisse_configuration_id, $annee->paroisse_configuration_id);

        $annee->loadCount(['inscriptionsAnnuelles as total_inscrits' => function ($q) {
            $q->where('statut_inscription', '!=', 'annulee');
        }]);

        return response()->json([
            'status' => 'success',
            'data'   => new AnneeCatecheseResource($annee),
        ]);
    }

    /**
     * Mise à jour d'une année pastorale.
     */
    public function update(Request $request, AnneeCatechese $annee): JsonResponse
    {
        $this->authorizeTenant($request->user()?->paroisse_configuration_id, $annee->paroisse_configuration_id);

        $validated = $request->validate([
            'libelle'    => ['sometimes', 'required', 'string', 'max:50'],
            'date_debut' => ['sometimes', 'required', 'date'],
            'date_fin'   => ['sometimes', 'required', 'date', 'after:date_debut'],
            'statut'     => ['nullable', 'string', 'in:preparation,active,cloturee'],
        ]);

        DB::transaction(function () use ($annee, $validated) {
            if (!empty($validated['statut']) && $validated['statut'] === 'active') {
                AnneeCatechese::where('paroisse_configuration_id', $annee->paroisse_configuration_id)
                    ->where('id', '!=', $annee->id)
                    ->where('statut', 'active')
                    ->update(['statut' => 'cloturee']);
            }

            $annee->update($validated);
        });

        $annee->loadCount(['inscriptionsAnnuelles as total_inscrits' => function ($q) {
            $q->where('statut_inscription', '!=', 'annulee');
        }]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Année pastorale mise à jour avec succès.',
            'data'    => new AnneeCatecheseResource($annee),
        ]);
    }

    /**
     * Activer l'année pastorale sélectionnée (Passe son statut à 'active' et clôture les autres années actives).
     */
    public function activate(Request $request, AnneeCatechese $annee): JsonResponse
    {
        $this->authorizeTenant($request->user()?->paroisse_configuration_id, $annee->paroisse_configuration_id);

        DB::transaction(function () use ($annee) {
            AnneeCatechese::where('paroisse_configuration_id', $annee->paroisse_configuration_id)
                ->where('id', '!=', $annee->id)
                ->where('statut', 'active')
                ->update(['statut' => 'cloturee']);

            $annee->update([
                'statut' => 'active',
            ]);
        });

        $annee->loadCount(['inscriptionsAnnuelles as total_inscrits' => function ($q) {
            $q->where('statut_inscription', '!=', 'annulee');
        }]);

        return response()->json([
            'status'  => 'success',
            'message' => "L'année pastorale {$annee->libelle} est désormais l'année active.",
            'data'    => new AnneeCatecheseResource($annee),
        ]);
    }

    /**
     * Suppression (SoftDelete) d'une année pastorale.
     */
    public function destroy(Request $request, AnneeCatechese $annee): JsonResponse
    {
        $this->authorizeTenant($request->user()?->paroisse_configuration_id, $annee->paroisse_configuration_id);

        if ($annee->statut === 'active') {
            return response()->json([
                'status'  => 'error',
                'message' => "Impossible de supprimer l'annÃ©e pastorale actuellement active.",
            ], 422);
        }

        if ($annee->classes()->exists()) {
            return response()->json([
                'status'  => 'error',
                'message' => "Impossible de supprimer cette annÃ©e pastorale car des classes y sont rattachÃ©es.",
            ], 422);
        }

        $annee->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'AnnÃ©e pastorale supprimÃ©e avec succÃ¨s.',
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'AccÃ¨s refusÃ©.'], 403));
        }
    }
}