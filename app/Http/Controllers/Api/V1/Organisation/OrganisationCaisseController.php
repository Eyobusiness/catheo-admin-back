<?php

namespace App\Http\Controllers\Api\V1\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Organisation\StoreDepenseRequest;
use App\Http\Resources\Api\V1\Organisation\Pelerinage\OperationOrganisationResource;
use App\Models\Organisation;
use App\Services\Organisation\OrganisationCaisseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class OrganisationCaisseController extends Controller
{
    public function __construct(
        protected OrganisationCaisseService $caisseService
    ) {}

    protected function checkPermission(Request $request, string $permission): void
    {
        $user = $request->user();
        if ($user && method_exists($user, 'hasPermission') && !$user->hasPermission($permission)) {
            // Si la permission demandée est caisse.create ou caisse.manage, autoriser aussi si l'utilisateur a caisse.read ou pelerinages.manage
            if (in_array($permission, ['caisse.create', 'caisse.manage', 'caisse.delete']) &&
                ($user->hasPermission('caisse.read') || $user->hasPermission('pelerinages.manage') || $user->hasPermission('activites.manage'))) {
                return;
            }
            throw new AccessDeniedHttpException("Vous ne disposez pas de la permission requise [{$permission}].");
        }
    }

    /**
     * État de caisse détaillé de l'organisation.
     */
    public function index(Request $request): JsonResponse
    {
        $this->checkPermission($request, 'caisse.read');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        $filters = $request->only(['date_debut', 'date_fin', 'type_operation', 'campagne_id', 'search']);
        $perPage = (int) $request->input('per_page', 25);

        $result = $this->caisseService->getEtatCaisse($organisation, $filters, $perPage);

        return response()->json([
            'status'   => 'success',
            'message'  => 'État de caisse récupéré avec succès.',
            'synthese' => $result['synthese'],
            'data'     => OperationOrganisationResource::collection($result['operations']),
            'meta'     => [
                'current_page' => $result['operations']->currentPage(),
                'last_page'    => $result['operations']->lastPage(),
                'per_page'     => $result['operations']->perPage(),
                'total'        => $result['operations']->total(),
            ],
        ]);
    }

    /**
     * Enregistrer une dépense (décaissement / sortie de caisse).
     */
    public function storeDepense(StoreDepenseRequest $request): JsonResponse
    {
        $this->checkPermission($request, 'caisse.read');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        try {
            $depense = $this->caisseService->createDepense(
                $organisation,
                $request->validated(),
                $request->user()?->id
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Dépense enregistrée avec succès dans la caisse.',
                'data'    => new OperationOrganisationResource($depense->load('operateur')),
            ], 201);
        } catch (UnprocessableEntityHttpException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Annuler une dépense de caisse.
     */
    public function annulerDepense(Request $request, $operation): JsonResponse
    {
        $this->checkPermission($request, 'caisse.read');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        try {
            $annulee = $this->caisseService->annulerDepense(
                $organisation,
                $operation,
                $request->input('motif'),
                $request->user()?->id
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Dépense annulée avec succès.',
                'data'    => new OperationOrganisationResource($annulee->load('operateur')),
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
