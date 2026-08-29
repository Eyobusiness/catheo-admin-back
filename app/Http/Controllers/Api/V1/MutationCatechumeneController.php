<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MutationCatechumeneResource;
use App\Models\AnneeCatechese;
use App\Models\CatecheseConfiguration;
use App\Models\Catechumene;
use App\Models\MutationCatechumene;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MutationCatechumeneController extends Controller
{
    /**
     * Liste des mutations de catéchumènes avec pagination et filtres.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $query = MutationCatechumene::with(['catechumene', 'anneeCatechese']);

        if ($paroisseId) {
            $query->where('paroisse_configuration_id', $paroisseId);
        }

        // Filtre par statut (demande, approuve, refuse)
        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        // Filtre par année catéchèse
        if ($request->filled('annee_catechese_id')) {
            $anneeId = $request->annee_catechese_id;
            $annee = is_numeric($anneeId)
                ? AnneeCatechese::find($anneeId)
                : AnneeCatechese::where('uuid', $anneeId)->first();

            if ($annee) {
                $query->where('annee_catechese_id', $annee->id);
            }
        }

        // Recherche par mot-clé (nom, prénoms, matricule, paroisses)
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('paroisse_origine_nom', 'like', "%{$search}%")
                  ->orWhere('paroisse_destination_nom', 'like', "%{$search}%")
                  ->orWhere('motif', 'like', "%{$search}%")
                  ->orWhereHas('catechumene', function ($sq) use ($search) {
                      $sq->where('nom', 'like', "%{$search}%")
                        ->orWhere('prenoms', 'like', "%{$search}%")
                        ->orWhere('matricule', 'like', "%{$search}%");
                  });
            });
        }

        // Retour complet si demandé
        if ($request->boolean('all') || $request->get('per_page') === 'all') {
            $mutations = $query->latest('date_mutation')->get();
            return response()->json([
                'status' => 'success',
                'data'   => MutationCatechumeneResource::collection($mutations),
            ]);
        }

        $perPage = (int) $request->get('per_page', 15);
        $mutations = $query->latest('date_mutation')->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data'   => MutationCatechumeneResource::collection($mutations->items()),
            'meta'   => [
                'current_page' => $mutations->currentPage(),
                'last_page'    => $mutations->lastPage(),
                'per_page'     => $mutations->perPage(),
                'total'        => $mutations->total(),
            ],
        ]);
    }

    /**
     * Enregistrer une nouvelle demande de mutation.
     */
    public function store(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $validated = $request->validate([
            'catechumene_id'           => ['required', 'string'],
            'annee_catechese_id'       => ['nullable', 'string'],
            'paroisse_origine_nom'     => ['nullable', 'string', 'max:255'],
            'paroisse_destination_nom' => ['required', 'string', 'max:255'],
            'motif'                    => ['nullable', 'string'],
            'date_mutation'            => ['nullable', 'date'],
            'statut'                   => ['nullable', 'string', 'in:demande,approuve,refuse'],
        ], [
            'catechumene_id.required'           => 'Le catéchumène est obligatoire.',
            'paroisse_destination_nom.required' => 'La paroisse de destination est obligatoire.',
        ]);

        // Résolution du catéchumène
        $catechumene = is_numeric($validated['catechumene_id'])
            ? Catechumene::find($validated['catechumene_id'])
            : (Catechumene::where('uuid', $validated['catechumene_id'])->first()
               ?? Catechumene::where('matricule', $validated['catechumene_id'])->first());

        if (!$catechumene) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Catéchumène introuvable.',
            ], 404);
        }

        // Résolution de l'année
        $annee = null;
        if (!empty($validated['annee_catechese_id'])) {
            $annee = is_numeric($validated['annee_catechese_id'])
                ? AnneeCatechese::find($validated['annee_catechese_id'])
                : AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->first();
        }
        if (!$annee) {
            $annee = AnneeCatechese::resolveAnnee($request, $paroisseId);
        }

        // Nom de paroisse d'origine par défaut
        $paroisseNom = CatecheseConfiguration::find($paroisseId)?->nom_paroisse ?? 'Paroisse Actuelle';
        $origine = !empty($validated['paroisse_origine_nom']) ? $validated['paroisse_origine_nom'] : $paroisseNom;

        $statut = $validated['statut'] ?? 'demande';

        $mutation = MutationCatechumene::create([
            'paroisse_configuration_id' => $paroisseId,
            'catechumene_id'            => $catechumene->id,
            'annee_catechese_id'        => $annee?->id,
            'paroisse_origine_nom'      => $origine,
            'paroisse_destination_nom'  => $validated['paroisse_destination_nom'],
            'motif'                     => $validated['motif'] ?? null,
            'date_mutation'             => $validated['date_mutation'] ?? now()->toDateString(),
            'statut'                    => $statut,
        ]);

        if ($statut === 'approuve') {
            $catechumene->update(['statut' => 'transfere']);
        }

        $mutation->load(['catechumene', 'anneeCatechese']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Demande de mutation enregistrée avec succès.',
            'data'    => new MutationCatechumeneResource($mutation),
        ], 201);
    }

    /**
     * Détails d'une mutation.
     */
    public function show(Request $request, mixed $id): JsonResponse
    {
        $mutation = $this->resolveMutation($id);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $mutation->paroisse_configuration_id);

        $mutation->load(['catechumene', 'anneeCatechese']);

        return response()->json([
            'status' => 'success',
            'data'   => new MutationCatechumeneResource($mutation),
        ]);
    }

    /**
     * Mettre à jour une mutation.
     */
    public function update(Request $request, mixed $id): JsonResponse
    {
        $mutation = $this->resolveMutation($id);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $mutation->paroisse_configuration_id);

        $validated = $request->validate([
            'paroisse_origine_nom'     => ['nullable', 'string', 'max:255'],
            'paroisse_destination_nom' => ['nullable', 'string', 'max:255'],
            'motif'                    => ['nullable', 'string'],
            'date_mutation'            => ['nullable', 'date'],
            'statut'                   => ['nullable', 'string', 'in:demande,approuve,refuse'],
        ]);

        $ancienStatut = $mutation->statut;
        $mutation->update($validated);

        // Mise à jour du statut du catéchumène si approbation / annulation
        if (isset($validated['statut'])) {
            if ($validated['statut'] === 'approuve') {
                $mutation->catechumene?->update(['statut' => 'transfere']);
            } elseif ($ancienStatut === 'approuve' && $validated['statut'] !== 'approuve') {
                $mutation->catechumene?->update(['statut' => 'actif']);
            }
        }

        $mutation->load(['catechumene', 'anneeCatechese']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Mutation mise à jour avec succès.',
            'data'    => new MutationCatechumeneResource($mutation),
        ]);
    }

    /**
     * Mettre à jour le statut d'une mutation (Approuver / Refuser / En attente).
     */
    public function updateStatus(Request $request, mixed $id): JsonResponse
    {
        $mutation = $this->resolveMutation($id);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $mutation->paroisse_configuration_id);

        $validated = $request->validate([
            'statut' => ['nullable', 'string', 'in:demande,approuve,refuse'],
            'status' => ['nullable', 'string', 'in:demande,approuve,refuse'],
        ]);

        $nouveauStatut = $validated['statut'] ?? $validated['status'];
        if (!$nouveauStatut) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Le champ statut est obligatoire.',
            ], 422);
        }

        $ancienStatut = $mutation->statut;
        $mutation->update(['statut' => $nouveauStatut]);

        if ($nouveauStatut === 'approuve') {
            $mutation->catechumene?->update(['statut' => 'transfere']);
        } elseif ($ancienStatut === 'approuve' && $nouveauStatut !== 'approuve') {
            $mutation->catechumene?->update(['statut' => 'actif']);
        }

        $mutation->load(['catechumene', 'anneeCatechese']);

        return response()->json([
            'status'  => 'success',
            'message' => "Statut de la mutation mis à jour : {$nouveauStatut}.",
            'data'    => new MutationCatechumeneResource($mutation),
        ]);
    }

    /**
     * Supprimer une mutation.
     */
    public function destroy(Request $request, mixed $id): JsonResponse
    {
        $mutation = $this->resolveMutation($id);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $mutation->paroisse_configuration_id);

        if ($mutation->statut === 'approuve') {
            $mutation->catechumene?->update(['statut' => 'actif']);
        }

        $mutation->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Mutation supprimée avec succès.',
        ]);
    }

    /**
     * Résolution flexible par UUID ou ID.
     */
    private function resolveMutation(mixed $id): MutationCatechumene
    {
        if ($id instanceof MutationCatechumene) {
            return $id;
        }

        $mutation = is_numeric($id)
            ? MutationCatechumene::find($id)
            : MutationCatechumene::where('uuid', $id)->first();

        if (!$mutation) {
            abort(response()->json([
                'status'  => 'error',
                'message' => 'Mutation introuvable.',
            ], 404));
        }

        return $mutation;
    }

    /**
     * Sécurité multi-tenant.
     */
    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
