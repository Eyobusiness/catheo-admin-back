<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CalendrierResource;
use App\Models\AnneeCatechese;
use App\Models\Calendrier;
use App\Models\Ceb;
use App\Models\Classe;
use App\Models\Mouvement;
use App\Models\Niveau;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CalendrierController extends Controller
{
    /**
     * Liste des activités du calendrier pastoral avec filtres.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $query = Calendrier::with('anneeCatechese')
            ->where('paroisse_configuration_id', $paroisseId);

        if ($request->filled('annee_catechese_id')) {
            $anneeId = AnneeCatechese::where('uuid', $request->annee_catechese_id)->value('id');
            if ($anneeId) {
                $query->where('annee_catechese_id', $anneeId);
            }
        } else {
            $activeAnneeId = AnneeCatechese::where('paroisse_configuration_id', $paroisseId)
                ->where('est_active', true)
                ->value('id');
            if ($activeAnneeId) {
                $query->where('annee_catechese_id', $activeAnneeId);
            }
        }

        if ($request->filled('cible_type') && strtoupper($request->cible_type) !== 'TOUS') {
            $query->where('cible_type', strtoupper($request->cible_type));
        }

        if ($request->filled('statut') && strtolower($request->statut) !== 'tous') {
            $query->where('statut', ucfirst(strtolower($request->statut)));
        }

        if ($request->filled('type')) {
            $query->where('type', 'like', "%{$request->type}%");
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('titre', 'like', "%{$search}%")
                  ->orWhere('lieu', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('date_debut')) {
            $query->whereDate('date', '>=', $request->date_debut);
        }

        if ($request->filled('date_fin')) {
            $query->whereDate('date', '<=', $request->date_fin);
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
        $paroisseId = $request->user()->paroisse_configuration_id;

        $validated = $request->validate([
            'annee_catechese_id' => ['nullable', 'string', 'exists:annee_catecheses,uuid'],
            'titre'              => ['required', 'string', 'max:150'],
            'type'               => ['required', 'string', 'max:150'],
            'date'               => ['required', 'date'],
            'heure_debut'        => ['nullable', 'date_format:H:i'],
            'heure_fin'          => ['nullable', 'date_format:H:i'],
            'lieu'               => ['nullable', 'string', 'max:150'],
            'cible_type'         => ['nullable', 'string', 'in:TOUS,ANIMATEURS,SECTION,NIVEAU,CLASSE,CEB,MOUVEMENT,tous,animateurs,section,niveau,classe,ceb,mouvement'],
            'cible_id'           => ['nullable', 'string'],
            'description'        => ['nullable', 'string'],
            'statut'             => ['nullable', 'string', 'in:Planifié,Réalisé,Annulé,planifie,realise,annule,Planifie,Realise,Annule'],
        ]);

        if (!empty($validated['annee_catechese_id'])) {
            $annee = AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->firstOrFail();
            $anneeId = $annee->id;
        } else {
            $anneeId = AnneeCatechese::where('paroisse_configuration_id', $paroisseId)
                ->where('est_active', true)
                ->value('id') ?? AnneeCatechese::where('paroisse_configuration_id', $paroisseId)->latest()->value('id');
        }

        $cibleType = strtoupper($validated['cible_type'] ?? 'TOUS');
        $cibleId = null;

        if (!empty($validated['cible_id']) && !in_array($cibleType, ['TOUS', 'ANIMATEURS'])) {
            $cibleId = $this->resolveCibleInternalId($cibleType, $validated['cible_id'], $paroisseId);
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
            'cible_type'                => $cibleType,
            'cible_id'                  => $cibleId,
            'description'               => $validated['description'] ?? null,
            'statut'                    => ucfirst(strtolower($validated['statut'] ?? 'Planifié')),
        ]);

        $calendrier->load('anneeCatechese');

        return response()->json([
            'status'  => 'success',
            'message' => 'Événement du calendrier créé avec succès.',
            'data'    => new CalendrierResource($calendrier),
        ], 201);
    }

    /**
     * Détails d'un événement au calendrier.
     */
    public function show(Request $request, Calendrier $calendrier): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $calendrier->paroisse_configuration_id);

        $calendrier->load('anneeCatechese');

        return response()->json([
            'status' => 'success',
            'data'   => new CalendrierResource($calendrier),
        ]);
    }

    /**
     * Mise à jour d'un événement au calendrier.
     */
    public function update(Request $request, Calendrier $calendrier): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $calendrier->paroisse_configuration_id);

        $validated = $request->validate([
            'annee_catechese_id' => ['sometimes', 'nullable', 'string', 'exists:annee_catecheses,uuid'],
            'titre'              => ['sometimes', 'required', 'string', 'max:150'],
            'type'               => ['sometimes', 'required', 'string', 'max:150'],
            'date'               => ['sometimes', 'required', 'date'],
            'heure_debut'        => ['nullable', 'date_format:H:i'],
            'heure_fin'          => ['nullable', 'date_format:H:i'],
            'lieu'               => ['nullable', 'string', 'max:150'],
            'cible_type'         => ['nullable', 'string', 'in:TOUS,ANIMATEURS,SECTION,NIVEAU,CLASSE,CEB,MOUVEMENT,tous,animateurs,section,niveau,classe,ceb,mouvement'],
            'cible_id'           => ['nullable', 'string'],
            'description'        => ['nullable', 'string'],
            'statut'             => ['nullable', 'string', 'in:Planifié,Réalisé,Annulé,planifie,realise,annule,Planifie,Realise,Annule'],
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
            $updateData['statut'] = ucfirst(strtolower($validated['statut']));
        }

        if (!empty($validated['annee_catechese_id'])) {
            $annee = AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->firstOrFail();
            $updateData['annee_catechese_id'] = $annee->id;
        }

        if (isset($validated['cible_type'])) {
            $cibleType = strtoupper($validated['cible_type']);
            $updateData['cible_type'] = $cibleType;

            if (in_array($cibleType, ['TOUS', 'ANIMATEURS'])) {
                $updateData['cible_id'] = null;
            } elseif (array_key_exists('cible_id', $validated)) {
                $updateData['cible_id'] = $validated['cible_id'] ? $this->resolveCibleInternalId($cibleType, $validated['cible_id'], $calendrier->paroisse_configuration_id) : null;
            }
        } elseif (array_key_exists('cible_id', $validated)) {
            $updateData['cible_id'] = $validated['cible_id'] ? $this->resolveCibleInternalId($calendrier->cible_type, $validated['cible_id'], $calendrier->paroisse_configuration_id) : null;
        }

        $calendrier->update($updateData);
        $calendrier->load('anneeCatechese');

        return response()->json([
            'status'  => 'success',
            'message' => 'Événement du calendrier mis à jour avec succès.',
            'data'    => new CalendrierResource($calendrier),
        ]);
    }

    /**
     * Modification du statut d'une activité du calendrier.
     */
    public function updateStatus(Request $request, Calendrier $calendrier): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $calendrier->paroisse_configuration_id);

        $validated = $request->validate([
            'statut' => ['required', 'string', 'in:Planifié,Réalisé,Annulé,planifie,realise,annule,Planifie,Realise,Annule'],
        ]);

        $calendrier->update(['statut' => ucfirst(strtolower($validated['statut']))]);
        $calendrier->load('anneeCatechese');

        return response()->json([
            'status'  => 'success',
            'message' => "Le statut de l'activité est désormais {$calendrier->statut}.",
            'data'    => new CalendrierResource($calendrier),
        ]);
    }

    /**
     * Suppression d'une activité du calendrier.
     */
    public function destroy(Request $request, Calendrier $calendrier): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $calendrier->paroisse_configuration_id);

        $calendrier->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Activité du calendrier supprimée avec succès.',
        ]);
    }

    private function resolveCibleInternalId(string $cibleType, string $cibleUuid, int $paroisseId): ?int
    {
        return match ($cibleType) {
            'SECTION'   => Section::where('uuid', $cibleUuid)->value('id'),
            'NIVEAU'    => Niveau::where('uuid', $cibleUuid)->value('id'),
            'CLASSE'    => Classe::where('uuid', $cibleUuid)->value('id'),
            'CEB'       => Ceb::where('uuid', $cibleUuid)->value('id'),
            'MOUVEMENT' => Mouvement::where('uuid', $cibleUuid)->value('id'),
            default     => null,
        };
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
