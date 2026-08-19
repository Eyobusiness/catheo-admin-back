<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AnneeCatechese;
use App\Models\Catechumene;
use App\Models\OperationPaiement;
use App\Models\Tarif;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OperationPaiementController extends Controller
{
    /**
     * Liste des opérations / paiements en attente.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $query = OperationPaiement::with(['catechumene', 'tarif', 'anneeCatechese'])
            ->where('paroisse_configuration_id', $paroisseId);

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhere('libelle', 'like', "%{$search}%")
                  ->orWhereHas('catechumene', function ($cq) use ($search) {
                      $cq->where('nom', 'like', "%{$search}%")
                        ->orWhere('prenoms', 'like', "%{$search}%");
                  });
            });
        }

        $operations = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data' => $operations->items(),
            'meta' => [
                'current_page' => $operations->currentPage(),
                'last_page' => $operations->lastPage(),
                'per_page' => $operations->perPage(),
                'total' => $operations->total(),
            ],
        ]);
    }

    /**
     * Créer une opération (Paiement en attente).
     */
    public function store(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $validated = $request->validate([
            'annee_catechese_id' => ['nullable', 'string', 'exists:annee_catecheses,uuid'],
            'catechumene_id' => ['nullable', 'string', 'exists:catechumenes,uuid'],
            'tarif_id' => ['nullable', 'string', 'exists:tarifs,uuid'],
            'libelle' => ['required', 'string', 'max:255'],
            'montant' => ['required', 'numeric', 'min:0'],
            'echeance' => ['nullable', 'date'],
        ]);

        $annee = !empty($validated['annee_catechese_id'])
            ? AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->first()
            : AnneeCatechese::where('paroisse_configuration_id', $paroisseId)->where('est_active', true)->first();

        $catechumeneId = !empty($validated['catechumene_id'])
            ? Catechumene::where('uuid', $validated['catechumene_id'])->value('id')
            : null;

        $tarifId = !empty($validated['tarif_id'])
            ? Tarif::where('uuid', $validated['tarif_id'])->value('id')
            : null;

        $reference = 'OP-' . date('Y') . '-' . sprintf('%03d', OperationPaiement::where('paroisse_configuration_id', $paroisseId)->count() + 1);

        $op = OperationPaiement::create([
            'paroisse_configuration_id' => $paroisseId,
            'annee_catechese_id' => $annee?->id,
            'catechumene_id' => $catechumeneId,
            'tarif_id' => $tarifId,
            'reference' => $reference,
            'libelle' => $validated['libelle'],
            'montant' => $validated['montant'],
            'montant_paye' => 0,
            'echeance' => $validated['echeance'] ?? null,
            'statut' => 'en_attente',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Opération créée avec succès',
            'data' => $op->load(['catechumene', 'tarif']),
        ], 201);
    }

    /**
     * Détails d'une opération.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;
        $op = OperationPaiement::with(['catechumene', 'tarif'])
            ->where('paroisse_configuration_id', $paroisseId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        return response()->json([
            'status' => 'success',
            'data' => $op,
        ]);
    }

    /**
     * Modifier une opération.
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;
        $op = OperationPaiement::where('paroisse_configuration_id', $paroisseId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $validated = $request->validate([
            'libelle' => ['sometimes', 'string', 'max:255'],
            'montant' => ['sometimes', 'numeric', 'min:0'],
            'echeance' => ['nullable', 'date'],
            'statut' => ['sometimes', 'string', 'in:en_attente,partiellement_paye,paye,annule'],
        ]);

        $op->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Opération mise à jour',
            'data' => $op->fresh(['catechumene', 'tarif']),
        ]);
    }

    /**
     * Supprimer une opération.
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;
        $op = OperationPaiement::where('paroisse_configuration_id', $paroisseId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $op->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Opération supprimée',
        ]);
    }
}
