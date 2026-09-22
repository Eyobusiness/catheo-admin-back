<?php

namespace App\Http\Controllers\Api\V1\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Organisation\Pelerinage\OperationOrganisationResource;
use App\Models\Organisation;
use App\Services\Organisation\OrganisationCaisseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class OrganisationCaisseController extends Controller
{
    public function __construct(
        protected OrganisationCaisseService $caisseService
    ) {}

    protected function checkPermission(Request $request, string $permission): void
    {
        $user = $request->user();
        if ($user && method_exists($user, 'hasPermission') && !$user->hasPermission($permission)) {
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

        $filters = $request->only(['date_debut', 'date_fin', 'type_operation', 'campagne_id']);
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
}
