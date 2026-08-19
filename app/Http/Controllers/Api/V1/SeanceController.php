<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\BatchPresenceRequest;
use App\Http\Requests\Api\V1\StoreSeanceRequest;
use App\Http\Resources\Api\V1\SeanceResource;
use App\Models\AnneeCatechese;
use App\Models\Catechumene;
use App\Models\Classe;
use App\Models\ModuleTrimestriel;
use App\Models\Presence;
use App\Models\Seance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SeanceController extends Controller
{
    /**
     * Liste des séances de cours planifiées.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $query = Seance::with(['anneeCatechese', 'classe', 'moduleTrimestriel'])
            ->where('paroisse_configuration_id', $paroisseId);

        if ($request->filled('classe_id')) {
            $classeId = Classe::where('uuid', $request->classe_id)->value('id');
            if ($classeId) {
                $query->where('classe_id', $classeId);
            }
        }

        $seances = $query->latest('date_seance')->get();

        return response()->json([
            'status' => 'success',
            'data' => SeanceResource::collection($seances),
        ]);
    }

    /**
     * Planifier une séance de cours.
     */
    public function store(StoreSeanceRequest $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;
        $validated = $request->validated();

        $annee = AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->firstOrFail();
        $classe = Classe::where('uuid', $validated['classe_id'])->firstOrFail();

        $validated['paroisse_configuration_id'] = $paroisseId;
        $validated['annee_catechese_id'] = $annee->id;
        $validated['classe_id'] = $classe->id;

        if (!empty($validated['module_trimestriel_id'])) {
            $module = ModuleTrimestriel::where('uuid', $validated['module_trimestriel_id'])->firstOrFail();
            $validated['module_trimestriel_id'] = $module->id;
        }


        $seance = Seance::create($validated);
        $seance->load(['anneeCatechese', 'classe', 'moduleTrimestriel']);

        return response()->json([
            'status' => 'success',
            'message' => 'Séance planifiée avec succès.',
            'data' => new SeanceResource($seance),
        ], 201);
    }

    /**
     * Afficher les détails d'une séance.
     */
    public function show(Request $request, Seance $seance): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $seance->paroisse_configuration_id);

        $seance->load(['anneeCatechese', 'classe', 'moduleTrimestriel', 'presences.catechumene']);

        return response()->json([
            'status' => 'success',
            'data' => new SeanceResource($seance),
        ]);
    }

    /**
     * Saisie en lot de la feuille de présence d'une séance.
     */
    public function presences(BatchPresenceRequest $request, Seance $seance): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $seance->paroisse_configuration_id);
        $paroisseId = $request->user()->paroisse_configuration_id;

        $validated = $request->validated();

        DB::transaction(function () use ($paroisseId, $seance, $validated) {
            foreach ($validated['presences'] as $item) {
                $catechumene = Catechumene::where('uuid', $item['catechumene_id'])->firstOrFail();

                Presence::updateOrCreate(
                    [
                        'paroisse_configuration_id' => $paroisseId,
                        'seance_id' => $seance->id,
                        'catechumene_id' => $catechumene->id,
                    ],
                    [
                        'statut_presence' => $item['statut_presence'],
                        'remarque' => $item['remarque'] ?? null,
                    ]
                );
            }

            $seance->update(['statut' => 'effectuee']);
        });

        $seance->load(['presences.catechumene']);

        return response()->json([
            'status' => 'success',
            'message' => 'Appel de présence enregistré avec succès.',
            'data' => new SeanceResource($seance),
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
