<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AnneeCatechese;
use App\Models\CaisseParoissiale;
use App\Models\VersementCure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VersementCureController extends Controller
{
    /**
     * Liste des versements au Curé avec compteurs KPI.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $query = VersementCure::with('user')
            ->where('paroisse_configuration_id', $paroisseId);

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhere('periode_concernee', 'like', "%{$search}%")
                  ->orWhere('effectue_par', 'like', "%{$search}%");
            });
        }

        $versements = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 15));

        // Calculs des KPIs (Screen 3)
        $totalEncaisseCaisse = (float) CaisseParoissiale::where('paroisse_configuration_id', $paroisseId)
            ->where('type_mouvement', 'recette')
            ->sum('montant');

        $totalVerseCure = (float) VersementCure::where('paroisse_configuration_id', $paroisseId)
            ->where('statut', 'valide')
            ->sum('montant_verse');

        $resteAReverser = max(0, $totalEncaisseCaisse - $totalVerseCure);

        return response()->json([
            'status' => 'success',
            'kpis' => [
                'total_en_caisse' => $totalEncaisseCaisse,
                'total_deja_verse' => $totalVerseCure,
                'reste_a_reverser' => $resteAReverser,
            ],
            'data' => $versements->items(),
            'meta' => [
                'current_page' => $versements->currentPage(),
                'last_page' => $versements->lastPage(),
                'per_page' => $versements->perPage(),
                'total' => $versements->total(),
            ],
        ]);
    }

    /**
     * Nouveau versement au Curé.
     */
    public function store(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $validated = $request->validate([
            'annee_catechese_id' => ['nullable', 'string', 'exists:annee_catecheses,uuid'],
            'periode_concernee' => ['required', 'string', 'max:100'],
            'montant_verse' => ['required', 'numeric', 'min:1'],
            'mode_remise' => ['required', 'string', 'in:cheque,especes,virement'],
            'effectue_par' => ['nullable', 'string', 'max:255'],
        ]);

        $annee = !empty($validated['annee_catechese_id'])
            ? AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->first()
            : AnneeCatechese::where('paroisse_configuration_id', $paroisseId)->where('est_active', true)->first();

        $reference = 'VRS-' . date('Y') . '-' . sprintf('%03d', VersementCure::where('paroisse_configuration_id', $paroisseId)->count() + 1);

        $versement = VersementCure::create([
            'paroisse_configuration_id' => $paroisseId,
            'annee_catechese_id' => $annee?->id,
            'user_id' => $request->user()->id,
            'reference' => $reference,
            'periode_concernee' => $validated['periode_concernee'],
            'montant_verse' => $validated['montant_verse'],
            'mode_remise' => $validated['mode_remise'],
            'effectue_par' => $validated['effectue_par'] ?? ($request->user()->name),
            'statut' => 'valide',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Versement enregistré avec succès',
            'data' => $versement->load('user'),
        ], 201);
    }

    /**
     * Détails d'un versement.
     */
    public function show(Request $request, string $uuid): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;
        $versement = VersementCure::with('user')
            ->where('paroisse_configuration_id', $paroisseId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        return response()->json([
            'status' => 'success',
            'data' => $versement,
        ]);
    }

    /**
     * Modifier un versement.
     */
    public function update(Request $request, string $uuid): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;
        $versement = VersementCure::where('paroisse_configuration_id', $paroisseId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $validated = $request->validate([
            'periode_concernee' => ['sometimes', 'string', 'max:100'],
            'montant_verse' => ['sometimes', 'numeric', 'min:1'],
            'mode_remise' => ['sometimes', 'string', 'in:cheque,especes,virement'],
            'effectue_par' => ['nullable', 'string', 'max:255'],
            'statut' => ['sometimes', 'string', 'in:valide,en_attente,annule'],
        ]);

        $versement->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Versement mis à jour',
            'data' => $versement->fresh('user'),
        ]);
    }

    /**
     * Supprimer / Annuler un versement.
     */
    public function destroy(Request $request, string $uuid): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;
        $versement = VersementCure::where('paroisse_configuration_id', $paroisseId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $versement->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Versement supprimé',
        ]);
    }
}
