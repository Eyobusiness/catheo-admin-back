<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SuperAdmin\StoreFormuleRequest;
use App\Http\Requests\Api\V1\SuperAdmin\UpdateFormuleRequest;
use App\Http\Resources\Api\V1\SuperAdmin\FormuleResource;
use App\Models\Formule;
use App\Services\SuperAdmin\SuperAdminFormuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuperAdminFormuleController extends Controller
{
    public function __construct(
        protected SuperAdminFormuleService $formuleService
    ) {}

    /**
     * Liste de toutes les formules de tarification.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['statut', 'est_gratuite', 'all']);
        $prodInput = $request->input('produit') ?? $request->input('produit_id') ?? $request->input('produit_code');
        if (!empty($prodInput)) {
            $filters['produit_id'] = $prodInput;
        }

        $perPage = (int) $request->input('per_page', 15);

        $result = $this->formuleService->list($filters, $perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Liste des formules récupérée avec succès.',
            'data'    => FormuleResource::collection($result),
            'meta'    => method_exists($result, 'total') ? [
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
                'per_page'     => $result->perPage(),
                'total'        => $result->total(),
            ] : null,
        ]);
    }

    /**
     * Enregistrer une nouvelle formule.
     */
    public function store(StoreFormuleRequest $request): JsonResponse
    {
        $formule = $this->formuleService->create($request->validated());

        return response()->json([
            'status'  => 'success',
            'message' => 'Formule créée avec succès.',
            'data'    => new FormuleResource($formule->load('produit')),
        ], 201);
    }

    /**
     * Détails d'une formule.
     */
    public function show(Formule $formule): JsonResponse
    {
        $formule->load(['produit']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Détails de la formule récupérés avec succès.',
            'data'    => new FormuleResource($formule),
        ]);
    }

    /**
     * Mettre à jour une formule.
     */
    public function update(UpdateFormuleRequest $request, Formule $formule): JsonResponse
    {
        $formule = $this->formuleService->update($formule, $request->validated());

        return response()->json([
            'status'  => 'success',
            'message' => 'Formule mise à jour avec succès.',
            'data'    => new FormuleResource($formule->load('produit')),
        ]);
    }

    /**
     * Activer / désactiver une formule.
     */
    public function toggleStatus(Formule $formule): JsonResponse
    {
        $formule = $this->formuleService->toggleStatus($formule);

        return response()->json([
            'status'  => 'success',
            'message' => "Le statut de la formule a été basculé à [{$formule->statut}].",
            'data'    => new FormuleResource($formule->load('produit')),
        ]);
    }

    /**
     * Supprimer une formule.
     */
    public function destroy(Formule $formule): JsonResponse
    {
        $this->formuleService->delete($formule);

        return response()->json([
            'status'  => 'success',
            'message' => 'Formule supprimée avec succès.',
        ]);
    }
}
