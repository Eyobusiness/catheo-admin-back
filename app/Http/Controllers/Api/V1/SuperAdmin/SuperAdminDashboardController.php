<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SuperAdmin\SuperAdminDashboardService;
use Illuminate\Http\JsonResponse;

class SuperAdminDashboardController extends Controller
{
    public function __construct(
        protected SuperAdminDashboardService $dashboardService
    ) {}

    /**
     * Indicateurs consolidés du tableau de bord Super Admin.
     */
    public function index(): JsonResponse
    {
        $metrics = $this->dashboardService->getMetrics();

        return response()->json([
            'status'  => 'success',
            'message' => 'Indicateurs consolidés du tableau de bord Super Admin.',
            'data'    => $metrics,
        ]);
    }
}
