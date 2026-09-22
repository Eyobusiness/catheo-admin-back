<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SuperAdmin\FactureResource;
use App\Models\Facture;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuperAdminFactureController extends Controller
{
    /**
     * Liste des factures émises.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Facture::with([
            'echeance.abonnement.paroisse',
            'echeance.abonnement.formule.produit',
        ])->latest('date_facture');

        if ($request->filled('statut') && $request->statut !== 'tous') {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('date_debut')) {
            $query->whereDate('date_facture', '>=', $request->date_debut);
        }

        if ($request->filled('date_fin')) {
            $query->whereDate('date_facture', '<=', $request->date_fin);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('echeance.abonnement.paroisse', function ($qp) use ($search) {
                      $qp->where('nom_paroisse', 'like', "%{$search}%");
                  });
            });
        }

        $perPage = (int) $request->input('per_page', 15);
        $result = $query->paginate($perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Liste des factures récupérée avec succès.',
            'data'    => FactureResource::collection($result),
            'meta'    => [
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
                'per_page'     => $result->perPage(),
                'total'        => $result->total(),
            ],
        ]);
    }

    /**
     * Détails d'une facture.
     */
    public function show(Facture $facture): JsonResponse
    {
        $facture->load([
            'echeance.abonnement.paroisse',
            'echeance.abonnement.formule.produit',
            'echeance.paiements',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Détails de la facture récupérés avec succès.',
            'data'    => new FactureResource($facture),
        ]);
    }
}
