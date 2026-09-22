<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SuperAdmin\StorePaiementAbonnementRequest;
use App\Http\Resources\Api\V1\SuperAdmin\PaiementAbonnementResource;
use App\Models\EcheanceAbonnement;
use App\Models\PaiementAbonnement;
use App\Services\SuperAdmin\SuperAdminBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuperAdminPaiementController extends Controller
{
    public function __construct(
        protected SuperAdminBillingService $billingService
    ) {}

    /**
     * Liste des paiements plateforme encaissés.
     */
    public function index(Request $request): JsonResponse
    {
        $query = PaiementAbonnement::with([
            'echeance.abonnement.paroisse',
            'echeance.abonnement.formule.produit',
            'caissier',
        ])->latest('date_paiement');

        if ($request->filled('statut') && $request->statut !== 'tous') {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('mode_paiement')) {
            $query->where('mode_paiement', $request->mode_paiement);
        }

        if ($request->filled('echeance_id')) {
            $echInput = $request->echeance_id;
            $echId = is_numeric($echInput) ? (int) $echInput : EcheanceAbonnement::where('uuid', $echInput)->orWhere('reference', $echInput)->value('id');
            if ($echId) {
                $query->where('echeance_abonnement_id', $echId);
            }
        }

        if ($request->filled('date_debut')) {
            $query->whereDate('date_paiement', '>=', $request->date_debut);
        }

        if ($request->filled('date_fin')) {
            $query->whereDate('date_paiement', '<=', $request->date_fin);
        }

        $perPage = (int) $request->input('per_page', 15);
        $result = $query->paginate($perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Liste des paiements récupérée avec succès.',
            'data'    => PaiementAbonnementResource::collection($result),
            'meta'    => [
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
                'per_page'     => $result->perPage(),
                'total'        => $result->total(),
            ],
        ]);
    }

    /**
     * Enregistrer un paiement pour une échéance.
     */
    public function store(StorePaiementAbonnementRequest $request): JsonResponse
    {
        $echeance = EcheanceAbonnement::findOrFail($request->echeance_abonnement_id);

        $paiement = $this->billingService->enregistrerPaiement($echeance, $request->validated());

        return response()->json([
            'status'  => 'success',
            'message' => 'Paiement plateforme enregistré avec succès.',
            'data'    => new PaiementAbonnementResource($paiement->load(['echeance.abonnement', 'caissier'])),
        ], 201);
    }

    /**
     * Détails d'un paiement.
     */
    public function show(PaiementAbonnement $paiement): JsonResponse
    {
        $paiement->load([
            'echeance.abonnement.paroisse',
            'echeance.abonnement.formule.produit',
            'caissier',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Détails du paiement récupérés avec succès.',
            'data'    => new PaiementAbonnementResource($paiement),
        ]);
    }

    /**
     * Annuler un paiement.
     */
    public function annuler(Request $request, PaiementAbonnement $paiement): JsonResponse
    {
        $request->validate([
            'observation' => 'nullable|string|max:500',
        ]);

        $paiement = $this->billingService->annulerPaiement($paiement, $request->observation);

        return response()->json([
            'status'  => 'success',
            'message' => 'Paiement annulé avec succès.',
            'data'    => new PaiementAbonnementResource($paiement->fresh(['echeance', 'caissier'])),
        ]);
    }

    /**
     * Rembourser un paiement.
     */
    public function rembourser(Request $request, PaiementAbonnement $paiement): JsonResponse
    {
        $request->validate([
            'observation' => 'nullable|string|max:500',
        ]);

        $paiement = $this->billingService->rembourserPaiement($paiement, $request->observation);

        return response()->json([
            'status'  => 'success',
            'message' => 'Paiement remboursé avec succès.',
            'data'    => new PaiementAbonnementResource($paiement->fresh(['echeance', 'caissier'])),
        ]);
    }
}
