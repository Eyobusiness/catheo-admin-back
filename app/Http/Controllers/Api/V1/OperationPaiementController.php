<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PaiementResource;
use App\Models\AnneeCatechese;
use App\Models\CaisseParoissiale;
use App\Models\Catechumene;
use App\Models\InscriptionAnnuelle;
use App\Models\LignePaiement;
use App\Models\Niveau;
use App\Models\OperationPaiement;
use App\Models\Paiement;
use App\Models\Tarif;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OperationPaiementController extends Controller
{
    /**
     * Résout une Opération par instance, UUID ou ID.
     */
    private function resolveOperation(mixed $operation): OperationPaiement
    {
        if ($operation instanceof OperationPaiement) {
            return $operation;
        }

        $item = is_numeric($operation)
            ? OperationPaiement::find((int) $operation)
            : OperationPaiement::where('uuid', $operation)->first();

        if (!$item) {
            abort(response()->json([
                'status'  => 'error',
                'message' => 'Opération de paiement introuvable.',
            ], 404));
        }

        return $item;
    }

    /**
     * Liste des opérations / paiements en attente avec filtres.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $query = OperationPaiement::with(['catechumene', 'tarif', 'anneeCatechese']);

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

        if ($request->filled('tarif_id')) {
            $tVal = $request->tarif_id;
            $tarifId = is_numeric($tVal) ? (int) $tVal : Tarif::where('uuid', $tVal)->value('id');
            if ($tarifId) {
                $query->where('tarif_id', $tarifId);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference', 'like', "%{$search}%")
                  ->orWhere('libelle', 'like', "%{$search}%")
                  ->orWhereHas('catechumene', function ($cq) use ($search) {
                      $cq->where('nom', 'like', "%{$search}%")
                        ->orWhere('prenoms', 'like', "%{$search}%")
                        ->orWhere('matricule', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->boolean('all') || $request->get('per_page') === 'all') {
            $items = $query->orderBy('created_at', 'desc')->get();
            return response()->json([
                'status' => 'success',
                'data'   => $items,
            ]);
        }

        $perPage = $request->integer('per_page', 20);
        $operations = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data'   => $operations->items(),
            'meta'   => [
                'current_page' => $operations->currentPage(),
                'last_page'    => $operations->lastPage(),
                'per_page'     => $operations->perPage(),
                'total'        => $operations->total(),
            ],
        ]);
    }

    /**
     * Créer manuellement une opération (Paiement en attente).
     */
    public function store(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $validated = $request->validate([
            'annee_catechese_id' => ['nullable', 'string'],
            'catechumene_id'     => ['nullable', 'string'],
            'tarif_id'           => ['nullable', 'string'],
            'libelle'            => ['required', 'string', 'max:255'],
            'montant'            => ['required', 'numeric', 'min:0'],
            'echeance'           => ['nullable', 'date'],
        ]);

        $annee = !empty($validated['annee_catechese_id'])
            ? (is_numeric($validated['annee_catechese_id']) ? AnneeCatechese::find($validated['annee_catechese_id']) : AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->first())
            : AnneeCatechese::resolveAnnee($request, $paroisseId);

        $catechumeneId = null;
        if (!empty($validated['catechumene_id'])) {
            $cVal = $validated['catechumene_id'];
            $catechumeneId = is_numeric($cVal) ? (int)$cVal : Catechumene::where('uuid', $cVal)->value('id');
        }

        $tarifId = null;
        if (!empty($validated['tarif_id'])) {
            $tVal = $validated['tarif_id'];
            $tarifId = is_numeric($tVal) ? (int)$tVal : Tarif::where('uuid', $tVal)->value('id');
        }

        $reference = 'OP-' . date('Y') . '-' . sprintf('%04d', OperationPaiement::where('paroisse_configuration_id', $paroisseId)->count() + 1);

        $op = OperationPaiement::create([
            'paroisse_configuration_id' => $paroisseId,
            'annee_catechese_id'        => $annee?->id,
            'catechumene_id'            => $catechumeneId,
            'tarif_id'                  => $tarifId,
            'reference'                 => $reference,
            'libelle'                   => $validated['libelle'],
            'montant'                   => $validated['montant'],
            'montant_paye'              => 0,
            'echeance'                  => $validated['echeance'] ?? null,
            'statut'                    => 'en_attente',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Opération de paiement créée avec succès.',
            'data'    => $op->load(['catechumene', 'tarif', 'anneeCatechese']),
        ], 201);
    }

    /**
     * Génération en masse d'opérations de paiement pour tous les catéchumènes d'un Tarif.
     * Route: POST /api/v1/tarifs/{tarif}/generer-operations
     * OU:    POST /api/v1/operations-paiements/generer-par-tarif
     */
    public function genererParTarif(Request $request, mixed $tarif = null): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $tarifIdOrUuid = $tarif ?? $request->input('tarif_id');
        if (!$tarifIdOrUuid) {
            return response()->json(['status' => 'error', 'message' => 'Tarif obligatoire pour la génération.'], 422);
        }

        $tarifObj = is_numeric($tarifIdOrUuid) ? Tarif::find($tarifIdOrUuid) : Tarif::where('uuid', $tarifIdOrUuid)->first();
        if (!$tarifObj) {
            return response()->json(['status' => 'error', 'message' => 'Tarif introuvable.'], 404);
        }

        $anneeId = $tarifObj->annee_catechese_id ?? AnneeCatechese::resolveAnnee($request, $paroisseId)?->id;

        // Trouver les niveaux concernés par ce tarif
        $niveauIds = [];
        if ($tarifObj->niveau_id) {
            $niveauIds[] = $tarifObj->niveau_id;
        }
        $attachedNiveaux = $tarifObj->niveaux()->pluck('niveaux.id')->toArray();
        $niveauIds = array_unique(array_merge($niveauIds, $attachedNiveaux));

        // Récupérer les inscriptions cibles
        $inscriptionsQuery = InscriptionAnnuelle::with('catechumene')
            ->where('annee_catechese_id', $anneeId);

        if ($paroisseId) {
            $inscriptionsQuery->where('paroisse_configuration_id', $paroisseId);
        }

        if (!empty($niveauIds)) {
            $inscriptionsQuery->whereIn('niveau_id', $niveauIds);
        }

        $inscriptions = $inscriptionsQuery->get();
        $generatedCount = 0;

        foreach ($inscriptions as $ins) {
            if (!$ins->catechumene_id) {
                continue;
            }

            // Vérifier si une opération existe déjà pour ce catéchumène et ce tarif pour l'année
            $existing = OperationPaiement::where('annee_catechese_id', $anneeId)
                ->where('catechumene_id', $ins->catechumene_id)
                ->where('tarif_id', $tarifObj->id)
                ->exists();

            if (!$existing) {
                $refCount = OperationPaiement::where('paroisse_configuration_id', $ins->paroisse_configuration_id)->count() + 1;
                $reference = 'OP-' . date('Y') . '-' . sprintf('%04d', $refCount);

                OperationPaiement::create([
                    'paroisse_configuration_id' => $ins->paroisse_configuration_id,
                    'annee_catechese_id'        => $anneeId,
                    'catechumene_id'            => $ins->catechumene_id,
                    'tarif_id'                  => $tarifObj->id,
                    'reference'                 => $reference,
                    'libelle'                   => "{$tarifObj->intitule} - {$ins->catechumene->nom_complet}",
                    'montant'                   => (float) $tarifObj->montant,
                    'montant_paye'              => 0,
                    'echeance'                  => $tarifObj->periode_fin?->toDateString() ?? now()->addMonths(1)->toDateString(),
                    'statut'                    => 'en_attente',
                ]);
                $generatedCount++;
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => "{$generatedCount} opération(s) de paiement générée(s) avec succès pour le tarif '{$tarifObj->intitule}'.",
            'count'   => $generatedCount,
        ]);
    }

    /**
     * Rattrapage de génération d'opération de paiement pour une inscription annuelle spécifique.
     * Route: POST /api/v1/inscriptions-annuelles/{inscription}/generer-operation-paiement
     */
    public function genererParInscription(Request $request, mixed $inscription): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $ins = is_numeric($inscription)
            ? InscriptionAnnuelle::with(['catechumene', 'niveau'])->find($inscription)
            : InscriptionAnnuelle::with(['catechumene', 'niveau'])->where('uuid', $inscription)->first();

        if (!$ins) {
            return response()->json(['status' => 'error', 'message' => 'Inscription introuvable.'], 404);
        }

        // Trouver le tarif d'inscription
        $tarif = Tarif::where('paroisse_configuration_id', $ins->paroisse_configuration_id)
            ->where('type_tarif', 'inscription')
            ->where(function ($q) use ($ins) {
                $q->where('niveau_id', $ins->niveau_id)
                  ->orWhereNull('niveau_id');
            })
            ->first();

        $montant = $tarif ? (float) $tarif->montant : 15000;
        $refCount = OperationPaiement::where('paroisse_configuration_id', $ins->paroisse_configuration_id)->count() + 1;
        $reference = 'OP-' . date('Y') . '-' . sprintf('%04d', $refCount);

        $op = OperationPaiement::firstOrCreate(
            [
                'paroisse_configuration_id' => $ins->paroisse_configuration_id,
                'catechumene_id'            => $ins->catechumene_id,
                'annee_catechese_id'        => $ins->annee_catechese_id,
                'statut'                    => 'en_attente',
            ],
            [
                'tarif_id'     => $tarif?->id,
                'reference'    => $reference,
                'libelle'      => "Frais d'inscription - {$ins->catechumene?->nom_complet} (" . ($ins->niveau?->nom ?? 'Catéchèse') . ")",
                'montant'      => $montant,
                'montant_paye' => 0,
                'echeance'     => now()->addMonths(1)->toDateString(),
                'statut'       => 'en_attente',
            ]
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Opération de paiement pour l\'inscription générée.',
            'data'    => $op->load(['catechumene', 'tarif']),
        ]);
    }

    /**
     * Encaisser directement une opération de paiement (Action rapide "Payer").
     * Route: POST /api/v1/operations-paiements/{operation}/payer
     */
    public function payer(Request $request, mixed $operation): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;
        $op = $this->resolveOperation($operation);

        $this->authorizeTenant($paroisseId, $op->paroisse_configuration_id);

        if ($op->statut === 'paye') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Cette opération de paiement a déjà été entièrement réglée.',
            ], 422);
        }

        $validated = $request->validate([
            'mode_paiement'         => ['required', 'string'],
            'reference_transaction' => ['nullable', 'string', 'max:255'],
            'date_paiement'         => ['nullable', 'date'],
            'notes'                 => ['nullable', 'string'],
        ]);

        $datePaiement = $validated['date_paiement'] ?? now()->toDateString();
        $modePaiement = $validated['mode_paiement'];
        $montantTotal = (float) $op->montant;

        $paiement = DB::transaction(function () use ($paroisseId, $op, $datePaiement, $modePaiement, $montantTotal, $validated) {
            // 1. Générer le numéro de reçu officiel (ex: REC26-124002-0001)
            $numeroRecu = app(\App\Services\ReceiptNumberGeneratorService::class)->generate($op->paroisse_configuration_id, $datePaiement);

            // Trouver l'inscription annuelle rattachée
            $inscription = InscriptionAnnuelle::where('catechumene_id', $op->catechumene_id)
                ->where('annee_catechese_id', $op->annee_catechese_id)
                ->latest()
                ->first();

            // 2. Créer le paiement
            $paiementObj = Paiement::create([
                'paroisse_configuration_id' => $op->paroisse_configuration_id,
                'annee_catechese_id'        => $op->annee_catechese_id,
                'inscription_annuelle_id'   => $inscription?->id,
                'catechumene_id'            => $op->catechumene_id,
                'numero_recu'               => $numeroRecu,
                'montant_total'             => $montantTotal,
                'mode_paiement'             => $modePaiement,
                'reference_transaction'     => $validated['reference_transaction'] ?? null,
                'date_paiement'             => $datePaiement,
                'statut'                    => 'valide',
                'notes'                     => $validated['notes'] ?? "Règlement direct de l'opération {$op->reference}",
            ]);

            // 3. Créer la ligne de paiement détaillée
            LignePaiement::create([
                'paroisse_configuration_id' => $op->paroisse_configuration_id,
                'paiement_id'               => $paiementObj->id,
                'tarif_id'                  => $op->tarif_id,
                'designation'               => $op->libelle,
                'montant'                   => $montantTotal,
                'quantite'                  => 1,
                'sous_total'                => $montantTotal,
            ]);

            // 4. Mettre à jour l'opération de paiement
            $op->update([
                'statut'       => 'paye',
                'montant_paye' => $montantTotal,
            ]);

            // 5. Si c'est des frais d'inscription, valider l'inscription
            if ($inscription) {
                $inscription->update(['frais_inscription_payes' => true]);
            }

            // 6. Écriture dans le journal de caisse paroissiale
            $catechumene = $op->catechumene;
            $nomBeneficiaire = $catechumene ? trim("{$catechumene->nom} {$catechumene->prenoms}") : 'Catéchumène';

            CaisseParoissiale::create([
                'paroisse_configuration_id' => $op->paroisse_configuration_id,
                'annee_catechese_id'        => $op->annee_catechese_id,
                'type_mouvement'            => 'entree',
                'categorie'                 => ($op->tarif?->type_tarif ?? 'recette_autre'),
                'montant'                   => $montantTotal,
                'reference_document'        => $numeroRecu,
                'libelle'                   => "Encaissement Reçu N° {$numeroRecu} - {$nomBeneficiaire} ({$op->libelle})",
                'date_mouvement'            => $datePaiement,
            ]);

            return $paiementObj;
        });

        $paiement->load(['catechumene', 'anneeCatechese', 'inscriptionAnnuelle', 'lignes']);

        return response()->json([
            'status'    => 'success',
            'message'   => "Paiement validé avec succès. Reçu N° {$paiement->numero_recu}",
            'data'      => new PaiementResource($paiement),
            'operation' => $op->fresh(['catechumene', 'tarif']),
        ]);
    }

    /**
     * Détails d'une opération.
     */
    public function show(Request $request, mixed $operation): JsonResponse
    {
        $op = $this->resolveOperation($operation);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $op->paroisse_configuration_id);

        return response()->json([
            'status' => 'success',
            'data'   => $op->load(['catechumene', 'tarif', 'anneeCatechese']),
        ]);
    }

    /**
     * Modifier une opération.
     */
    public function update(Request $request, mixed $operation): JsonResponse
    {
        $op = $this->resolveOperation($operation);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $op->paroisse_configuration_id);

        $validated = $request->validate([
            'libelle'  => ['sometimes', 'string', 'max:255'],
            'montant'  => ['sometimes', 'numeric', 'min:0'],
            'echeance' => ['nullable', 'date'],
            'statut'   => ['sometimes', 'string', 'in:en_attente,partiellement_paye,paye,annule'],
        ]);

        $op->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Opération mise à jour avec succès.',
            'data'    => $op->fresh(['catechumene', 'tarif', 'anneeCatechese']),
        ]);
    }

    /**
     * Supprimer une opération.
     */
    public function destroy(Request $request, mixed $operation): JsonResponse
    {
        $op = $this->resolveOperation($operation);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $op->paroisse_configuration_id);

        $op->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Opération supprimée avec succès.',
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, ?int $targetParoisseId): void
    {
        if ($userParoisseId && $targetParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
