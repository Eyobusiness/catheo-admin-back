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
     * Liste des années pastorales de la paroisse du tenant connecté.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $annees = AnneeCatechese::where('paroisse_configuration_id', $paroisseId)
            ->latest('date_debut')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => AnneeCatecheseResource::collection($annees),
        ]);
    }

    /**
     * Création d'une nouvelle année pastorale.
     */
    public function store(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $validated = $request->validate([
            'libelle' => ['required', 'string', 'max:50'],
            'date_debut' => ['required', 'date'],
            'date_fin' => ['required', 'date', 'after:date_debut'],
            'est_active' => ['nullable', 'boolean'],
            'statut' => ['nullable', 'string', 'in:preparation,active,cloturee'],
        ]);

        $validated['paroisse_configuration_id'] = $paroisseId;

        $annee = DB::transaction(function () use ($validated, $paroisseId) {
            // Si la nouvelle année est marquée comme active, désactiver les autres
            if (!empty($validated['est_active'])) {
                AnneeCatechese::where('paroisse_configuration_id', $paroisseId)
                    ->update(['est_active' => false]);
            }

            return AnneeCatechese::create($validated);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Année pastorale créée avec succès.',
            'data' => new AnneeCatecheseResource($annee),
        ], 201);
    }

    /**
     * Affichage d'une année pastorale.
     */
    public function show(Request $request, AnneeCatechese $annee): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $annee->paroisse_configuration_id);

        return response()->json([
            'status' => 'success',
            'data' => new AnneeCatecheseResource($annee),
        ]);
    }

    /**
     * Mise à jour d'une année pastorale.
     */
    public function update(Request $request, AnneeCatechese $annee): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $annee->paroisse_configuration_id);

        $validated = $request->validate([
            'libelle' => ['sometimes', 'required', 'string', 'max:50'],
            'date_debut' => ['sometimes', 'required', 'date'],
            'date_fin' => ['sometimes', 'required', 'date', 'after:date_debut'],
            'statut' => ['nullable', 'string', 'in:preparation,active,cloturee'],
        ]);

        $annee->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Année pastorale mise à jour avec succès.',
            'data' => new AnneeCatecheseResource($annee),
        ]);
    }

    /**
     * Activer l'année pastorale sélectionnée (Désactive atomiquement les autres années de la paroisse).
     */
    public function activate(Request $request, AnneeCatechese $annee): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $annee->paroisse_configuration_id);

        DB::transaction(function () use ($annee) {
            AnneeCatechese::where('paroisse_configuration_id', $annee->paroisse_configuration_id)
                ->update(['est_active' => false]);

            $annee->update([
                'est_active' => true,
                'statut' => 'active',
            ]);
        });

        return response()->json([
            'status' => 'success',
            'message' => "L'année pastorale {$annee->libelle} est désormais l'année active de travail.",
            'data' => new AnneeCatecheseResource($annee),
        ]);
    }

    /**
     * Suppression (SoftDelete) d'une année pastorale.
     */
    public function destroy(Request $request, AnneeCatechese $annee): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $annee->paroisse_configuration_id);

        if ($annee->est_active) {
            return response()->json([
                'status' => 'error',
                'message' => 'Impossible de supprimer l\'année pastorale actuellement active.',
            ], 422);
        }

        $annee->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Année pastorale supprimée avec succès.',
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
