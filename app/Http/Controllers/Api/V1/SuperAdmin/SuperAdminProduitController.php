<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SuperAdmin\StoreProduitRequest;
use App\Http\Requests\Api\V1\SuperAdmin\UpdateProduitRequest;
use App\Http\Resources\Api\V1\SuperAdmin\ProduitResource;
use App\Models\Produit;
use App\Services\SuperAdmin\SuperAdminProduitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuperAdminProduitController extends Controller
{
    public function __construct(
        protected SuperAdminProduitService $produitService
    ) {}

    /**
     * Liste de tous les produits SaaS.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['statut', 'search', 'all']);
        $perPage = (int) $request->input('per_page', 15);

        $result = $this->produitService->list($filters, $perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Liste des produits récupérée avec succès.',
            'data'    => ProduitResource::collection($result),
            'meta'    => method_exists($result, 'total') ? [
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
                'per_page'     => $result->perPage(),
                'total'        => $result->total(),
            ] : null,
        ]);
    }

    /**
     * Enregistrer un nouveau produit.
     */
    public function store(StoreProduitRequest $request): JsonResponse
    {
        $produit = $this->produitService->create($request->validated());

        return response()->json([
            'status'  => 'success',
            'message' => 'Produit SaaS créé avec succès.',
            'data'    => new ProduitResource($produit),
        ], 201);
    }

    /**
     * Afficher les détails d'un produit.
     */
    public function show(Produit $produit): JsonResponse
    {
        $produit->load(['formules']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Détails du produit récupérés avec succès.',
            'data'    => new ProduitResource($produit),
        ]);
    }

    /**
     * Mettre à jour un produit existant.
     */
    public function update(UpdateProduitRequest $request, Produit $produit): JsonResponse
    {
        $produit = $this->produitService->update($produit, $request->validated());

        return response()->json([
            'status'  => 'success',
            'message' => 'Produit SaaS mis à jour avec succès.',
            'data'    => new ProduitResource($produit),
        ]);
    }

    /**
     * Activer / désactiver un produit.
     */
    public function toggleStatus(Produit $produit): JsonResponse
    {
        $produit = $this->produitService->toggleStatus($produit);

        return response()->json([
            'status'  => 'success',
            'message' => "Le statut du produit a été basculé à [{$produit->statut}].",
            'data'    => new ProduitResource($produit),
        ]);
    }

    /**
     * Supprimer un produit (soft delete).
     */
    public function destroy(Produit $produit): JsonResponse
    {
        $this->produitService->delete($produit);

        return response()->json([
            'status'  => 'success',
            'message' => 'Produit supprimé avec succès.',
        ]);
    }
}
