<?php

namespace App\Http\Controllers\Api\V1\Organisation;

use App\Http\Controllers\Controller;
use App\Models\Organisation;
use App\Services\Organisation\CampagnePelerinageService;
use App\Services\Organisation\OrganisationExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class OrganisationExportController extends Controller
{
    public function __construct(
        protected OrganisationExportService $exportService,
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
     * Export CSV des membres.
     */
    public function membres(Request $request): StreamedResponse
    {
        $this->checkPermission($request, 'exports.read');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');
        $filters = $request->only(['statut', 'sexe', 'fonction']);

        return $this->exportService->exportMembres($organisation, $filters);
    }

    /**
     * Export CSV des activités.
     */
    public function activites(Request $request): StreamedResponse
    {
        $this->checkPermission($request, 'exports.read');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');
        $filters = $request->only(['statut', 'type_activite']);

        return $this->exportService->exportActivites($organisation, $filters);
    }

    /**
     * Export CSV des participants d'un pèlerinage.
     */
    public function participantsPelerinage(Request $request, $campagne): StreamedResponse
    {
        $this->checkPermission($request, 'exports.read');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        $campagneModel = $this->campagneService->find($organisation, $campagne);
        $filters = $request->only(['statut_inscription', 'statut_participation']);
        $typeExport = $request->input('type_export', 'general');

        return $this->exportService->exportParticipantsPelerinage($campagneModel, $filters, $typeExport);
    }

    /**
     * Export CSV des paiements d'un pèlerinage.
     */
    public function paiementsPelerinage(Request $request, $campagne): StreamedResponse
    {
        $this->checkPermission($request, 'exports.read');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        $campagneModel = $this->campagneService->find($organisation, $campagne);
        $filters = $request->only(['statut', 'mode_paiement']);

        return $this->exportService->exportPaiementsPelerinage($campagneModel, $filters);
    }

    /**
     * Export CSV des opérations de caisse.
     */
    public function operations(Request $request): StreamedResponse
    {
        $this->checkPermission($request, 'exports.read');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');
        $filters = $request->only(['type_operation', 'date_debut', 'date_fin']);

        return $this->exportService->exportOperations($organisation, $filters);
    }

    /**
     * Export de l'état de caisse (alias operations).
     */
    public function caisse(Request $request): StreamedResponse
    {
        return $this->operations($request);
    }
}
