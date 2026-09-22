<?php

namespace App\Http\Controllers\Api\V1\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Organisation\Pelerinage\StoreTarifPelerinageRequest;
use App\Http\Requests\Api\V1\Organisation\Pelerinage\UpdateTarifPelerinageRequest;
use App\Http\Resources\Api\V1\Organisation\Pelerinage\TarifPelerinageResource;
use App\Models\Organisation;
use App\Services\Organisation\CampagnePelerinageService;
use App\Services\Organisation\TarifPelerinageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class TarifPelerinageController extends Controller
{
    public function __construct(
        protected CampagnePelerinageService $campagneService,
        protected TarifPelerinageService $tarifService
    ) {}

    protected function checkPermission(Request $request, string $permission): void
    {
        $user = $request->user();
        if ($user && method_exists($user, 'hasPermission') && !$user->hasPermission($permission)) {
            throw new AccessDeniedHttpException("Vous ne disposez pas de la permission requise [{$permission}].");
        }
    }

    /**
     * Liste des tarifs d'une campagne.
     */
    public function index(Request $request, $campagne): JsonResponse
    {
        $this->checkPermission($request, 'pelerinages.read');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        try {
            $campagneModel = $this->campagneService->find($organisation, $campagne);
            $tarifs = $this->tarifService->list($campagneModel);

            return response()->json([
                'status'  => 'success',
                'message' => 'Tarifs de la campagne récupérés avec succès.',
                'data'    => TarifPelerinageResource::collection($tarifs),
            ]);
        } catch (NotFoundHttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Ajouter un tarif à la campagne.
     */
    public function store(StoreTarifPelerinageRequest $request, $campagne): JsonResponse
    {
        $this->checkPermission($request, 'pelerinages.create');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        try {
            $campagneModel = $this->campagneService->find($organisation, $campagne);
            $tarif = $this->tarifService->create($campagneModel, $request->validated());

            return response()->json([
                'status'  => 'success',
                'message' => 'Tarif ajouté avec succès.',
                'data'    => new TarifPelerinageResource($tarif),
            ], 201);
        } catch (NotFoundHttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Détails d'un tarif.
     */
    public function show(Request $request, $campagne, $tarif): JsonResponse
    {
        $this->checkPermission($request, 'pelerinages.read');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        try {
            $campagneModel = $this->campagneService->find($organisation, $campagne);
            $tarifModel = $this->tarifService->find($campagneModel, $tarif);

            return response()->json([
                'status'  => 'success',
                'message' => 'Détails du tarif récupérés avec succès.',
                'data'    => new TarifPelerinageResource($tarifModel),
            ]);
        } catch (NotFoundHttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Mettre à jour un tarif.
     */
    public function update(UpdateTarifPelerinageRequest $request, $campagne, $tarif): JsonResponse
    {
        $this->checkPermission($request, 'pelerinages.update');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        try {
            $campagneModel = $this->campagneService->find($organisation, $campagne);
            $tarifModel = $this->tarifService->find($campagneModel, $tarif);
            $updated = $this->tarifService->update($tarifModel, $request->validated());

            return response()->json([
                'status'  => 'success',
                'message' => 'Tarif mis à jour avec succès.',
                'data'    => new TarifPelerinageResource($updated),
            ]);
        } catch (NotFoundHttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Supprimer un tarif.
     */
    public function destroy(Request $request, $campagne, $tarif): JsonResponse
    {
        $this->checkPermission($request, 'pelerinages.delete');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        try {
            $campagneModel = $this->campagneService->find($organisation, $campagne);
            $tarifModel = $this->tarifService->find($campagneModel, $tarif);
            $this->tarifService->delete($tarifModel);

            return response()->json([
                'status'  => 'success',
                'message' => 'Tarif supprimé avec succès.',
            ]);
        } catch (NotFoundHttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 404);
        } catch (UnprocessableEntityHttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }
}
