<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Services\SuperAdmin\ActionAuditService;
use App\Services\SuperAdmin\SoftDeleteAuditService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuperAdminTrashController extends Controller
{
    public function __construct(
        protected SoftDeleteAuditService $trashService
    ) {}

    /**
     * Liste des éléments dans la corbeille centrale (tous modules confondus).
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['module', 'paroisse_id', 'organisation_id', 'date_debut', 'date_fin', 'search']);
        $perPage = (int) $request->input('per_page', 25);
        $page = (int) $request->input('page', 1);

        $paginator = $this->trashService->list($filters, $perPage, $page);

        return response()->json([
            'status'  => 'success',
            'message' => 'Corbeille centrale récupérée avec succès.',
            'data'    => $paginator->items(),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ]);
    }

    /**
     * Détails d'un élément présent dans la corbeille.
     */
    public function show(string $uuid): JsonResponse
    {
        $item = $this->trashService->find($uuid);

        if (!$item) {
            return response()->json([
                'status'  => 'error',
                'message' => "Élément introuvable dans la corbeille [{$uuid}].",
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Détails de l\'élément supprimé récupérés avec succès.',
            'data'    => $item,
        ]);
    }

    /**
     * Restaurer un élément depuis la corbeille.
     */
    public function restore(Request $request, string $uuid): JsonResponse
    {
        try {
            $found = $this->trashService->find($uuid);
            if (!$found) {
                return response()->json([
                    'status'  => 'error',
                    'message' => "Élément introuvable dans la corbeille [{$uuid}].",
                ], 404);
            }

            $currentUser = $request->user();
            $supprimePar = $found['supprime_par'] ?? 'Inconnu';
            $restaurePar = $currentUser ? ($currentUser->name . ' (' . $currentUser->email . ')') : 'Super Admin';

            $result = $this->trashService->restore($uuid);

            // Log d'audit enrichi Ancien état -> Nouvel état (Recommandation B)
            ActionAuditService::log(
                action: 'restore',
                module: 'Corbeille',
                description: "Restauration de l'élément [{$result['element']}] du module [{$result['module']}]",
                entite: [
                    'id'   => $found['model_instance']->id ?? null,
                    'uuid' => $uuid,
                    'nom'  => $result['element'],
                ],
                anciennesValeurs: [
                    'statut'       => 'supprime',
                    'deleted_at'   => $found['date_suppression'] ?? null,
                    'supprime_par' => $supprimePar,
                ],
                nouvellesValeurs: [
                    'statut'       => 'actif',
                    'deleted_at'   => null,
                    'restaure_par' => $restaurePar,
                ],
                request: $request
            );

            $result['supprime_par'] = $supprimePar;
            $result['restaure_par'] = $restaurePar;

            return response()->json([
                'status'  => 'success',
                'message' => $result['message'],
                'data'    => $result,
            ]);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $code);
        }
    }

    /**
     * Supprimer définitivement un élément (Force Delete).
     */
    public function force(Request $request, string $uuid): JsonResponse
    {
        try {
            $result = $this->trashService->forceDelete($uuid);

            // Log d'audit
            ActionAuditService::log(
                action: 'force_delete',
                module: 'Corbeille',
                description: "Suppression définitive (Force Delete) de l'élément [{$result['element']}] du module [{$result['module']}]",
                entite: ['uuid' => $uuid],
                anciennesValeurs: null,
                nouvellesValeurs: ['purged' => true],
                request: $request
            );

            return response()->json([
                'status'  => 'success',
                'message' => $result['message'],
                'data'    => $result,
            ]);
        } catch (Exception $e) {
            $code = $e->getCode() >= 400 && $e->getCode() < 600 ? $e->getCode() : 500;
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], $code);
        }
    }
}
