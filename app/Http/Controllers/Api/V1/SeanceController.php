<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PresenceResource;
use App\Http\Resources\Api\V1\SeanceResource;
use App\Models\AnneeCatechese;
use App\Models\CatecheseConfiguration;
use App\Models\Catechumene;
use App\Models\Classe;
use App\Models\Presence;
use App\Models\Seance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SeanceController extends Controller
{
    /**
     * Liste des séances de cours avec filtres.
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

        $query = Seance::with(['anneeCatechese', 'classe', 'presences.catechumene'])
            ->where('paroisse_configuration_id', (int) $paroisseId);

        // Si l'utilisateur connecté est un Animateur, restreindre strictement à ses classes affectées
        if ($user instanceof \App\Models\Animateur) {
            $assignedClassIds = DB::table('affectations_animateurs')
                ->where('animateur_id', $user->id)
                ->whereNull('deleted_at')
                ->pluck('classe_id')
                ->toArray();

            $query->whereIn('classe_id', $assignedClassIds);
        }

        if ($request->filled('classe_id')) {
            $classeId = Classe::where('uuid', $request->classe_id)
                ->orWhere('id', $request->classe_id)
                ->value('id');
            if ($classeId) {
                $query->where('classe_id', $classeId);
            }
        }

        if ($request->filled('annee_catechese_id')) {
            $anneeId = AnneeCatechese::where('uuid', $request->annee_catechese_id)
                ->orWhere('id', $request->annee_catechese_id)
                ->value('id');
            if ($anneeId) {
                $query->where('annee_catechese_id', $anneeId);
            }
        }

        if ($request->filled('statut') && strtolower($request->statut) !== 'tous') {
            $query->where('statut', strtolower($request->statut));
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('titre', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $startDate = $request->input('start_date') ?? $request->input('date_debut');
        if ($startDate) {
            $query->whereDate('date_seance', '>=', $startDate);
        }

        $endDate = $request->input('end_date') ?? $request->input('date_fin');
        if ($endDate) {
            $query->whereDate('date_seance', '<=', $endDate);
        }

        $seances = $query->orderBy('date_seance', 'desc')->orderBy('heure_debut', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'meta'   => [
                'total_elements' => $seances->count(),
            ],
            'data'   => SeanceResource::collection($seances),
        ]);
    }

    /**
     * Planifier une séance de cours.
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

        // Normalisations
        if (isset($data['classe']['id']) && !isset($data['classe_id'])) {
            $data['classe_id'] = $data['classe']['id'];
        }
        if (isset($data['annee_catechese']['id']) && !isset($data['annee_catechese_id'])) {
            $data['annee_catechese_id'] = $data['annee_catechese']['id'];
        }
        if (isset($data['titre_lecon']) && !isset($data['titre'])) {
            $data['titre'] = $data['titre_lecon'];
        }

        $request->merge($data);

        $validated = $request->validate([
            'annee_catechese_id' => ['nullable'],
            'classe_id'          => ['required'],
            'titre'              => ['required', 'string', 'max:255'],
            'description'        => ['nullable', 'string'],
            'date_seance'        => ['required', 'date'],
            'heure_debut'        => ['nullable', 'string'],
            'heure_fin'          => ['nullable', 'string'],
            'statut'             => ['nullable', 'string', 'in:planifiee,effectuee,annulee,Planifiée,Effectuée,Annulée'],
        ]);

        if (!empty($validated['annee_catechese_id'])) {
            $annee = AnneeCatechese::where('uuid', $validated['annee_catechese_id'])
                ->orWhere('id', $validated['annee_catechese_id'])
                ->firstOrFail();
            $anneeId = $annee->id;
        } else {
            $annee = AnneeCatechese::getAnneeCourante($paroisseId);
            $anneeId = $annee?->id;
        }

        $classe = Classe::where('uuid', $validated['classe_id'])
            ->orWhere('id', $validated['classe_id'])
            ->firstOrFail();

        $statut = strtolower($validated['statut'] ?? 'planifiee');
        if ($statut === 'planifiée') $statut = 'planifiee';
        if ($statut === 'effectuée') $statut = 'effectuee';
        if ($statut === 'annulée') $statut = 'annulee';

        $this->checkPeriodOrBilanLock($paroisseId, $validated['date_seance'] ?? null, $classe->id ?? null);

        $seance = Seance::create([
            'paroisse_configuration_id' => $paroisseId,
            'annee_catechese_id'        => $anneeId,
            'classe_id'                 => $classe->id,
            'titre'                     => $validated['titre'],
            'description'               => $validated['description'] ?? null,
            'date_seance'               => $validated['date_seance'],
            'heure_debut'               => $validated['heure_debut'] ?? '08:30',
            'heure_fin'                 => $validated['heure_fin'] ?? '10:00',
            'statut'                    => $statut,
        ]);

        $seance->load(['anneeCatechese', 'classe', 'presences.catechumene']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Séance planifiée avec succès.',
            'data'    => new SeanceResource($seance),
        ], 201);
    }

    /**
     * Afficher les détails d'une séance.
     */
    public function show(Request $request, mixed $seance): JsonResponse
    {
        $model = $this->resolveSeance($seance);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $model->paroisse_configuration_id);

        $model->load(['anneeCatechese', 'classe', 'presences.catechumene']);

        return response()->json([
            'status' => 'success',
            'data'   => new SeanceResource($model),
        ]);
    }

    /**
     * Mise à jour d'une séance.
     */
    public function update(Request $request, mixed $seance): JsonResponse
    {
        $model = $this->resolveSeance($seance);
        $paroisseId = $request->user()?->paroisse_configuration_id ?? $model->paroisse_configuration_id;
        $this->authorizeTenant($request->user()?->paroisse_configuration_id, $model->paroisse_configuration_id);

        $data = $request->all();

        if (isset($data['classe']['id']) && !isset($data['classe_id'])) {
            $data['classe_id'] = $data['classe']['id'];
        }
        if (isset($data['annee_catechese']['id']) && !isset($data['annee_catechese_id'])) {
            $data['annee_catechese_id'] = $data['annee_catechese']['id'];
        }
        if (isset($data['titre_lecon']) && !isset($data['titre'])) {
            $data['titre'] = $data['titre_lecon'];
        }

        $request->merge($data);

        $this->checkPeriodOrBilanLock($paroisseId, $model->date_seance?->toDateString(), $model->classe_id);

        $validated = $request->validate([
            'annee_catechese_id' => ['sometimes', 'nullable'],
            'classe_id'          => ['sometimes', 'nullable'],
            'titre'              => ['sometimes', 'required', 'string', 'max:255'],
            'description'        => ['nullable', 'string'],
            'date_seance'        => ['sometimes', 'required', 'date'],
            'heure_debut'        => ['nullable', 'string'],
            'heure_fin'          => ['nullable', 'string'],
            'statut'             => ['nullable', 'string'],
        ]);

        $updateData = [];

        if (isset($validated['titre'])) {
            $updateData['titre'] = $validated['titre'];
        }
        if (isset($validated['date_seance'])) {
            $updateData['date_seance'] = $validated['date_seance'];
        }
        if (array_key_exists('heure_debut', $validated)) {
            $updateData['heure_debut'] = $validated['heure_debut'];
        }
        if (array_key_exists('heure_fin', $validated)) {
            $updateData['heure_fin'] = $validated['heure_fin'];
        }
        if (array_key_exists('description', $validated)) {
            $updateData['description'] = $validated['description'];
        }
        if (isset($validated['statut'])) {
            $st = strtolower($validated['statut']);
            if ($st === 'planifiée') $st = 'planifiee';
            if ($st === 'effectuée') $st = 'effectuee';
            if ($st === 'annulée') $st = 'annulee';
            $updateData['statut'] = $st;
        }

        if (array_key_exists('annee_catechese_id', $validated)) {
            if (!empty($validated['annee_catechese_id'])) {
                $annee = AnneeCatechese::where('uuid', $validated['annee_catechese_id'])
                    ->orWhere('id', $validated['annee_catechese_id'])
                    ->first();
                $updateData['annee_catechese_id'] = $annee?->id;
            }
        }

        if (array_key_exists('classe_id', $validated)) {
            if (!empty($validated['classe_id'])) {
                $classe = Classe::where('uuid', $validated['classe_id'])
                    ->orWhere('id', $validated['classe_id'])
                    ->first();
                $updateData['classe_id'] = $classe?->id;
            }
        }

        $model->update($updateData);
        $model->refresh();
        $model->load(['anneeCatechese', 'classe', 'presences.catechumene']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Séance mise à jour avec succès.',
            'data'    => new SeanceResource($model),
        ]);
    }

    /**
     * Modifier le statut d'une séance.
     */
    public function updateStatus(Request $request, mixed $seance): JsonResponse
    {
        $model = $this->resolveSeance($seance);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $model->paroisse_configuration_id);

        $statusInput = $request->input('statut') ?? $request->input('status') ?? 'planifiee';
        $st = strtolower($statusInput);
        if ($st === 'planifiée') $st = 'planifiee';
        if ($st === 'effectuée') $st = 'effectuee';
        if ($st === 'annulée') $st = 'annulee';

        $model->update(['statut' => $st]);
        $model->refresh();
        $model->load(['anneeCatechese', 'classe', 'presences.catechumene']);

        return response()->json([
            'status'  => 'success',
            'message' => "Le statut de la séance est désormais {$model->statut}.",
            'data'    => new SeanceResource($model),
        ]);
    }

    /**
     * Supprimer une séance et ses présences associées.
     */
    public function destroy(Request $request, mixed $seance): JsonResponse
    {
        $model = $this->resolveSeance($seance);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $model->paroisse_configuration_id);

        DB::transaction(function () use ($model) {
            $model->presences()->delete();
            $model->delete();
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Séance supprimée avec succès.',
        ]);
    }

    /**
     * Liste des présences d'une séance.
     */
    public function getPresences(Request $request, mixed $seance): JsonResponse
    {
        $model = $this->resolveSeance($seance);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $model->paroisse_configuration_id);

        $presences = $model->presences()->with('catechumene')->get();

        return response()->json([
            'status' => 'success',
            'data'   => PresenceResource::collection($presences),
        ]);
    }

    /**
     * Saisie en lot de la feuille de présence d'une séance.
     */
    public function presences(Request $request, mixed $seance): JsonResponse
    {
        $user = $request->user() ?? auth('sanctum')->user();
        $model = $this->resolveSeance($seance);
        $paroisseId = $user?->paroisse_configuration_id ?? $model->paroisse_configuration_id;
        $this->authorizeTenant($user?->paroisse_configuration_id, $model->paroisse_configuration_id);

        $this->checkPeriodOrBilanLock($paroisseId, $model->date_seance?->toDateString(), $model->classe_id);

        $validated = $request->validate([
            'presences'                   => ['required', 'array', 'min:1'],
            'presences.*.catechumene_id'  => ['required', 'string'],
            'presences.*.statut_presence' => ['required', 'string', 'in:present,absent,retard,excuse,Présent,Absent,Retard,Excusé'],
            'presences.*.remarque'        => ['nullable', 'string', 'max:255'],
            'presences.*.motif_absence'   => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($paroisseId, $model, $validated) {
            foreach ($validated['presences'] as $item) {
                $catechumene = Catechumene::where('uuid', $item['catechumene_id'])
                    ->orWhere('id', $item['catechumene_id'])
                    ->firstOrFail();

                $statutPresence = strtolower($item['statut_presence']);
                if ($statutPresence === 'présent') $statutPresence = 'present';
                if ($statutPresence === 'excusé') $statutPresence = 'excuse';

                $remarque = $item['remarque'] ?? $item['motif_absence'] ?? null;

                Presence::updateOrCreate(
                    [
                        'paroisse_configuration_id' => $paroisseId,
                        'seance_id'                 => $model->id,
                        'catechumene_id'            => $catechumene->id,
                    ],
                    [
                        'statut_presence' => $statutPresence,
                        'motif_absence'   => $remarque,
                        'remarque'        => $remarque,
                    ]
                );
            }

            $model->update(['statut' => 'effectuee']);
        });

        $model->refresh();
        $model->load(['anneeCatechese', 'classe', 'presences.catechumene']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Appel de présence enregistré avec succès.',
            'data'    => new SeanceResource($model),
        ]);
    }

    /**
     * Résout l'instance du modèle depuis un objet injecté, un UUID ou un ID numérique.
     */
    private function resolveSeance(mixed $seance): Seance
    {
        if ($seance instanceof Seance && $seance->exists) {
            return $seance;
        }

        $identifier = is_object($seance) ? ($seance->uuid ?? $seance->id ?? null) : $seance;

        return Seance::where('uuid', $identifier)
            ->orWhere('id', $identifier)
            ->firstOrFail();
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }

    /**
     * Vérifie si le trimestre ou le bilan de la classe est clôturé / validé.
     */
    private function checkPeriodOrBilanLock(?int $paroisseId, ?string $date, ?int $classeId): void
    {
        if (!$paroisseId) return;

        // 1. Date comprise dans un trimestre terminé
        if ($date) {
            $dateStr = substr($date, 0, 10);
            $closedModule = \App\Models\ModuleTrimestriel::where('paroisse_configuration_id', $paroisseId)
                ->whereIn('statut', ['termine', 'cloture', 'clos'])
                ->whereDate('date_debut', '<=', $dateStr)
                ->whereDate('date_fin', '>=', $dateStr)
                ->exists();
            if ($closedModule) {
                abort(response()->json([
                    'status'  => 'error',
                    'message' => 'Aucune modification n\'est à effectuer car le bilan est déjà validé.',
                ], 403));
            }
        }

        // 2. Bilan annuel / décisions déjà validées pour la classe
        if ($classeId) {
            $hasBilan = DB::table('decisions_fin_annee')
                ->join('inscriptions_annuelles', 'decisions_fin_annee.inscription_annuelle_id', '=', 'inscriptions_annuelles.id')
                ->where('inscriptions_annuelles.classe_id', $classeId)
                ->whereNull('decisions_fin_annee.deleted_at')
                ->exists();
            if ($hasBilan) {
                abort(response()->json([
                    'status'  => 'error',
                    'message' => 'Aucune modification n\'est à effectuer car le bilan est déjà validé.',
                ], 403));
            }
        }
    }
}
