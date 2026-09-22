<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SuperAdmin\EcheanceAbonnementResource;
use App\Http\Resources\Api\V1\SuperAdmin\FactureResource;
use App\Models\Abonnement;
use App\Models\EcheanceAbonnement;
use App\Services\SuperAdmin\SuperAdminBillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuperAdminEcheanceController extends Controller
{
    public function __construct(
        protected SuperAdminBillingService $billingService
    ) {}

    /**
     * Liste des échéances avec filtres.
     */
    public function index(Request $request): JsonResponse
    {
        $query = EcheanceAbonnement::with(['abonnement.paroisse', 'abonnement.formule.produit', 'facture', 'paiements'])
            ->latest('date_echeance');

        if ($request->filled('abonnement_id')) {
            $aboInput = $request->abonnement_id;
            $aboId = is_numeric($aboInput) ? (int) $aboInput : Abonnement::where('uuid', $aboInput)->orWhere('reference', $aboInput)->value('id');
            if ($aboId) {
                $query->where('abonnement_id', $aboId);
            }
        }

        if ($request->filled('statut') && $request->statut !== 'tous') {
            $query->where('statut', $request->statut);
        }

        if ($request->boolean('en_retard')) {
            $query->where('statut', EcheanceAbonnement::STATUT_EN_ATTENTE)
                  ->where('date_echeance', '<', now()->toDateString());
        }

        $perPage = (int) $request->input('per_page', 15);
        $result = $query->paginate($perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Liste des échéances récupérée avec succès.',
            'data'    => EcheanceAbonnementResource::collection($result),
            'meta'    => [
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
                'per_page'     => $result->perPage(),
                'total'        => $result->total(),
            ],
        ]);
    }

    /**
     * Détails d'une échéance.
     */
    public function show(EcheanceAbonnement $echeance): JsonResponse
    {
        $echeance->load([
            'abonnement.paroisse',
            'abonnement.formule.produit',
            'facture',
            'paiements.caissier',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Détails de l\'échéance récupérés avec succès.',
            'data'    => new EcheanceAbonnementResource($echeance),
        ]);
    }

    /**
     * Générer une facture pour une échéance si elle n'en a pas encore.
     */
    public function genererFacture(Request $request, EcheanceAbonnement $echeance): JsonResponse
    {
        $data = $request->validate([
            'taux_tva'      => 'nullable|numeric|min:0|max:100',
            'date_facture'  => 'nullable|date',
            'description'   => 'nullable|string',
            'observation'   => 'nullable|string',
        ]);

        $facture = $this->billingService->createFactureForEcheance($echeance, $data);

        return response()->json([
            'status'  => 'success',
            'message' => 'Facture générée avec succès.',
            'data'    => new FactureResource($facture),
        ], 201);
    }
}
