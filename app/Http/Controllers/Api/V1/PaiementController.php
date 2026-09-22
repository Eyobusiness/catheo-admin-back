<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePaiementRequest;
use App\Http\Resources\Api\V1\CatecheseConfigurationResource;
use App\Http\Resources\Api\V1\PaiementResource;
use App\Models\AnneeCatechese;
use App\Models\CaisseParoissiale;
use App\Models\CatecheseConfiguration;
use App\Models\Catechumene;
use App\Models\InscriptionAnnuelle;
use App\Models\LignePaiement;
use App\Models\OperationPaiement;
use App\Models\Paiement;
use App\Models\Tarif;
use App\Services\ParoisseHeaderService;
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
        $user = $request->user();
        $paroisseId = $user->paroisse_configuration_id;
        $validated = $request->validated();

        // Seul un utilisateur ADMIN a le droit de spécifier une remise
        $profilCode = strtoupper(trim($user->profil?->code ?? $user->user_type ?? ''));
        $isAdmin = in_array($profilCode, ['ADMIN', 'SUPER_ADMIN'], true) || str_contains($profilCode, 'ADMIN');

        $anneeVal = $validated['annee_catechese_id'];
        $annee = is_numeric($anneeVal)
            ? AnneeCatechese::find($anneeVal)
            : AnneeCatechese::where('uuid', $anneeVal)->first();

        if (!$annee) {
            return response()->json(['status' => 'error', 'message' => 'Année de catéchèse introuvable.'], 404);
        }

        $inscription = null;
        $catechumene = null;

        if (!empty($validated['inscription_annuelle_id']) && $validated['inscription_annuelle_id'] !== 'null') {
            $insVal = $validated['inscription_annuelle_id'];
            $inscription = is_numeric($insVal) ? InscriptionAnnuelle::find($insVal) : InscriptionAnnuelle::where('uuid', $insVal)->first();
            $catechumene = $inscription?->catechumene;
        }

        if (!$catechumene && !empty($validated['catechumene_id']) && $validated['catechumene_id'] !== 'null') {
            $catVal = $validated['catechumene_id'];
            $catechumene = is_numeric($catVal) ? Catechumene::find($catVal) : Catechumene::where('uuid', $catVal)->first();
        }

        $paiement = DB::transaction(function () use ($paroisseId, $annee, $inscription, $catechumene, $validated, $isAdmin) {
            // 1. Calculer le montant total
            $montantTotal = 0;
            foreach ($validated['lignes'] as $ligne) {
                $qte = $ligne['quantite'] ?? 1;
                $montantTotal += $ligne['montant'] * $qte;
            }

            // Remise accordée (uniquement si ADMIN, sinon 0)
            $remise = 0.0;
            if ($isAdmin && isset($validated['remise'])) {
                $remise = (float) $validated['remise'];
                $remise = max(0, min($remise, $montantTotal));
            }
            $montantNet = max(0, $montantTotal - $remise);

            // 2. Générer le numéro de reçu officiel unique (ex: REC26-124002-0001)
            $numeroRecu = app(\App\Services\ReceiptNumberGeneratorService::class)->generate($paroisseId, $validated['date_paiement']);

            // 3. Créer le paiement
            $paiementObj = Paiement::create([
                'paroisse_configuration_id' => $paroisseId,
                'annee_catechese_id' => $annee->id,
                'inscription_annuelle_id' => $inscription?->id,
                'catechumene_id' => $catechumene?->id,
                'numero_recu' => $numeroRecu,
                'montant_total' => $montantTotal,
                'remise' => $remise,
                'mode_paiement' => $validated['mode_paiement'],
                'reference_transaction' => $validated['reference_transaction'] ?? null,
                'date_paiement' => $validated['date_paiement'],
                'statut' => 'valide',
                'notes' => $validated['notes'] ?? null,
            ]);

            // 4. Créer les lignes de détail du paiement
            foreach ($validated['lignes'] as $l) {
                $tarifId = null;
                if (!empty($l['tarif_id']) && $l['tarif_id'] !== 'null' && $l['tarif_id'] !== 'undefined') {
                    $tVal = $l['tarif_id'];
                    $tarifId = is_numeric($tVal) ? (int) $tVal : Tarif::where('uuid', $tVal)->value('id');
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

            // Mettre à jour l'opération de paiement associée
            $updatedOperations = false;
            foreach ($validated['lignes'] as $l) {
                if (!empty($l['operation_paiement_id'])) {
                    $opVal = $l['operation_paiement_id'];
                    $opObj = is_numeric($opVal) ? OperationPaiement::find($opVal) : OperationPaiement::where('uuid', $opVal)->first();
                    if ($opObj) {
                        $opObj->update([
                            'statut' => 'paye',
                            'montant_paye' => $montantNet,
                            'remise' => $remise,
                        ]);
                        $updatedOperations = true;
                    }
                }
            }

            if (!$updatedOperations && !empty($validated['operation_paiement_id'])) {
                $topOpVal = $validated['operation_paiement_id'];
                $topOp = is_numeric($topOpVal) ? OperationPaiement::find($topOpVal) : OperationPaiement::where('uuid', $topOpVal)->first();
                if ($topOp) {
                    $topOp->update([
                        'statut' => 'paye',
                        'montant_paye' => $montantNet,
                        'remise' => $remise,
                    ]);
                    $updatedOperations = true;
                }
            }

            if (!$updatedOperations && $catechumene) {
                OperationPaiement::where('paroisse_configuration_id', $paroisseId)
                    ->where('catechumene_id', $catechumene->id)
                    ->where('annee_catechese_id', $annee->id)
                    ->where('statut', 'en_attente')
                    ->update([
                        'statut' => 'paye',
                        'montant_paye' => $montantNet,
                        'remise' => $remise,
                    ]);
            }

            // 6. Écriture automatique dans la caisse paroissiale du montant réellement encaissé (montantNet)
            CaisseParoissiale::create([
                'paroisse_configuration_id' => $paroisseId,
                'annee_catechese_id' => $annee->id,
                'type_mouvement' => 'entree',
                'categorie' => 'inscription',
                'montant' => $montantNet,
                'reference_document' => $numeroRecu,
                'libelle' => "Encaissement Reçu N° {$numeroRecu}" . ($remise > 0 ? " (Remise: {$remise} F)" : "") . " - " . ($catechumene ? $catechumene->nom . ' ' . $catechumene->prenoms : 'Catéchumène'),
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
            'data'   => new PaiementResource($paiement),
        ]);
    }

    /**
     * Fournit les données complètes pour l'impression du reçu de paiement par Angular.
     */
    public function recu(Request $request, mixed $paiement): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id ?? CatecheseConfiguration::value('id');

        $item = is_numeric($paiement)
            ? Paiement::find((int) $paiement)
            : Paiement::where('uuid', $paiement)->orWhere('numero_recu', $paiement)->first();

        if (!$item) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Paiement / Reçu introuvable.',
            ], 404);
        }

        $this->authorizeTenant($paroisseId, $item->paroisse_configuration_id);

        $paroisse = CatecheseConfiguration::find($item->paroisse_configuration_id)
            ?? CatecheseConfiguration::find($paroisseId)
            ?? CatecheseConfiguration::firstOrFail();

        $item->loadMissing([
            'catechumene',
            'anneeCatechese',
            'inscriptionAnnuelle.niveau.section',
            'inscriptionAnnuelle.classe',
            'lignes.tarif',
            'user'
        ]);

        $headerService = app(ParoisseHeaderService::class);
        $entete = $headerService->getHeaderData($paroisse, $item->anneeCatechese);
        $format = $request->query('format', 'thermique');

        $cat = $item->catechumene;
        $ins = $item->inscriptionAnnuelle;

        $lignesFormatees = $item->lignes->map(function ($l) {
            return [
                'id'                => $l->uuid ?? (string)$l->id,
                'designation'       => $l->designation,
                'quantite'          => (int) $l->quantite,
                'montant_unitaire'  => (float) $l->montant_unitaire,
                'montant_total'     => (float) $l->montant_total,
                'tarif_id'          => $l->tarif?->uuid,
            ];
        });

        $remise = (float) ($item->remise ?? 0);
        $montantTotal = (float) $item->montant_total;
        $montantNet = max(0, $montantTotal - $remise);

        return response()->json([
            'status'   => 'success',
            'entete'   => $entete,
            'paroisse' => new CatecheseConfigurationResource($paroisse),
            'paiement' => new PaiementResource($item),
            'recu'     => [
                'numero_recu'           => $item->numero_recu,
                'reference_transaction' => $item->reference_transaction,
                'date_paiement'         => $item->date_paiement?->toDateString(),
                'date_paiement_fr'      => $item->date_paiement?->format('d/m/Y'),
                'heure_paiement'        => $item->created_at?->format('H:i'),
                'mode_paiement'         => $item->mode_paiement,
                'mode_paiement_libelle' => ucfirst(str_replace('_', ' ', $item->mode_paiement ?? '')),
                'montant_total'         => $montantTotal,
                'remise'                => $remise,
                'montant_net'           => $montantNet,
                'devise'                => 'FCFA',
                'format_recommande'     => $format,
                'statut'                => $item->statut,
                'notes'                 => $item->notes,
                'beneficiaire'          => [
                    'nom'             => $cat?->nom,
                    'prenom'          => $cat?->prenoms ?? $cat?->prenom,
                    'nom_complet'     => $cat?->nom_complet ?? trim(($cat?->nom ?? '') . ' ' . ($cat?->prenoms ?? '')),
                    'matricule'       => $cat?->matricule,
                    'section'         => $ins?->niveau?->section?->nom,
                    'niveau'          => $ins?->niveau?->nom,
                    'classe'          => $ins?->classe?->nom,
                    'annee_pastorale' => $item->anneeCatechese?->libelle,
                ],
                'lignes'                => $lignesFormatees,
                'caissier'              => [
                    'nom' => $item->user?->name ?? 'Secrétariat Paroissial',
                ],
            ],
        ]);
    }

    /**
     * Alias de compatibilité retournant le JSON du reçu.
     */
    public function pdf(Request $request, mixed $paiement): JsonResponse
    {
        return $this->recu($request, $paiement);
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
