<?php

namespace App\Http\Controllers\Api\V1\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Organisation\Pelerinage\StoreCampagnePelerinageRequest;
use App\Http\Requests\Api\V1\Organisation\Pelerinage\UpdateCampagnePelerinageRequest;
use App\Http\Resources\Api\V1\Organisation\Pelerinage\CampagnePelerinageResource;
use App\Models\CampagnePelerinage;
use App\Models\Organisation;
use App\Services\Organisation\CampagnePelerinageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class CampagnePelerinageController extends Controller
{
    public function __construct(
        protected CampagnePelerinageService $campagneService
    ) {}

    protected function checkPermission(Request $request, string $permission): void
    {
        $user = $request->user();
        if ($user && method_exists($user, 'hasPermission') && !$user->hasPermission($permission)) {
            throw new AccessDeniedHttpException("Vous ne disposez pas de la permission requise [{$permission}].");
        }
    }

    /**
     * Liste des campagnes de pèlerinage de l'organisation.
     */
    public function index(Request $request): JsonResponse
    {
        $this->checkPermission($request, 'pelerinages.read');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        $filters = $request->only(['statut', 'date_depart_min', 'search']);
        $perPage = (int) $request->input('per_page', 15);

        $result = $this->campagneService->list($organisation, $filters, $perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Liste des campagnes de pèlerinage récupérée avec succès.',
            'data'    => CampagnePelerinageResource::collection($result),
            'meta'    => [
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
                'per_page'     => $result->perPage(),
                'total'        => $result->total(),
            ],
        ]);
    }

    /**
     * Créer une nouvelle campagne de pèlerinage.
     */
    public function store(StoreCampagnePelerinageRequest $request): JsonResponse
    {
        $this->checkPermission($request, 'pelerinages.create');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        try {
            $campagne = $this->campagneService->create($organisation, $request->validated());

            return response()->json([
                'status'  => 'success',
                'message' => 'Campagne de pèlerinage créée avec succès.',
                'data'    => new CampagnePelerinageResource($campagne->load('activite', 'tarifs')),
            ], 201);
        } catch (UnprocessableEntityHttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Détails d'une campagne de pèlerinage.
     */
    public function show(Request $request, $campagne): JsonResponse
    {
        $this->checkPermission($request, 'pelerinages.read');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        try {
            $campagneModel = $this->campagneService->find($organisation, $campagne);

            return response()->json([
                'status'  => 'success',
                'message' => 'Détails de la campagne récupérés avec succès.',
                'data'    => new CampagnePelerinageResource($campagneModel),
                'stats'   => $this->campagneService->getStatistiques($campagneModel),
            ]);
        } catch (NotFoundHttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Mettre à jour une campagne de pèlerinage.
     */
    public function update(UpdateCampagnePelerinageRequest $request, $campagne): JsonResponse
    {
        $this->checkPermission($request, 'pelerinages.update');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        try {
            $campagneModel = $this->campagneService->find($organisation, $campagne);
            $updated = $this->campagneService->update($campagneModel, $request->validated());

            return response()->json([
                'status'  => 'success',
                'message' => 'Campagne mise à jour avec succès.',
                'data'    => new CampagnePelerinageResource($updated),
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

    /**
     * Supprimer une campagne de pèlerinage.
     */
    public function destroy(Request $request, $campagne): JsonResponse
    {
        $this->checkPermission($request, 'pelerinages.delete');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        try {
            $campagneModel = $this->campagneService->find($organisation, $campagne);
            $this->campagneService->delete($campagneModel);

            return response()->json([
                'status'  => 'success',
                'message' => 'Campagne supprimée avec succès.',
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

    /**
     * Ouvrir la campagne aux inscriptions.
     */
    public function ouvrir(Request $request, $campagne): JsonResponse
    {
        $this->checkPermission($request, 'pelerinages.update');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        try {
            $campagneModel = $this->campagneService->find($organisation, $campagne);
            $ouvert = $this->campagneService->ouvrir($campagneModel);

            return response()->json([
                'status'  => 'success',
                'message' => 'Campagne ouverte aux inscriptions avec succès.',
                'data'    => new CampagnePelerinageResource($ouvert),
            ]);
        } catch (NotFoundHttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Clôturer la campagne (annule automatiquement les impayés sans suppression physique).
     */
    public function cloturer(Request $request, $campagne): JsonResponse
    {
        $this->checkPermission($request, 'pelerinages.update');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        try {
            $campagneModel = $this->campagneService->find($organisation, $campagne);
            $result = $this->campagneService->cloturer($campagneModel);

            return response()->json([
                'status'  => 'success',
                'message' => "Campagne clôturée avec succès. {$result['inscriptions_annulees']} inscriptions impayées ont été passées au statut annulé.",
                'data'    => new CampagnePelerinageResource($result['campagne']),
                'meta'    => [
                    'inscriptions_annulees' => $result['inscriptions_annulees'],
                ],
            ]);
        } catch (NotFoundHttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Annuler la campagne.
     */
    public function annuler(Request $request, $campagne): JsonResponse
    {
        $this->checkPermission($request, 'pelerinages.update');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        try {
            $campagneModel = $this->campagneService->find($organisation, $campagne);
            $annulee = $this->campagneService->annuler($campagneModel, $request->input('motif'));

            return response()->json([
                'status'  => 'success',
                'message' => 'Campagne annulée avec succès.',
                'data'    => new CampagnePelerinageResource($annulee),
            ]);
        } catch (NotFoundHttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    /**
     * Statistiques globales sur la campagne.
     */
    public function statistiques(Request $request, $campagne): JsonResponse
    {
        $this->checkPermission($request, 'pelerinages.read');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        try {
            $campagneModel = $this->campagneService->find($organisation, $campagne);
            $stats = $this->campagneService->getStatistiques($campagneModel);

            return response()->json([
                'status'  => 'success',
                'message' => 'Statistiques de la campagne récupérées avec succès.',
                'data'    => $stats,
            ]);
        } catch (NotFoundHttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 404);
        }
    }
}
