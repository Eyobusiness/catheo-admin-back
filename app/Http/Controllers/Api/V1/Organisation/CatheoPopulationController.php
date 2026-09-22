<?php

namespace App\Http\Controllers\Api\V1\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Organisation\CatheoPopulationResource;
use App\Models\Organisation;
use App\Services\Organisation\CatheoPopulationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatheoPopulationController extends Controller
{
    public function __construct(
        protected CatheoPopulationService $populationService
    ) {}

    /**
     * Récupère la population de catéchumènes de la paroisse pour l'organisation
     * en appliquant rigoureusement les codes de section officiels et l'année courante.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        $filters = $request->only(['niveau_id', 'classe_id', 'sexe', 'search']);
        $perPage = (int) $request->input('per_page', 20);

        $result = $this->populationService->getPopulation($organisation, $filters, $perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Population catéchétique cible récupérée avec succès.',
            'data'    => CatheoPopulationResource::collection($result),
            'meta'    => [
                'type_organisation' => $organisation->type_organisation,
                'sections_cibles'   => $this->populationService->getTargetSectionCodes($organisation->type_organisation),
                'current_page'      => $result->currentPage(),
                'last_page'         => $result->lastPage(),
                'per_page'          => $result->perPage(),
                'total'             => $result->total(),
            ],
        ]);
    }
}
