<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePaiementRequest;
use App\Http\Resources\Api\V1\PaiementResource;
use App\Models\AnneeCatechese;
use App\Models\CaisseParoissiale;
use App\Models\Catechumene;
use App\Models\InscriptionAnnuelle;
use App\Models\LignePaiement;
use App\Models\Paiement;
use App\Models\Tarif;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PaiementController extends Controller
{
    /**
     * Liste des paiements / reçus avec pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $query = Paiement::with(['catechumene', 'anneeCatechese', 'lignes'])
            ->where('paroisse_configuration_id', $paroisseId);

        if ($request->filled('catechumene_id')) {
            $catId = Catechumene::where('uuid', $request->catechumene_id)->value('id');
            if ($catId) {
                $query->where('catechumene_id', $catId);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('numero_recu', 'like', "%{$search}%")
                  ->orWhere('reference_transaction', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->get('per_page', 15);
        $paiements = $query->latest('date_paiement')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => PaiementResource::collection($paiements->items()),
            'meta' => [
                'current_page' => $paiements->currentPage(),
                'last_page' => $paiements->lastPage(),
                'per_page' => $paiements->perPage(),
                'total' => $paiements->total(),
            ],
        ]);
    }

    /**
     * Enregistrer un paiement / encaissement.
     */
    public function store(StorePaiementRequest $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;
        $validated = $request->validated();

        $annee = AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->firstOrFail();
        $inscription = null;
        $catechumene = null;

        if (!empty($validated['inscription_annuelle_id'])) {
            $inscription = InscriptionAnnuelle::where('uuid', $validated['inscription_annuelle_id'])->firstOrFail();
            $catechumene = $inscription->catechumene;
        } elseif (!empty($validated['catechumene_id'])) {
            $catechumene = Catechumene::where('uuid', $validated['catechumene_id'])->firstOrFail();
        }

        $paiement = DB::transaction(function () use ($paroisseId, $annee, $inscription, $catechumene, $validated) {
            // 1. Calculer le montant total
            $montantTotal = 0;
            foreach ($validated['lignes'] as $ligne) {
                $qte = $ligne['quantite'] ?? 1;
                $montantTotal += $ligne['montant'] * $qte;
            }

            // 2. Générer le numéro de reçu unique (ex: REC-2026-0001)
            $prefixAnnee = date('Y', strtotime($validated['date_paiement']));
            $countRecu = Paiement::where('paroisse_configuration_id', $paroisseId)
                ->where('numero_recu', 'like', "REC-{$prefixAnnee}-%")
                ->count();
            $numeroRecu = sprintf("REC-%s-%04d", $prefixAnnee, $countRecu + 1);

            // 3. Créer le paiement
            $paiementObj = Paiement::create([
                'paroisse_configuration_id' => $paroisseId,
                'annee_catechese_id' => $annee->id,
                'inscription_annuelle_id' => $inscription?->id,
                'catechumene_id' => $catechumene?->id,
                'numero_recu' => $numeroRecu,
                'montant_total' => $montantTotal,
                'mode_paiement' => $validated['mode_paiement'],
                'reference_transaction' => $validated['reference_transaction'] ?? null,
                'date_paiement' => $validated['date_paiement'],
                'statut' => 'valide',
                'notes' => $validated['notes'] ?? null,
            ]);

            // 4. Créer les lignes de détail du paiement
            foreach ($validated['lignes'] as $l) {
                $tarifId = null;
                if (!empty($l['tarif_id'])) {
                    $tarifId = Tarif::where('uuid', $l['tarif_id'])->value('id');
                }
                $qte = $l['quantite'] ?? 1;
                $sousTotal = $l['montant'] * $qte;

                LignePaiement::create([
                    'paroisse_configuration_id' => $paroisseId,
                    'paiement_id' => $paiementObj->id,
                    'tarif_id' => $tarifId,
                    'designation' => $l['designation'],
                    'montant' => $l['montant'],
                    'quantite' => $qte,
                    'sous_total' => $sousTotal,
                ]);
            }

            // 5. Mettre à jour l'inscription si paiement des frais d'inscription
            if ($inscription) {
                $inscription->update(['frais_inscription_payes' => true]);
            }

            // 6. Écriture automatique dans la caisse paroissiale
            CaisseParoissiale::create([
                'paroisse_configuration_id' => $paroisseId,
                'annee_catechese_id' => $annee->id,
                'type_mouvement' => 'entree',
                'categorie' => 'inscription',
                'montant' => $montantTotal,
                'reference_document' => $numeroRecu,
                'libelle' => "Encaissement Reçu N° {$numeroRecu} - " . ($catechumene ? $catechumene->nom . ' ' . $catechumene->prenoms : 'Catéchumène'),
                'date_mouvement' => $validated['date_paiement'],
            ]);

            return $paiementObj;
        });

        $paiement->load(['catechumene', 'anneeCatechese', 'inscriptionAnnuelle', 'lignes']);

        return response()->json([
            'status' => 'success',
            'message' => "Paiement enregistré avec succès. N° Reçu : {$paiement->numero_recu}",
            'data' => new PaiementResource($paiement),
        ], 201);
    }

    /**
     * Détails d'un paiement.
     */
    public function show(Request $request, Paiement $paiement): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $paiement->paroisse_configuration_id);

        $paiement->load(['catechumene', 'anneeCatechese', 'inscriptionAnnuelle', 'lignes']);

        return response()->json([
            'status' => 'success',
            'data' => new PaiementResource($paiement),
        ]);
    }

    /**
     * Effectuer un remboursement sur un paiement / reçu donné.
     */
    public function rembourser(Request $request, string $uuid): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $validated = $request->validate([
            'montant_rembourse' => ['nullable', 'numeric', 'min:0.01'],
            'motif' => ['required', 'string', 'max:255'],
        ]);

        $paiement = Paiement::where('paroisse_configuration_id', $paroisseId)
            ->where('uuid', $uuid)
            ->firstOrFail();

        if ($paiement->statut === 'annule' || $paiement->statut === 'rembourse') {
            return response()->json([
                'status' => 'error',
                'message' => 'Ce paiement a déjà été remboursé ou annulé.',
            ], 422);
        }

        $montantRembourse = $validated['montant_rembourse'] ?? $paiement->montant_total;

        DB::transaction(function () use ($paroisseId, $paiement, $montantRembourse, $validated) {
            $paiement->update([
                'statut' => 'rembourse',
                'notes' => trim(($paiement->notes ?? '') . " | Remboursé: {$montantRembourse} F. Motif: " . $validated['motif']),
            ]);

            // Enregistrer l'opération de remboursement dans la caisse
            CaisseParoissiale::create([
                'paroisse_configuration_id' => $paroisseId,
                'annee_catechese_id' => $paiement->annee_catechese_id,
                'type_mouvement' => 'remboursement',
                'categorie' => 'remboursement',
                'montant' => $montantRembourse,
                'reference_document' => 'RMB-' . $paiement->numero_recu,
                'libelle' => "Remboursement Reçu N° {$paiement->numero_recu} - Motif: " . $validated['motif'],
                'date_mouvement' => now()->toDateString(),
            ]);
        });

        return response()->json([
            'status' => 'success',
            'message' => "Remboursement de {$montantRembourse} F effectué avec succès sur le reçu N° {$paiement->numero_recu}.",
            'data' => $paiement->fresh(['catechumene', 'lignes']),
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
