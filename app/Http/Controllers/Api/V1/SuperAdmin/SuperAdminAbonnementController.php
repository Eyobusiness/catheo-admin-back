<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SuperAdmin\StoreAbonnementRequest;
use App\Http\Resources\Api\V1\SuperAdmin\AbonnementResource;
use App\Models\Abonnement;
use App\Services\SuperAdmin\SuperAdminAbonnementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuperAdminAbonnementController extends Controller
{
    public function __construct(
        protected SuperAdminAbonnementService $abonnementService
    ) {}

    /**
     * Liste de tous les abonnements avec filtres.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['paroisse_id', 'organisation_id', 'produit_id', 'statut', 'context']);
        $perPage = (int) $request->input('per_page', 15);

        $result = $this->abonnementService->list($filters, $perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Liste des abonnements récupérée avec succès.',
            'data'    => AbonnementResource::collection($result),
            'meta'    => [
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
                'per_page'     => $result->perPage(),
                'total'        => $result->total(),
            ],
        ]);
    }

    /**
     * Liste des abonnements Paroisses uniquement (CATHEO).
     */
    public function paroisses(Request $request): JsonResponse
    {
        $filters = $request->only(['paroisse_id', 'produit_id', 'statut']);
        $filters['context'] = 'paroisse';
        $perPage = (int) $request->input('per_page', 15);

        $result = $this->abonnementService->list($filters, $perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Liste des abonnements paroisses récupérée avec succès.',
            'data'    => AbonnementResource::collection($result),
            'meta'    => [
                'context'      => 'paroisses',
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
                'per_page'     => $result->perPage(),
                'total'        => $result->total(),
            ],
        ]);
    }

    /**
     * Liste des abonnements Organisations uniquement (OPPE, OPPJ, OPPA).
     */
    public function organisations(Request $request): JsonResponse
    {
        $filters = $request->only(['organisation_id', 'produit_id', 'statut']);
        $filters['context'] = 'organisation';
        $perPage = (int) $request->input('per_page', 15);

        $result = $this->abonnementService->list($filters, $perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Liste des abonnements organisations récupérée avec succès.',
            'data'    => AbonnementResource::collection($result),
            'meta'    => [
                'context'      => 'organisations',
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
                'per_page'     => $result->perPage(),
                'total'        => $result->total(),
            ],
        ]);
    }

    /**
     * Souscrire une organisation à une formule spécifique à son produit.
     */
    public function storeOrganisation(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'organisation_id'            => 'required',
            'formule_id'                 => 'required',
            'date_debut'                 => 'nullable|date',
            'date_fin'                   => 'nullable|date|after_or_equal:date_debut',
            'renouvellement_automatique' => 'nullable|boolean',
            'observation'                => 'nullable|string',
        ]);

        try {
            $abonnement = $this->abonnementService->souscrireOrganisation($validated);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Abonnement d\'organisation souscrit avec succès.',
            'data'    => new AbonnementResource($abonnement),
        ], 201);
    }

    /**
     * Souscrire une paroisse à une formule.
     */
    public function store(StoreAbonnementRequest $request): JsonResponse
    {
        $abonnement = $this->abonnementService->souscrire($request->validated());

        return response()->json([
            'status'  => 'success',
            'message' => 'Abonnement souscrit avec succès.',
            'data'    => new AbonnementResource($abonnement),
        ], 201);
    }

    /**
     * Afficher les détails d'un abonnement.
     */
    public function show(Abonnement $abonnement): JsonResponse
    {
        $abonnement->load([
            'paroisse',
            'formule.produit',
            'echeances.paiements',
            'echeances.facture',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Détails de l\'abonnement récupérés avec succès.',
            'data'    => new AbonnementResource($abonnement),
        ]);
    }

    /**
     * Mettre à jour le statut d'un abonnement.
     */
    public function changerStatut(Request $request, Abonnement $abonnement): JsonResponse
    {
        $request->validate([
            'statut'      => 'required|string|in:' . implode(',', Abonnement::STATUTS),
            'observation' => 'nullable|string',
        ]);

        $abonnement = $this->abonnementService->changerStatut(
            $abonnement,
            $request->statut,
            $request->observation
        );

        return response()->json([
            'status'  => 'success',
            'message' => "Le statut de l'abonnement est désormais [{$abonnement->statut}].",
            'data'    => new AbonnementResource($abonnement),
        ]);
    }

    /**
     * Résilier un abonnement.
     */
    public function resilier(Request $request, Abonnement $abonnement): JsonResponse
    {
        $request->validate([
            'date_resiliation'  => 'nullable|date',
            'motif_resiliation' => 'required|string|max:500',
            'observation'       => 'nullable|string',
        ]);

        $abonnement = $this->abonnementService->resilier($abonnement, $request->all());

        return response()->json([
            'status'  => 'success',
            'message' => 'Abonnement résilié avec succès.',
            'data'    => new AbonnementResource($abonnement),
        ]);
    }
}
