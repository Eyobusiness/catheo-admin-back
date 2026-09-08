<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\VersementResource;
use App\Models\AnneeCatechese;
use App\Models\CaisseParoissiale;
use App\Models\Versement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VersementController extends Controller
{
    /**
     * Résout un Versement par UUID ou ID.
     */
    private function resolveVersement(mixed $versement): Versement
    {
        if ($versement instanceof Versement) {
            return $versement;
        }

        $item = is_numeric($versement)
            ? Versement::find((int) $versement)
            : Versement::where('uuid', $versement)->first();

        if (!$item) {
            abort(response()->json([
                'status'  => 'error',
                'message' => 'Versement introuvable.',
            ], 404));
        }

        return $item;
    }

    /**
     * Liste des versements avec compteurs KPI.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $query = Versement::with(['user', 'anneeCatechese']);

        if ($paroisseId) {
            $query->where('paroisse_configuration_id', $paroisseId);
        }

        if ($request->filled('statut') && strtolower($request->statut) !== 'tous') {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('annee_catechese_id')) {
            $val = $request->annee_catechese_id;
            if ($val !== 'all') {
                $anneeId = is_numeric($val) ? (int) $val : AnneeCatechese::where('uuid', $val)->value('id');
                if ($anneeId) {
                    $query->where('annee_catechese_id', $anneeId);
                }
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhere('periode_concernee', 'like', "%{$search}%")
                  ->orWhere('effectue_par', 'like', "%{$search}%")
                  ->orWhere('destinataire', 'like', "%{$search}%");
            });
        }

        $versements = $query->orderBy('created_at', 'desc')
            ->paginate($request->integer('per_page', 15));

        // Calculs des KPIs
        $caisseQuery = CaisseParoissiale::query();
        $versementQuery = Versement::where('statut', 'valide');

        if ($paroisseId) {
            $caisseQuery->where('paroisse_configuration_id', $paroisseId);
            $versementQuery->where('paroisse_configuration_id', $paroisseId);
        }

        if ($request->filled('annee_catechese_id')) {
            $val = $request->annee_catechese_id;
            if ($val !== 'all' && $val !== 'tous') {
                $anneeId = is_numeric($val) ? (int) $val : AnneeCatechese::where('uuid', $val)->value('id');
                if ($anneeId) {
                    $caisseQuery->where('annee_catechese_id', $anneeId);
                    $versementQuery->where('annee_catechese_id', $anneeId);
                }
            }
        }

        $totalEncaisseCaisse = (float) (clone $caisseQuery)->whereIn('type_mouvement', ['entree', 'recette'])->sum('montant');
        $totalSortiesCaisse = (float) (clone $caisseQuery)->whereIn('type_mouvement', ['sortie', 'depense', 'remboursement'])->sum('montant');
        $totalVerse = (float) $versementQuery->sum('montant_verse');
        
        // Le montant restant à reverser est exactement le solde disponible en caisse
        $resteAReverser = max(0, $totalEncaisseCaisse - $totalSortiesCaisse - $totalVerse);

        return response()->json([
            'status' => 'success',
            'kpis' => [
                'total_en_caisse'  => $totalEncaisseCaisse,
                'total_encaisse'   => $totalEncaisseCaisse,
                'total_sorties'    => $totalSortiesCaisse,
                'total_deja_verse' => $totalVerse,
                'reste_a_reverser' => $resteAReverser,
                'solde_en_caisse'  => $resteAReverser,
            ],
            'data' => VersementResource::collection($versements->items()),
            'meta' => [
                'current_page' => $versements->currentPage(),
                'last_page'    => $versements->lastPage(),
                'per_page'     => $versements->perPage(),
                'total'        => $versements->total(),
            ],
        ]);
    }

    /**
     * Nouveau versement.
     */
    public function store(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id ?? \App\Models\CatecheseConfiguration::value('id');

        $validated = $request->validate([
            'annee_catechese_id' => ['nullable', 'string'],
            'periode_concernee'  => ['required', 'string', 'max:100'],
            'montant_verse'      => ['required', 'numeric', 'min:1'],
            'mode_remise'        => ['required', 'string'],
            'effectue_par'       => ['nullable', 'string', 'max:255'],
            'destinataire'       => ['nullable', 'string', 'max:255'],
        ]);

        $annee = !empty($validated['annee_catechese_id'])
            ? (is_numeric($validated['annee_catechese_id']) ? AnneeCatechese::find($validated['annee_catechese_id']) : AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->first())
            : AnneeCatechese::resolveAnnee($request, $paroisseId);

        $refQuery = Versement::query();
        if ($paroisseId) {
            $refQuery->where('paroisse_configuration_id', $paroisseId);
        }
        $refCount = $refQuery->count() + 1;
        $reference = 'VRS-' . date('Y') . '-' . sprintf('%04d', $refCount);

        $versement = Versement::create([
            'paroisse_configuration_id' => $paroisseId,
            'annee_catechese_id'        => $annee?->id,
            'user_id'                   => $request->user()->id,
            'reference'                 => $reference,
            'periode_concernee'         => $validated['periode_concernee'],
            'montant_verse'             => $validated['montant_verse'],
            'mode_remise'               => $validated['mode_remise'],
            'effectue_par'              => $validated['effectue_par'] ?? ($request->user()->name),
            'destinataire'              => $validated['destinataire'] ?? null,
            'statut'                    => 'valide',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Versement enregistré avec succès.',
            'data'    => new VersementResource($versement->load(['user', 'anneeCatechese'])),
        ], 201);
    }

    /**
     * Détails d'un versement.
     */
    public function show(Request $request, mixed $versement): JsonResponse
    {
        $v = $this->resolveVersement($versement);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $v->paroisse_configuration_id);

        return response()->json([
            'status' => 'success',
            'data'   => new VersementResource($v->load(['user', 'anneeCatechese'])),
        ]);
    }

    /**
     * Modifier un versement.
     */
    public function update(Request $request, mixed $versement): JsonResponse
    {
        $v = $this->resolveVersement($versement);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $v->paroisse_configuration_id);

        $validated = $request->validate([
            'periode_concernee' => ['sometimes', 'string', 'max:100'],
            'montant_verse'     => ['sometimes', 'numeric', 'min:1'],
            'mode_remise'       => ['sometimes', 'string'],
            'effectue_par'      => ['nullable', 'string', 'max:255'],
            'destinataire'      => ['nullable', 'string', 'max:255'],
            'statut'            => ['sometimes', 'string', 'in:valide,en_attente,annule'],
        ]);

        $v->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Versement mis à jour avec succès.',
            'data'    => new VersementResource($v->fresh(['user', 'anneeCatechese'])),
        ]);
    }

    /**
     * Supprimer / Annuler un versement.
     */
    public function destroy(Request $request, mixed $versement): JsonResponse
    {
        $v = $this->resolveVersement($versement);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $v->paroisse_configuration_id);

        $v->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Versement supprimé avec succès.',
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, ?int $targetParoisseId): void
    {
        if ($userParoisseId && $targetParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
