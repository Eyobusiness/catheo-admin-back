<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\TarifResource;
use App\Models\AnneeCatechese;
use App\Models\Niveau;
use App\Models\Tarif;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TarifController extends Controller
{
    /**
     * Résout un Tarif par instance, UUID ou ID.
     */
    private function resolveTarif(mixed $tarif): Tarif
    {
        if ($tarif instanceof Tarif) {
            return $tarif;
        }

        $item = is_numeric($tarif)
            ? Tarif::find((int) $tarif)
            : Tarif::where('uuid', $tarif)->first();

        if (!$item) {
            abort(response()->json([
                'status'  => 'error',
                'message' => 'Tarif introuvable.',
            ], 404));
        }

        return $item;
    }

    /**
     * Liste de la grille tarifaire avec filtres et recherche.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $query = Tarif::with(['anneeCatechese', 'niveau.section', 'niveaux']);

        if ($paroisseId) {
            $query->where('paroisse_configuration_id', $paroisseId);
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

        if ($request->filled('niveau_id')) {
            $val = $request->niveau_id;
            $niveauId = is_numeric($val) ? (int) $val : Niveau::where('uuid', $val)->value('id');
            if ($niveauId) {
                $query->where(function ($q) use ($niveauId) {
                    $q->where('niveau_id', $niveauId)
                      ->orWhereHas('niveaux', function ($nq) use ($niveauId) {
                          $nq->where('niveaux.id', $niveauId);
                      });
                });
            }
        }

        if ($request->filled('type_tarif') && strtolower($request->type_tarif) !== 'tous') {
            $query->where('type_tarif', $request->type_tarif);
        }

        if ($request->filled('statut') && strtolower($request->statut) !== 'tous') {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('intitule', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('all') || $request->get('per_page') === 'all') {
            $tarifs = $query->latest()->get();
            return response()->json([
                'status' => 'success',
                'data'   => TarifResource::collection($tarifs),
            ]);
        }

        $perPage = (int) $request->get('per_page', 25);
        $tarifs = $query->latest()->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data'   => TarifResource::collection($tarifs->items()),
            'meta'   => [
                'current_page' => $tarifs->currentPage(),
                'last_page'    => $tarifs->lastPage(),
                'per_page'     => $tarifs->perPage(),
                'total'        => $tarifs->total(),
            ],
        ]);
    }

    /**
     * Créer un élément tarifaire.
     */
    public function store(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $validated = $request->validate([
            'annee_catechese_id' => ['nullable', 'string'],
            'niveau_id'          => ['nullable', 'string'],
            'niveau_ids'         => ['nullable', 'array'],
            'niveau_ids.*'       => ['string'],
            'intitule'           => ['required', 'string', 'max:255'],
            'description'        => ['nullable', 'string'],
            'montant'            => ['required', 'numeric', 'min:0'],
            'periode_debut'      => ['nullable', 'date'],
            'periode_fin'        => ['nullable', 'date'],
            'est_obligatoire'    => ['nullable', 'boolean'],
            'type_tarif'         => ['required', 'string'],
            'statut'             => ['nullable', 'string', 'in:actif,inactif,active,inactive'],
        ]);

        // Année pastorale
        if (!empty($validated['annee_catechese_id'])) {
            $aVal = $validated['annee_catechese_id'];
            $annee = is_numeric($aVal) ? AnneeCatechese::find($aVal) : AnneeCatechese::where('uuid', $aVal)->first();
            $anneeId = $annee?->id;
        } else {
            $annee = AnneeCatechese::where('paroisse_configuration_id', $paroisseId)->where('est_active', true)->first()
                ?? AnneeCatechese::latest()->first();
            $anneeId = $annee?->id;
        }

        $validated['paroisse_configuration_id'] = $paroisseId;
        $validated['annee_catechese_id'] = $anneeId;

        // Niveau individuel
        if (!empty($validated['niveau_id'])) {
            $nVal = $validated['niveau_id'];
            $niveau = is_numeric($nVal) ? Niveau::find($nVal) : Niveau::where('uuid', $nVal)->first();
            $validated['niveau_id'] = $niveau?->id;
        } else {
            $validated['niveau_id'] = null;
        }

        $niveauUuids = $validated['niveau_ids'] ?? [];
        unset($validated['niveau_ids']);

        if (isset($validated['statut'])) {
            $validated['statut'] = in_array(strtolower($validated['statut']), ['actif', 'active']) ? 'actif' : 'inactif';
        } else {
            $validated['statut'] = 'actif';
        }

        $tarif = Tarif::create($validated);

        if (!empty($niveauUuids)) {
            $niveauIds = Niveau::where(function ($q) use ($niveauUuids) {
                $q->whereIn('uuid', $niveauUuids)->orWhereIn('id', $niveauUuids);
            })->pluck('id')->toArray();

            $tarif->niveaux()->sync($niveauIds);
            if (empty($validated['niveau_id']) && count($niveauIds) === 1) {
                $tarif->update(['niveau_id' => $niveauIds[0]]);
            }
        }

        $tarif->load(['anneeCatechese', 'niveau.section', 'niveaux']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Tarif créé avec succès.',
            'data'    => new TarifResource($tarif),
        ], 201);
    }

    /**
     * Détails d'un tarif.
     */
    public function show(Request $request, mixed $tarif): JsonResponse
    {
        $item = $this->resolveTarif($tarif);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $item->paroisse_configuration_id);

        $item->load(['anneeCatechese', 'niveau.section', 'niveaux']);

        return response()->json([
            'status' => 'success',
            'data'   => new TarifResource($item),
        ]);
    }

    /**
     * Mettre à jour un tarif.
     */
    public function update(Request $request, mixed $tarif): JsonResponse
    {
        $item = $this->resolveTarif($tarif);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $item->paroisse_configuration_id);

        $validated = $request->validate([
            'intitule'           => ['sometimes', 'required', 'string', 'max:255'],
            'description'        => ['nullable', 'string'],
            'montant'            => ['sometimes', 'required', 'numeric', 'min:0'],
            'periode_debut'      => ['nullable', 'date'],
            'periode_fin'        => ['nullable', 'date'],
            'est_obligatoire'    => ['nullable', 'boolean'],
            'type_tarif'         => ['sometimes', 'required', 'string'],
            'statut'             => ['nullable', 'string', 'in:actif,inactif,active,inactive'],
            'annee_catechese_id' => ['nullable', 'string'],
            'niveau_id'          => ['nullable', 'string'],
            'niveau_ids'         => ['nullable', 'array'],
            'niveau_ids.*'       => ['string'],
        ]);

        if (array_key_exists('annee_catechese_id', $validated)) {
            $aVal = $validated['annee_catechese_id'];
            if (!empty($aVal)) {
                $annee = is_numeric($aVal) ? AnneeCatechese::find($aVal) : AnneeCatechese::where('uuid', $aVal)->first();
                $validated['annee_catechese_id'] = $annee?->id;
            }
        }

        if (array_key_exists('niveau_id', $validated)) {
            $nVal = $validated['niveau_id'];
            if (!empty($nVal)) {
                $niveau = is_numeric($nVal) ? Niveau::find($nVal) : Niveau::where('uuid', $nVal)->first();
                $validated['niveau_id'] = $niveau?->id;
            } else {
                $validated['niveau_id'] = null;
            }
        }

        if (isset($validated['statut'])) {
            $validated['statut'] = in_array(strtolower($validated['statut']), ['actif', 'active']) ? 'actif' : 'inactif';
        }

        $niveauUuids = $validated['niveau_ids'] ?? null;
        unset($validated['niveau_ids']);

        $item->update($validated);

        if ($niveauUuids !== null) {
            $niveauIds = Niveau::where(function ($q) use ($niveauUuids) {
                $q->whereIn('uuid', $niveauUuids)->orWhereIn('id', $niveauUuids);
            })->pluck('id')->toArray();

            $item->niveaux()->sync($niveauIds);
        }

        $item->load(['anneeCatechese', 'niveau.section', 'niveaux']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Tarif mis à jour avec succès.',
            'data'    => new TarifResource($item),
        ]);
    }

    /**
     * Basculer le statut d'un tarif (actif <-> inactif).
     */
    public function toggleStatus(Request $request, mixed $tarif): JsonResponse
    {
        $item = $this->resolveTarif($tarif);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $item->paroisse_configuration_id);

        $newStatus = ($item->statut === 'actif') ? 'inactif' : 'actif';
        $item->update(['statut' => $newStatus]);
        $item->load(['anneeCatechese', 'niveau.section', 'niveaux']);

        return response()->json([
            'status'  => 'success',
            'message' => "Le tarif '{$item->intitule}' est désormais {$newStatus}.",
            'data'    => new TarifResource($item),
        ]);
    }

    /**
     * Supprimer un tarif.
     */
    public function destroy(Request $request, mixed $tarif): JsonResponse
    {
        $item = $this->resolveTarif($tarif);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $item->paroisse_configuration_id);

        $item->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Tarif supprimé avec succès.',
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, ?int $targetParoisseId): void
    {
        if ($userParoisseId && $targetParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}