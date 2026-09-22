<?php

namespace App\Http\Controllers\Api\V1\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Organisation\StoreOrganisationUserRequest;
use App\Http\Requests\Api\V1\Organisation\UpdateOrganisationUserRequest;
use App\Http\Resources\Api\V1\Organisation\OrganisationUserResource;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Organisation\OrganisationUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganisationUserController extends Controller
{
    public function __construct(
        protected OrganisationUserService $userService
    ) {}

    /**
     * Liste des comptes utilisateurs rattachés à cette organisation.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        $filters = $request->only(['statut', 'search']);
        $perPage = (int) $request->input('per_page', 15);

        $result = $this->userService->list($organisation, $filters, $perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Liste des utilisateurs de l\'organisation récupérée avec succès.',
            'data'    => OrganisationUserResource::collection($result),
            'meta'    => [
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
                'per_page'     => $result->perPage(),
                'total'        => $result->total(),
            ],
        ]);
    }

    /**
     * Créer un utilisateur au sein de l'organisation.
     */
    public function store(StoreOrganisationUserRequest $request): JsonResponse
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        $user = $this->userService->create($organisation, $request->validated());

        return response()->json([
            'status'  => 'success',
            'message' => 'Compte utilisateur créé et associé à l\'organisation avec succès.',
            'data'    => new OrganisationUserResource($user->load('profil')),
        ], 201);
    }

    /**
     * Consulter un utilisateur d'organisation.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        if ((int) $user->organisation_id !== (int) $organisation->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Utilisateur introuvable dans cette organisation.',
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Fiche utilisateur récupérée avec succès.',
            'data'    => new OrganisationUserResource($user->load('profil')),
        ]);
    }

    /**
     * Mettre à jour un utilisateur.
     */
    public function update(UpdateOrganisationUserRequest $request, User $user): JsonResponse
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        if ((int) $user->organisation_id !== (int) $organisation->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Utilisateur introuvable dans cette organisation.',
            ], 404);
        }

        $updated = $this->userService->update($user, $request->validated());

        return response()->json([
            'status'  => 'success',
            'message' => 'Compte utilisateur mis à jour avec succès.',
            'data'    => new OrganisationUserResource($updated),
        ]);
    }

    /**
     * Activer / désactiver un compte utilisateur.
     */
    public function toggleStatus(Request $request, User $user): JsonResponse
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        if ((int) $user->organisation_id !== (int) $organisation->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Utilisateur introuvable dans cette organisation.',
            ], 404);
        }

        $updated = $this->userService->toggleStatus($user);

        return response()->json([
            'status'  => 'success',
            'message' => "Le statut du compte est désormais [{$updated->statut}].",
            'data'    => new OrganisationUserResource($updated),
        ]);
    }
}
