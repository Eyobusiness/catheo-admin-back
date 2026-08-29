<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCatechumenSacrementRequest;
use App\Http\Requests\Api\V1\UpdateCatechumenSacrementRequest;
use App\Http\Resources\Api\V1\CatechumeneSacrementListResource;
use App\Http\Resources\Api\V1\CatechumenSacrementResource;
use App\Http\Resources\Api\V1\SacrementResource;
use App\Models\Catechumene;
use App\Models\CatechumenSacrement;
use App\Services\SacrementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SacrementController extends Controller
{
    protected SacrementService $sacrementService;

    public function __construct(SacrementService $sacrementService)
    {
        $this->sacrementService = $sacrementService;
    }

    /**
     * Liste de tous les types de sacrements gérés par le système.
     */
    public function index(Request $request): JsonResponse
    {
        $sacrements = $this->sacrementService->getSacrements();

        return response()->json([
            'status' => 'success',
            'data'   => SacrementResource::collection($sacrements),
        ]);
    }

    /**
     * Obtenir les détails d'un type de sacrement (Baptême, 1ère Communion, Confirmation).
     */
    public function show(Request $request, string|int $id): JsonResponse
    {
        $sacrement = $this->sacrementService->getSacrementById($id);

        if (!$sacrement) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Type de sacrement introuvable.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => new SacrementResource($sacrement),
        ]);
    }

    /**
     * Filtrage dynamique des catéchumènes et de leur état sacramentel.
     * ZERO hardcoding : Filtres par section_id, niveau_id, classe_id, sacrement_id, statut, search.
     */
    public function catechumens(Request $request): JsonResponse
    {
        $paroisseId = $request->user()?->paroisse_configuration_id ?? 1;
        $filters = $request->all();

        $paginator = $this->sacrementService->getCatechumens($paroisseId, $filters);

        return response()->json([
            'status' => 'success',
            'meta'   => [
                'current_page'   => $paginator->currentPage(),
                'per_page'       => $paginator->perPage(),
                'total_elements' => $paginator->total(),
                'total_pages'    => $paginator->lastPage(),
                'has_next'       => $paginator->hasMorePages(),
            ],
            'data'   => CatechumeneSacrementListResource::collection($paginator->items()),
        ]);
    }

    /**
     * Obtenir le parcours sacramentel complet d'un catéchumène.
     */
    public function parcours(Request $request, string|int $catechumeneId): JsonResponse
    {
        $paroisseId = $request->user()?->paroisse_configuration_id ?? 1;
        $catechumene = $this->resolveCatechumene($catechumeneId, $paroisseId);

        $parcours = $this->sacrementService->getCatechumenParcours($paroisseId, $catechumene);

        return response()->json([
            'status'      => 'success',
            'catechumene' => [
                'id'          => $catechumene->uuid,
                'matricule'   => $catechumene->matricule,
                'nom'         => $catechumene->nom,
                'prenoms'     => $catechumene->prenoms,
                'nom_complet' => $catechumene->nom_complet,
                'sexe'        => $catechumene->sexe,
                'telephone'   => $catechumene->telephone,
            ],
            'data'        => $parcours,
        ]);
    }

    /**
     * Enregistrer un sacrement (en préparation ou validé) pour un catéchumène.
     */
    public function storeParcours(StoreCatechumenSacrementRequest $request, string|int $catechumeneId): JsonResponse
    {
        $paroisseId = $request->user()?->paroisse_configuration_id ?? 1;
        $catechumene = $this->resolveCatechumene($catechumeneId, $paroisseId);

        $parcours = $this->sacrementService->storeParcoursSacrement(
            $paroisseId,
            $catechumene,
            $request->validated(),
            $request->user()
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Parcours sacramentel enregistré avec succès.',
            'data'    => new CatechumenSacrementResource($parcours),
        ], 201);
    }

    /**
     * Consulter un sacrement spécifique pour un catéchumène.
     */
    public function showParcours(Request $request, string|int $catechumeneId, string|int $sacrementId): JsonResponse
    {
        $paroisseId = $request->user()?->paroisse_configuration_id ?? 1;
        $catechumene = $this->resolveCatechumene($catechumeneId, $paroisseId);

        $parcours = CatechumenSacrement::where('catechumene_id', $catechumene->id)
            ->where('paroisse_configuration_id', $paroisseId)
            ->where(function ($q) use ($sacrementId) {
                $q->where('uuid', $sacrementId)
                  ->orWhere('id', is_numeric($sacrementId) ? $sacrementId : 0)
                  ->orWhereHas('sacrement', function ($sq) use ($sacrementId) {
                      $sq->where('uuid', $sacrementId)
                         ->orWhere('code', strtoupper($sacrementId))
                         ->orWhere('id', is_numeric($sacrementId) ? $sacrementId : 0);
                  });
            })
            ->with(['sacrement', 'validator', 'anneeCatechese'])
            ->firstOrFail();

        return response()->json([
            'status' => 'success',
            'data'   => new CatechumenSacrementResource($parcours),
        ]);
    }

    /**
     * Mettre à jour ou Valider un sacrement pour un catéchumène.
     */
    public function updateParcours(UpdateCatechumenSacrementRequest $request, string|int $catechumeneId, string|int $sacrementId): JsonResponse
    {
        $paroisseId = $request->user()?->paroisse_configuration_id ?? 1;
        $catechumene = $this->resolveCatechumene($catechumeneId, $paroisseId);

        $parcours = $this->sacrementService->updateParcoursSacrement(
            $paroisseId,
            $catechumene,
            $sacrementId,
            $request->validated(),
            $request->user()
        );

        $message = ($parcours->statut === 'valide')
            ? "Le sacrement '{$parcours->sacrement->nom}' a été validé avec succès."
            : 'Parcours sacramentel mis à jour.';

        return response()->json([
            'status'  => 'success',
            'message' => $message,
            'data'    => new CatechumenSacrementResource($parcours),
        ]);
    }

    /**
     * Supprimer un sacrement du parcours d'un catéchumène.
     */
    public function destroyParcours(Request $request, string|int $catechumeneId, string|int $sacrementId): JsonResponse
    {
        $paroisseId = $request->user()?->paroisse_configuration_id ?? 1;
        $catechumene = $this->resolveCatechumene($catechumeneId, $paroisseId);

        $this->sacrementService->deleteParcoursSacrement($paroisseId, $catechumene, $sacrementId);

        return response()->json([
            'status'  => 'success',
            'message' => 'Enregistrement sacramentel supprimé avec succès.',
        ]);
    }

    /**
     * Résout un catéchumène par UUID ou ID numérique pour la paroisse connectée.
     */
    protected function resolveCatechumene(string|int $catechumeneId, int $paroisseId): Catechumene
    {
        return is_numeric($catechumeneId)
            ? Catechumene::where('paroisse_configuration_id', $paroisseId)->where('id', $catechumeneId)->firstOrFail()
            : Catechumene::where('paroisse_configuration_id', $paroisseId)->where('uuid', $catechumeneId)->firstOrFail();
    }
}
