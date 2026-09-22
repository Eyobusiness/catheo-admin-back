<?php

namespace App\Http\Controllers\Api\V1\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Organisation\StoreMembreRequest;
use App\Http\Requests\Api\V1\Organisation\UpdateMembreRequest;
use App\Http\Resources\Api\V1\Organisation\MembreResource;
use App\Models\Membre;
use App\Models\Organisation;
use App\Services\Organisation\MembreService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class MembreController extends Controller
{
    public function __construct(
        protected MembreService $membreService
    ) {}

    protected function checkPermission(Request $request, string $permission): void
    {
        $user = $request->user();
        if ($user && method_exists($user, 'hasPermission') && !$user->hasPermission($permission)) {
            throw new AccessDeniedHttpException("Vous ne disposez pas de la permission requise [{$permission}].");
        }
    }

    protected function checkAnyPermission(Request $request, array $permissions): void
    {
        $user = $request->user();
        if (!$user || !method_exists($user, 'hasPermission')) {
            return;
        }

        foreach ($permissions as $permission) {
            if ($user->hasPermission($permission)) {
                return;
            }
        }

        throw new AccessDeniedHttpException("Vous ne disposez pas des permissions requises.");
    }

    /**
     * Liste des membres de l'organisation.
     */
    public function index(Request $request): JsonResponse
    {
        $this->checkAnyPermission($request, ['membres.manage', 'membres.view']);
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        $filters = $request->only(['statut', 'sexe', 'fonction', 'search']);
        $perPage = (int) $request->input('per_page', 15);

        $result = $this->membreService->list($organisation, $filters, $perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Liste des membres récupérée avec succès.',
            'data'    => MembreResource::collection($result),
            'meta'    => [
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
                'per_page'     => $result->perPage(),
                'total'        => $result->total(),
            ],
        ]);
    }

    /**
     * Enregistrer un nouveau membre.
     */
    public function store(StoreMembreRequest $request): JsonResponse
    {
        $this->checkPermission($request, 'membres.manage');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        $membre = $this->membreService->create($organisation, $request->validated());

        return response()->json([
            'status'  => 'success',
            'message' => 'Membre enregistré avec succès.',
            'data'    => new MembreResource($membre),
        ], 201);
    }

    /**
     * Consulter la fiche d'un membre.
     */
    public function show(Request $request, Membre $membre): JsonResponse
    {
        $this->checkAnyPermission($request, ['membres.manage', 'membres.view']);

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        if ((int) $membre->organisation_id !== (int) $organisation->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Membre introuvable dans cette organisation.',
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Fiche du membre récupérée avec succès.',
            'data'    => new MembreResource($membre),
        ]);
    }

    /**
     * Mettre à jour un membre.
     */
    public function update(UpdateMembreRequest $request, Membre $membre): JsonResponse
    {
        $this->checkPermission($request, 'membres.manage');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        if ((int) $membre->organisation_id !== (int) $organisation->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Membre introuvable dans cette organisation.',
            ], 404);
        }

        $updated = $this->membreService->update($membre, $request->validated());

        return response()->json([
            'status'  => 'success',
            'message' => 'Membre mis à jour avec succès.',
            'data'    => new MembreResource($updated),
        ]);
    }

    /**
     * Supprimer un membre (Soft delete).
     */
    public function destroy(Request $request, Membre $membre): JsonResponse
    {
        $this->checkPermission($request, 'membres.manage');

        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        if ((int) $membre->organisation_id !== (int) $organisation->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Membre introuvable dans cette organisation.',
            ], 404);
        }

        $this->membreService->delete($membre);

        return response()->json([
            'status'  => 'success',
            'message' => 'Membre supprimé avec succès.',
        ]);
    }
}
