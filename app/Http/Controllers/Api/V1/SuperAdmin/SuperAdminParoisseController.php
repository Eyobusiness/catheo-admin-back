<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SuperAdmin\SuperAdminParoisseResource;
use App\Models\CatecheseConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuperAdminParoisseController extends Controller
{
    /**
     * Liste des paroisses supervisées avec leurs abonnements SaaS.
     */
    public function index(Request $request): JsonResponse
    {
        $query = CatecheseConfiguration::with([
            'abonnements.formule.produit',
        ])->latest('id');

        if ($request->filled('diocese')) {
            $query->where('diocese', 'like', "%{$request->diocese}%");
        }

        if ($request->filled('ville')) {
            $query->where('ville', 'like', "%{$request->ville}%");
        }

        if ($request->filled('statut') && $request->statut !== 'tous') {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nom_paroisse', 'like', "%{$search}%")
                  ->orWhere('code_paroisse', 'like', "%{$search}%")
                  ->orWhere('ville', 'like', "%{$search}%")
                  ->orWhere('diocese', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 15);
        $result = $query->paginate($perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Liste des paroisses récupérée avec succès.',
            'data'    => SuperAdminParoisseResource::collection($result),
            'meta'    => [
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
                'per_page'     => $result->perPage(),
                'total'        => $result->total(),
            ],
        ]);
    }

    /**
     * Détails d'une paroisse avec l'historique complet de ses abonnements.
     */
    public function show(string $id): JsonResponse
    {
        $paroisse = is_numeric($id)
            ? CatecheseConfiguration::with(['abonnements.formule.produit', 'abonnements.echeances.paiements'])->find((int) $id)
            : CatecheseConfiguration::with(['abonnements.formule.produit', 'abonnements.echeances.paiements'])->where('uuid', $id)->first();

        if (!$paroisse) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Paroisse introuvable.',
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Détails de la paroisse récupérés avec succès.',
            'data'    => new SuperAdminParoisseResource($paroisse),
        ]);
    }
}
