<?php

namespace App\Http\Controllers\Api\V1\Organisation;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Services\Organisation\OrganisationRapportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class OrganisationRapportController extends Controller
{
    public function __construct(
        protected OrganisationRapportService $rapportService
    ) {}

    protected function checkPermission(Request $request, string $permission): void
    {
        $user = $request->user();
        if ($user && method_exists($user, 'hasPermission') && !$user->hasPermission($permission)) {
            throw new AccessDeniedHttpException("Vous ne disposez pas de la permission requise [{$permission}].");
        }
    }

    /**
     * Rapport annuel consolidé de l'organisation.
     */
    public function annuel(Request $request): JsonResponse
    {
        $this->checkPermission($request, 'rapports.read');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        $annee = (int) $request->input('annee', date('Y'));

        $data = $this->rapportService->getRapportAnnuel($organisation, $annee);

        return response()->json([
            'status'  => 'success',
            'message' => "Rapport annuel de l'exercice {$annee} récupéré avec succès.",
            'data'    => $data,
        ]);
    }
}
