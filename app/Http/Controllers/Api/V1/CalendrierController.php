<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CalendrierResource;
use App\Models\AnneeCatechese;
use App\Models\Calendrier;
use App\Models\CatecheseConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendrierController extends Controller
{
    /**
     * Liste des activités du calendrier pastoral avec filtres.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user() ?? auth('sanctum')->user();
        $paroisseId = $user?->paroisse_configuration_id 
            ?? $request->input('paroisse_configuration_id')
            ?? $request->header('X-Paroisse-Id');

        if (!$paroisseId) {
            return response()->json([
                'status' => 'success',
                'meta'   => ['total_elements' => 0],
                'data'   => [],
            ]);
        }

        $query = Calendrier::with('anneeCatechese')
            ->where('paroisse_configuration_id', (int) $paroisseId);

        if ($request->filled('annee_catechese_id')) {
            $anneeId = AnneeCatechese::where('uuid', $request->annee_catechese_id)
                ->orWhere('id', $request->annee_catechese_id)
                ->value('id');
            if ($anneeId) {
                $query->where('annee_catechese_id', $anneeId);
            }
        }

        if ($request->filled('cible_type') && !in_array(strtolower($request->cible_type), ['', 'tous_publics', 'all'])) {
            $targetCible = $request->cible_type;
            $query->where(function ($q) use ($targetCible) {
                $q->where('cible_type', $targetCible)
                  ->orWhereRaw('LOWER(cible_type) = ?', [strtolower($targetCible)]);
            });
        }

        if ($request->filled('statut') && strtolower($request->statut) !== 'tous') {
            $statut = $this->normalizeStatut($request->statut);
            $query->where('statut', $statut);
        }

        if ($request->filled('type')) {
            $query->where('type', 'like', "%{$request->type}%");
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('titre', 'like', "%{$search}%")
                  ->orWhere('type', 'like', "%{$search}%")
                  ->orWhere('lieu', 'like', "%{$search}%")
                  ->orWhere('cible_nom', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $startDate = $request->input('start_date') ?? $request->input('date_debut');
        if ($startDate) {
            $query->whereDate('date', '>=', $startDate);
        }

        $endDate = $request->input('end_date') ?? $request->input('date_fin');
        if ($endDate) {
            $query->whereDate('date', '<=', $endDate);
        }

        $activites = $query->orderBy('date', 'asc')->orderBy('heure_debut', 'asc')->get();

        return response()->json([
            'status' => 'success',
            'meta'   => [
                'total_elements' => $activites->count(),
            ],
            'data'   => CalendrierResource::collection($activites),
        ]);
    }

    /**
     * Création d'un événement au calendrier pastoral.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user() ?? auth('sanctum')->user();
        $paroisseId = $user?->paroisse_configuration_id 
            ?? $request->input('paroisse_configuration_id')
            ?? $request->header('X-Paroisse-Id');

        if (!$paroisseId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'L\'identifiant de la paroisse est obligatoire.',
            ], 422);
        }

        $data = $request->all();

        // Normalisation année
        $anneeInput = $data['annee_catechese_id'] ?? $data['annee_id'] ?? ($data['annee_catechese']['id'] ?? null);
        if ($anneeInput) {
            $data['annee_catechese_id'] = $anneeInput;
        }

        $request->merge($data);

        $validated = $request->validate([
            'annee_catechese_id' => ['nullable', 'string'],
            'titre'              => ['required', 'string', 'min:3', 'max:255'],
            'type'               => ['required', 'string', 'max:100'],
            'date'               => ['required', 'date'],
            'heure_debut'        => ['nullable', 'string'],
            'heure_fin'          => ['nullable', 'string'],
            'lieu'               => ['nullable', 'string', 'max:255'],
            'cible_type'         => ['required', 'string', 'max:50'],
            'cible_id'           => ['nullable', 'string'],
            'cible_ids'          => ['nullable', 'array'],
            'cible_nom'          => ['nullable', 'string', 'max:255'],
            'description'        => ['nullable', 'string'],
            'statut'             => ['nullable', 'string'],
        ]);

        if (!empty($validated['annee_catechese_id'])) {
            $annee = AnneeCatechese::where('uuid', $validated['annee_catechese_id'])
                ->orWhere('id', $validated['annee_catechese_id'])
                ->first();
            $anneeId = $annee?->id;
        } else {
            $annee = AnneeCatechese::getAnneeCourante($paroisseId);
            $anneeId = $annee?->id;
        }

        $cibleIds = $validated['cible_ids'] ?? [];
        $cibleId = $validated['cible_id'] ?? null;

        if (empty($cibleId) && !empty($cibleIds)) {
            $cibleId = implode(',', $cibleIds);
        } elseif (!empty($cibleId) && empty($cibleIds)) {
            $cibleIds = array_map('trim', explode(',', $cibleId));
        }

        $calendrier = Calendrier::create([
            'paroisse_configuration_id' => $paroisseId,
            'annee_catechese_id'        => $anneeId,
            'titre'                     => $validated['titre'],
            'type'                      => $validated['type'],
            'date'                      => $validated['date'],
            'heure_debut'               => $validated['heure_debut'] ?? null,
            'heure_fin'                 => $validated['heure_fin'] ?? null,
            'lieu'                      => $validated['lieu'] ?? null,
            'cible_type'                => $validated['cible_type'],
            'cible_id'                  => $cibleId,
            'cible_ids'                 => $cibleIds,
            'cible_nom'                 => $validated['cible_nom'] ?? null,
            'description'               => $validated['description'] ?? null,
            'statut'                    => $this->normalizeStatut($validated['statut'] ?? 'Planifié'),
        ]);

        $calendrier->load('anneeCatechese');

        return response()->json([
            'status'  => 'success',
            'message' => 'Événement enregistré avec succès.',
            'data'    => new CalendrierResource($calendrier),
        ], 201);
    }

    /**
     * Détails d'un événement au calendrier.
     */
    public function show(Request $request, mixed $calendrier): JsonResponse
    {
        $model = $this->resolveCalendrier($calendrier);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $model->paroisse_configuration_id);

        $model->load('anneeCatechese');

        return response()->json([
            'status' => 'success',
            'data'   => new CalendrierResource($model),
        ]);
    }

    /**
     * Mise à jour d'un événement au calendrier.
     */
    public function update(Request $request, mixed $calendrier): JsonResponse
    {
        $model = $this->resolveCalendrier($calendrier);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $model->paroisse_configuration_id);

        $data = $request->all();

        if (isset($data['annee_catechese']['id']) && !isset($data['annee_catechese_id'])) {
            $data['annee_catechese_id'] = $data['annee_catechese']['id'];
        }

        $request->merge($data);

        $validated = $request->validate([
            'annee_catechese_id' => ['sometimes', 'nullable', 'string'],
            'titre'              => ['sometimes', 'required', 'string', 'min:3', 'max:255'],
            'type'               => ['sometimes', 'required', 'string', 'max:100'],
            'date'               => ['sometimes', 'required', 'date'],
            'heure_debut'        => ['nullable', 'string'],
            'heure_fin'          => ['nullable', 'string'],
            'lieu'               => ['nullable', 'string', 'max:255'],
            'cible_type'         => ['nullable', 'string', 'max:50'],
            'cible_id'           => ['nullable', 'string'],
            'cible_ids'          => ['nullable', 'array'],
            'cible_nom'          => ['nullable', 'string', 'max:255'],
            'description'        => ['nullable', 'string'],
            'statut'             => ['nullable', 'string'],
        ]);

        $updateData = [];

        if (isset($validated['titre'])) {
            $updateData['titre'] = $validated['titre'];
        }
        if (isset($validated['type'])) {
            $updateData['type'] = $validated['type'];
        }
        if (isset($validated['date'])) {
            $updateData['date'] = $validated['date'];
        }
        if (array_key_exists('heure_debut', $validated)) {
            $updateData['heure_debut'] = $validated['heure_debut'];
        }
        if (array_key_exists('heure_fin', $validated)) {
            $updateData['heure_fin'] = $validated['heure_fin'];
        }
        if (array_key_exists('lieu', $validated)) {
            $updateData['lieu'] = $validated['lieu'];
        }
        if (array_key_exists('description', $validated)) {
            $updateData['description'] = $validated['description'];
        }
        if (isset($validated['statut'])) {
            $updateData['statut'] = $this->normalizeStatut($validated['statut']);
        }
        if (isset($validated['cible_type'])) {
            $updateData['cible_type'] = $validated['cible_type'];
        }
        if (array_key_exists('cible_ids', $validated)) {
            $updateData['cible_ids'] = $validated['cible_ids'];
            if (!empty($validated['cible_ids'])) {
                $updateData['cible_id'] = implode(',', $validated['cible_ids']);
            }
        }
        if (array_key_exists('cible_id', $validated)) {
            $updateData['cible_id'] = $validated['cible_id'];
            if (!empty($validated['cible_id']) && !isset($updateData['cible_ids'])) {
                $updateData['cible_ids'] = array_map('trim', explode(',', $validated['cible_id']));
            }
        }
        if (array_key_exists('cible_nom', $validated)) {
            $updateData['cible_nom'] = $validated['cible_nom'];
        }

        if (array_key_exists('annee_catechese_id', $validated)) {
            if (!empty($validated['annee_catechese_id'])) {
                $annee = AnneeCatechese::where('uuid', $validated['annee_catechese_id'])
                    ->orWhere('id', $validated['annee_catechese_id'])
                    ->first();
                $updateData['annee_catechese_id'] = $annee?->id;
            }
        }

        $model->update($updateData);
        $model->refresh();
        $model->load('anneeCatechese');

        return response()->json([
            'status'  => 'success',
            'message' => 'Événement du calendrier mis à jour avec succès.',
            'data'    => new CalendrierResource($model),
        ]);
    }

    /**
     * Modification du statut d'une activité du calendrier.
     */
    public function updateStatus(Request $request, mixed $calendrier): JsonResponse
    {
        $model = $this->resolveCalendrier($calendrier);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $model->paroisse_configuration_id);

        $statusInput = $request->input('statut') ?? $request->input('status');

        $validated = validator(['statut' => $statusInput], [
            'statut' => ['required', 'string'],
        ])->validate();

        $statut = $this->normalizeStatut($validated['statut']);
        $model->update(['statut' => $statut]);
        $model->refresh();
        $model->load('anneeCatechese');

        return response()->json([
            'status'  => 'success',
            'message' => "Le statut de l'activité est désormais {$model->statut}.",
            'data'    => new CalendrierResource($model),
        ]);
    }

    /**
     * Suppression d'une activité du calendrier.
     */
    public function destroy(Request $request, mixed $calendrier): JsonResponse
    {
        $model = $this->resolveCalendrier($calendrier);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $model->paroisse_configuration_id);

        $model->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Activité du calendrier supprimée avec succès.',
        ]);
    }

    /**
     * Résout l'instance du modèle depuis un objet injecté, un UUID ou un ID numérique.
     */
    private function resolveCalendrier(mixed $calendrier): Calendrier
    {
        if ($calendrier instanceof Calendrier && $calendrier->exists) {
            return $calendrier;
        }

        $identifier = is_object($calendrier) ? ($calendrier->uuid ?? $calendrier->id ?? null) : $calendrier;

        return Calendrier::where('uuid', $identifier)
            ->orWhere('id', $identifier)
            ->firstOrFail();
    }

    private function normalizeStatut(string $statut): string
    {
        $cleaned = mb_strtolower(trim($statut), 'UTF-8');
        return match ($cleaned) {
            'realise', 'réalisé', 'effectue', 'effectué' => 'Réalisé',
            'annule', 'annulé'                           => 'Annulé',
            default                                      => 'Planifié',
        };
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}

