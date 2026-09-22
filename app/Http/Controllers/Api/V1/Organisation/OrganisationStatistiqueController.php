<?php

namespace App\Http\Controllers\Api\V1\Organisation;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Services\Organisation\OrganisationStatistiqueService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class OrganisationStatistiqueController extends Controller
{
    public function __construct(
        protected OrganisationStatistiqueService $statistiqueService
    ) {}

    protected function checkPermission(Request $request, string $permission): void
    {
        $user = $request->user();
        if ($user && method_exists($user, 'hasPermission') && !$user->hasPermission($permission)) {
            throw new AccessDeniedHttpException("Vous ne disposez pas de la permission requise [{$permission}].");
        }
    }

    /**
     * Statistiques sur les membres.
     */
    public function membres(Request $request): JsonResponse
    {
        $this->checkPermission($request, 'statistiques.read');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');
        $filters = $request->only(['statut', 'sexe', 'fonction', 'date_debut', 'date_fin']);

        $data = $this->statistiqueService->getStatistiquesMembres($organisation, $filters);

        return response()->json([
            'status'  => 'success',
            'message' => 'Statistiques des membres récupérées avec succès.',
            'data'    => $data,
        ]);
    }

    /**
     * Statistiques sur les activités.
     */
    public function activites(Request $request): JsonResponse
    {
        $this->checkPermission($request, 'statistiques.read');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');
        $filters = $request->only(['statut', 'type_activite', 'date_debut', 'date_fin']);

        $data = $this->statistiqueService->getStatistiquesActivites($organisation, $filters);

        return response()->json([
            'status'  => 'success',
            'message' => 'Statistiques des activités récupérées avec succès.',
            'data'    => $data,
        ]);
    }

    /**
     * Statistiques sur les pèlerinages.
     */
    public function pelerinages(Request $request): JsonResponse
    {
        $this->checkPermission($request, 'statistiques.read');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');
        $filters = $request->only(['campagne_id', 'date_debut', 'date_fin', 'statut_inscription', 'type_participant']);

        $data = $this->statistiqueService->getStatistiquesPelerinages($organisation, $filters);

        return response()->json([
            'status'  => 'success',
            'message' => 'Statistiques des pèlerinages récupérées avec succès.',
            'data'    => $data,
        ]);
    }

    /**
     * Statistiques financières.
     */
    public function finances(Request $request): JsonResponse
    {
        $this->checkPermission($request, 'statistiques.read');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');
        $filters = $request->only(['date_debut', 'date_fin', 'campagne_id']);

        $data = $this->statistiqueService->getStatistiquesFinances($organisation, $filters);

        return response()->json([
            'status'  => 'success',
            'message' => 'Statistiques financières récupérées avec succès.',
            'data'    => $data,
        ]);
    }
}
