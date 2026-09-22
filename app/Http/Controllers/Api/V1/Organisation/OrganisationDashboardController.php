<?php

namespace App\Http\Controllers\Api\V1\Organisation;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Services\Organisation\OrganisationDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class OrganisationDashboardController extends Controller
{
    public function __construct(
        protected OrganisationDashboardService $dashboardService
    ) {}

    protected function checkPermission(Request $request, string $permission): void
    {
        $user = $request->user();
        if ($user && method_exists($user, 'hasPermission') && !$user->hasPermission($permission)) {
            throw new AccessDeniedHttpException("Vous ne disposez pas de la permission requise [{$permission}].");
        }
    }

    /**
     * Dashboard général consolidé de l'organisation.
     */
    public function index(Request $request): JsonResponse
    {
        $this->checkPermission($request, 'dashboard.read');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        $data = $this->dashboardService->getDashboard(
            $organisation,
            $request->boolean('fresh', false)
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Tableau de bord de l\'organisation récupéré avec succès.',
            'data'    => $data,
        ]);
    }
}
