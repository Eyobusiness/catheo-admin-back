<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCaisseParoissialeRequest;
use App\Http\Resources\Api\V1\CaisseParoissialeResource;
use App\Models\AnneeCatechese;
use App\Models\CaisseParoissiale;
use App\Models\Paiement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CaisseParoissialeController extends Controller
{
    /**
     * Livre journal de caisse (Paiements Encaissés) avec KPIs (Screen 2).
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id ?? \App\Models\CatecheseConfiguration::value('id');

        $query = CaisseParoissiale::with('anneeCatechese');

        if ($paroisseId) {
            $query->where('paroisse_configuration_id', $paroisseId);
        }

        if ($request->filled('type_mouvement') && strtolower($request->type_mouvement) !== 'tous' && strtolower($request->type_mouvement) !== 'all') {
            $query->where('type_mouvement', $request->type_mouvement);
        } elseif ($request->filled('type') && strtolower($request->type) !== 'tous' && strtolower($request->type) !== 'all') {
            $query->where('type_mouvement', $request->type);
        }

        if ($request->filled('categorie') && strtolower($request->categorie) !== 'tous' && strtolower($request->categorie) !== 'all') {
            $query->where('categorie', $request->categorie);
        }

        if ($request->filled('annee_catechese_id') && $request->annee_catechese_id !== 'all' && $request->annee_catechese_id !== 'tous') {
            $val = $request->annee_catechese_id;
            $anneeId = is_numeric($val) ? (int) $val : AnneeCatechese::where('uuid', $val)->value('id');
            if ($anneeId) {
                $query->where('annee_catechese_id', $anneeId);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('libelle', 'like', "%{$search}%")
                  ->orWhere('reference_document', 'like', "%{$search}%")
                  ->orWhere('categorie', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_debut') && $request->filled('date_fin')) {
            $query->whereBetween('date_mouvement', [$request->date_debut, $request->date_fin]);
        } elseif ($request->filled('date_debut')) {
            $query->where('date_mouvement', '>=', $request->date_debut);
        } elseif ($request->filled('date_fin')) {
            $query->where('date_mouvement', '<=', $request->date_fin);
        }

        $mouvements = $query->orderBy('date_mouvement', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($request->integer('per_page', 15));

        // Calculs des KPIs
        $kpiQuery = CaisseParoissiale::query();
        $paiementQuery = Paiement::where('statut', 'valide');

        if ($paroisseId) {
            $kpiQuery->where('paroisse_configuration_id', $paroisseId);
            $paiementQuery->where('paroisse_configuration_id', $paroisseId);
        }

        if ($request->filled('annee_catechese_id') && $request->annee_catechese_id !== 'all' && $request->annee_catechese_id !== 'tous') {
            $val = $request->annee_catechese_id;
            $anneeId = is_numeric($val) ? (int) $val : AnneeCatechese::where('uuid', $val)->value('id');
            if ($anneeId) {
                $kpiQuery->where('annee_catechese_id', $anneeId);
                $paiementQuery->where('annee_catechese_id', $anneeId);
            }
        }

        $totalEncaisse = (float) (clone $kpiQuery)->whereIn('type_mouvement', ['entree', 'recette'])->sum('montant');
        $totalRembourse = (float) (clone $kpiQuery)->where('type_mouvement', 'remboursement')->sum('montant');
        $totalDepense = (float) (clone $kpiQuery)->whereIn('type_mouvement', ['sortie', 'depense'])->sum('montant');
        $totalSorties = (float) (clone $kpiQuery)->whereIn('type_mouvement', ['sortie', 'depense', 'remboursement'])->sum('montant');
        $soldeCaisse = $totalEncaisse - $totalSorties;

        $paiementsValidesCount = $paiementQuery->count();

        return response()->json([
            'status' => 'success',
            'kpis' => [
                'solde_en_caisse'        => $soldeCaisse,
                'solde_caisse'           => $soldeCaisse,
                'total_encaisse'         => $totalEncaisse,
                'total_entrees'          => $totalEncaisse,
                'total_rembourse'        => $totalRembourse,
                'total_depense'          => $totalDepense,
                'total_sorties'          => $totalSorties,
                'paiements_valides_count'=> $paiementsValidesCount,
            ],
            'data' => CaisseParoissialeResource::collection($mouvements->items()),
            'meta' => [
                'current_page'  => $mouvements->currentPage(),
                'last_page'     => $mouvements->lastPage(),
                'per_page'      => $mouvements->perPage(),
                'total'         => $mouvements->total(),
                'total_entrees' => $totalEncaisse,
                'total_sorties' => $totalSorties,
                'solde_caisse'  => $soldeCaisse,
            ],
        ]);
    }

    /**
     * Enregistrer une opération manuelle en caisse.
     */
    public function store(StoreCaisseParoissialeRequest $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id ?? \App\Models\CatecheseConfiguration::value('id');
        $validated = $request->validated();

        $annee = !empty($validated['annee_catechese_id'])
            ? (is_numeric($validated['annee_catechese_id']) ? AnneeCatechese::find($validated['annee_catechese_id']) : AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->first())
            : AnneeCatechese::resolveAnnee($request, $paroisseId);

        $validated['paroisse_configuration_id'] = $paroisseId;
        $validated['annee_catechese_id'] = $annee->id;

        $mouvement = CaisseParoissiale::create($validated);
        $mouvement->load('anneeCatechese');

        return response()->json([
            'status' => 'success',
            'message' => 'Mouvement de caisse enregistré avec succès.',
            'data' => new CaisseParoissialeResource($mouvement),
        ], 201);
    }

    /**
     * Supprimer une écriture de caisse.
     */
    public function destroy(Request $request, CaisseParoissiale $caisseParoissiale): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $caisseParoissiale->paroisse_configuration_id);

        $caisseParoissiale->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Mouvement de caisse supprimé avec succès.',
        ]);
    }

    /**
     * Rembourser une écriture d'encaissement de la caisse.
     */
    public function rembourser(Request $request, string $uuid): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $validated = $request->validate([
            'montant_rembourse' => ['nullable', 'numeric', 'min:0.01'],
            'motif' => ['required', 'string', 'max:255'],
        ]);

        $mouvement = CaisseParoissiale::where('paroisse_configuration_id', $paroisseId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        $montant = $validated['montant_rembourse'] ?? $mouvement->montant;

        $remboursement = CaisseParoissiale::create([
            'paroisse_configuration_id' => $paroisseId,
            'annee_catechese_id' => $mouvement->annee_catechese_id,
            'type_mouvement' => 'remboursement',
            'categorie' => 'remboursement',
            'montant' => $montant,
            'reference_document' => 'RMB-' . ($mouvement->reference_document ?? $mouvement->id),
            'libelle' => "Remboursement sur " . $mouvement->libelle . " - Motif: " . $validated['motif'],
            'date_mouvement' => now()->toDateString(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Remboursement de {$montant} F enregistré avec succès en Caisse.",
            'data' => new CaisseParoissialeResource($remboursement),
        ], 201);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
