<?php

namespace App\Http\Controllers\Api\V1\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Organisation\StoreActiviteRequest;
use App\Http\Requests\Api\V1\Organisation\UpdateActiviteRequest;
use App\Http\Resources\Api\V1\Organisation\ActiviteResource;
use App\Models\Activite;
use App\Models\Organisation;
use App\Services\Organisation\ActiviteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ActiviteController extends Controller
{
    public function __construct(
        protected ActiviteService $activiteService
    ) {}

    /**
     * Liste des activités de l'organisation.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        $filters = $request->only(['statut', 'type_activite', 'date_debut', 'date_fin', 'search']);
        $perPage = (int) $request->input('per_page', 15);

        $result = $this->activiteService->list($organisation, $filters, $perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Liste des activités récupérée avec succès.',
            'data'    => ActiviteResource::collection($result),
            'meta'    => [
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
                'per_page'     => $result->perPage(),
                'total'        => $result->total(),
            ],
        ]);
    }

    /**
     * Créer une nouvelle activité.
     */
    public function store(StoreActiviteRequest $request): JsonResponse
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        try {
            $activite = $this->activiteService->create($organisation, $request->validated());

            return response()->json([
                'status'  => 'success',
                'message' => 'Activité planifiée avec succès.',
                'data'    => new ActiviteResource($activite->load('responsable')),
            ], 201);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Détails d'une activité.
     */
    public function show(Request $request, Activite $activite): JsonResponse
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        if ((int) $activite->organisation_id !== (int) $organisation->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Activité introuvable dans cette organisation.',
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Détails de l\'activité récupérés avec succès.',
            'data'    => new ActiviteResource($activite->load('responsable')),
        ]);
    }

    /**
     * Mettre à jour une activité.
     */
    public function update(UpdateActiviteRequest $request, Activite $activite): JsonResponse
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        if ((int) $activite->organisation_id !== (int) $organisation->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Activité introuvable dans cette organisation.',
            ], 404);
        }

        try {
            $updated = $this->activiteService->update($activite, $request->validated());

            return response()->json([
                'status'  => 'success',
                'message' => 'Activité mise à jour avec succès.',
                'data'    => new ActiviteResource($updated),
            ]);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Supprimer une activité.
     */
    public function destroy(Request $request, Activite $activite): JsonResponse
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        if ((int) $activite->organisation_id !== (int) $organisation->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Activité introuvable dans cette organisation.',
            ], 404);
        }

        $this->activiteService->delete($activite);

        return response()->json([
            'status'  => 'success',
            'message' => 'Activité supprimée avec succès.',
        ]);
    }
}
