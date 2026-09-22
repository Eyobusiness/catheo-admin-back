<?php

namespace App\Http\Controllers\Api\V1\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Organisation\Pelerinage\StorePaiementPelerinageRequest;
use App\Http\Resources\Api\V1\Organisation\Pelerinage\InscriptionPelerinageResource;
use App\Http\Resources\Api\V1\Organisation\Pelerinage\PaiementPelerinageResource;
use App\Models\Organisation;
use App\Services\Organisation\CampagnePelerinageService;
use App\Services\Organisation\InscriptionPelerinageService;
use App\Services\Organisation\PaiementPelerinageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PaiementPelerinageController extends Controller
{
    public function __construct(
        protected CampagnePelerinageService $campagneService,
        protected InscriptionPelerinageService $inscriptionService,
        protected PaiementPelerinageService $paiementService
    ) {}

    protected function checkPermission(Request $request, string $permission): void
    {
        $user = $request->user();
        if ($user && method_exists($user, 'hasPermission') && !$user->hasPermission($permission)) {
            throw new AccessDeniedHttpException("Vous ne disposez pas de la permission requise [{$permission}].");
        }
    }

    /**
     * Journal des paiements d'une campagne de pèlerinage.
     */
    public function index(Request $request, $campagne): JsonResponse
    {
        $this->checkPermission($request, 'pelerinages.read');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        try {
            $campagneModel = $this->campagneService->find($organisation, $campagne);
            $filters = $request->only(['statut', 'mode_paiement', 'search']);
            $perPage = (int) $request->input('per_page', 15);

            $result = $this->paiementService->list($campagneModel, $filters, $perPage);

            return response()->json([
                'status'  => 'success',
                'message' => 'Journal des paiements récupéré avec succès.',
                'data'    => PaiementPelerinageResource::collection($result),
                'meta'    => [
                    'current_page' => $result->currentPage(),
                    'last_page'    => $result->lastPage(),
                    'per_page'     => $result->perPage(),
                    'total'        => $result->total(),
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
     * Historique des paiements d'une inscription spécifique.
     */
    public function indexForInscription(Request $request, $campagne, $inscription): JsonResponse
    {
        $this->checkPermission($request, 'pelerinages.read');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        try {
            $campagneModel = $this->campagneService->find($organisation, $campagne);
            $inscriptionModel = $this->inscriptionService->find($campagneModel, $inscription);
            $paiements = $this->paiementService->listForInscription($inscriptionModel);

            return response()->json([
                'status'  => 'success',
                'message' => 'Historique des paiements du participant récupéré avec succès.',
                'data'    => PaiementPelerinageResource::collection($paiements),
                'meta'    => [
                    'montant_total' => (float) $inscriptionModel->montant,
                    'montant_paye'  => (float) $inscriptionModel->montant_paye,
                    'reste_a_payer' => (float) $inscriptionModel->reste_a_payer,
                    'statut'        => $inscriptionModel->statut_inscription,
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
     * Enregistrer un nouveau paiement pour un participant.
     */
    public function store(StorePaiementPelerinageRequest $request, $campagne, $inscription): JsonResponse
    {
        $this->checkPermission($request, 'pelerinages.paiements');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        try {
            $campagneModel = $this->campagneService->find($organisation, $campagne);
            $inscriptionModel = $this->inscriptionService->find($campagneModel, $inscription);

            $result = $this->paiementService->create(
                $inscriptionModel,
                $request->validated(),
                $request->user()?->uuid ?? (string) $request->user()?->id
            );

            return response()->json([
                'status'      => 'success',
                'message'     => 'Paiement enregistré avec succès.',
                'data'        => new PaiementPelerinageResource($result['paiement']),
                'inscription' => new InscriptionPelerinageResource($result['inscription']),
            ], 201);
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
     * Annuler un paiement de pèlerinage.
     */
    public function annuler(Request $request, $campagne, $paiement): JsonResponse
    {
        $this->checkPermission($request, 'pelerinages.paiements');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        try {
            $campagneModel = $this->campagneService->find($organisation, $campagne);
            $paiementModel = $this->paiementService->find($campagneModel, $paiement);

            $result = $this->paiementService->annuler(
                $paiementModel,
                $request->input('motif'),
                $request->user()?->uuid ?? (string) $request->user()?->id
            );

            return response()->json([
                'status'      => 'success',
                'message'     => 'Paiement annulé avec succès.',
                'data'        => new PaiementPelerinageResource($result['paiement']),
                'inscription' => new InscriptionPelerinageResource($result['inscription']),
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
