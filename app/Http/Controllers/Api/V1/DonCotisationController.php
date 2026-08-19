<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreDonCotisationRequest;
use App\Http\Resources\Api\V1\DonCotisationResource;
use App\Models\AnneeCatechese;
use App\Models\CaisseParoissiale;
use App\Models\Ceb;
use App\Models\DonCotisation;
use App\Models\Mouvement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DonCotisationController extends Controller
{
    /**
     * Liste des dons et cotisations.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $query = DonCotisation::with(['anneeCatechese', 'mouvement', 'ceb'])
            ->where('paroisse_configuration_id', $paroisseId);

        if ($request->filled('type_don')) {
            $query->where('type_don', $request->type_don);
        }

        $list = $query->latest('date_reception')->get();

        return response()->json([
            'status' => 'success',
            'data' => DonCotisationResource::collection($list),
        ]);
    }

    /**
     * Enregistrer un don ou une cotisation.
     */
    public function store(StoreDonCotisationRequest $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;
        $validated = $request->validated();

        $annee = AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->firstOrFail();

        $validated['paroisse_configuration_id'] = $paroisseId;
        $validated['annee_catechese_id'] = $annee->id;

        if (!empty($validated['mouvement_id'])) {
            $mouvement = Mouvement::where('uuid', $validated['mouvement_id'])->firstOrFail();
            $validated['mouvement_id'] = $mouvement->id;
        }

        if (!empty($validated['ceb_id'])) {
            $ceb = Ceb::where('uuid', $validated['ceb_id'])->firstOrFail();
            $validated['ceb_id'] = $ceb->id;
        }


        $don = DB::transaction(function () use ($paroisseId, $annee, $validated) {
            $donObj = DonCotisation::create($validated);

            // Si c'est un don en espèces ou une cotisation, l'inscrire en entrée dans la caisse paroissiale
            if ($donObj->montant > 0 && in_array($donObj->type_don, ['don_especes', 'cotisation_ceb', 'cotisation_mouvement'])) {
                $cat = str_contains($donObj->type_don, 'cotisation') ? 'cotisation' : 'don';

                CaisseParoissiale::create([
                    'paroisse_configuration_id' => $paroisseId,
                    'annee_catechese_id' => $annee->id,
                    'type_mouvement' => 'entree',
                    'categorie' => $cat,
                    'montant' => $donObj->montant,
                    'reference_document' => 'DON-' . $donObj->id,
                    'libelle' => "{$donObj->type_don} - Donateur : {$donObj->donateur_nom}",
                    'date_mouvement' => $donObj->date_reception,
                ]);
            }

            return $donObj;
        });

        $don->load(['anneeCatechese', 'mouvement', 'ceb']);

        return response()->json([
            'status' => 'success',
            'message' => 'Don / Cotisation enregistré avec succès.',
            'data' => new DonCotisationResource($don),
        ], 201);
    }

    /**
     * Afficher les détails d'un don.
     */
    public function show(Request $request, DonCotisation $donCotisation): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $donCotisation->paroisse_configuration_id);

        $donCotisation->load(['anneeCatechese', 'mouvement', 'ceb']);

        return response()->json([
            'status' => 'success',
            'data' => new DonCotisationResource($donCotisation),
        ]);
    }

    /**
     * Supprimer un don.
     */
    public function destroy(Request $request, DonCotisation $donCotisation): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $donCotisation->paroisse_configuration_id);

        $donCotisation->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Don supprimé avec succès.',
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
