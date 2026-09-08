<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CebResource;
use App\Models\Ceb;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CebController extends Controller
{
    /**
     * Liste de toutes les CEB de la paroisse avec filtres.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()?->paroisse_configuration_id ?? \App\Models\CatecheseConfiguration::first()?->id;

        $query = Ceb::withCount('inscriptionsAnnuelles')
            ->where('paroisse_configuration_id', $paroisseId);

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('responsable', 'like', "%{$search}%")
                  ->orWhere('telephone', 'like', "%{$search}%")
                  ->orWhere('adresse', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->filled('statut') && strtolower($request->input('statut')) !== 'tous') {
            $query->where('statut', ucfirst(strtolower($request->input('statut'))));
        }

        $cebs = $query->orderBy('nom', 'asc')->get();

        return response()->json([
            'status' => 'success',
            'meta'   => [
                'total_elements' => $cebs->count(),
            ],
            'data'   => CebResource::collection($cebs),
        ]);
    }

    /**
     * Création d'une nouvelle CEB.
     */
    public function store(Request $request): JsonResponse
    {
        $paroisseId = $request->user()?->paroisse_configuration_id ?? \App\Models\CatecheseConfiguration::first()?->id;

        $validated = $request->validate([
            'nom'         => ['required', 'string', 'max:150'],
            'responsable' => ['nullable', 'string', 'max:150'],
            'telephone'   => ['nullable', 'string', 'max:20'],
            'adresse'     => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'statut'      => ['nullable', 'string', 'in:Active,Inactive,active,inactive'],
        ]);

        $validated['paroisse_configuration_id'] = $paroisseId;
        $validated['statut'] = ucfirst(strtolower($validated['statut'] ?? 'Active'));

        $ceb = Ceb::create($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'CEB créée avec succès.',
            'data'    => new CebResource($ceb),
        ], 201);
    }

    /**
     * Détails d'une CEB.
     */
    public function show(Request $request, Ceb $ceb): JsonResponse
    {
        $this->authorizeTenant($request->user()?->paroisse_configuration_id ?? \App\Models\CatecheseConfiguration::first()?->id, $ceb->paroisse_configuration_id);

        $ceb->loadCount('inscriptionsAnnuelles');

        return response()->json([
            'status' => 'success',
            'data'   => new CebResource($ceb),
        ]);
    }

    /**
     * Mise à jour des informations d'une CEB.
     */
    public function update(Request $request, Ceb $ceb): JsonResponse
    {
        $this->authorizeTenant($request->user()?->paroisse_configuration_id ?? \App\Models\CatecheseConfiguration::first()?->id, $ceb->paroisse_configuration_id);

        $validated = $request->validate([
            'nom'         => ['sometimes', 'required', 'string', 'max:150'],
            'responsable' => ['nullable', 'string', 'max:150'],
            'telephone'   => ['nullable', 'string', 'max:20'],
            'adresse'     => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'statut'      => ['nullable', 'string', 'in:Active,Inactive,active,inactive'],
        ]);

        if (isset($validated['statut'])) {
            $validated['statut'] = ucfirst(strtolower($validated['statut']));
        }

        $ceb->update($validated);
        $ceb->loadCount('inscriptionsAnnuelles');

        return response()->json([
            'status'  => 'success',
            'message' => 'CEB mise à jour avec succès.',
            'data'    => new CebResource($ceb),
        ]);
    }

    /**
     * Basculer le statut d'une CEB (Active <-> Inactive).
     */
    public function toggleStatus(Request $request, Ceb $ceb): JsonResponse
    {
        $this->authorizeTenant($request->user()?->paroisse_configuration_id ?? \App\Models\CatecheseConfiguration::first()?->id, $ceb->paroisse_configuration_id);

        $nouveauStatut = (strtolower($ceb->statut) === 'active') ? 'Inactive' : 'Active';
        $ceb->update(['statut' => $nouveauStatut]);
        $ceb->loadCount('inscriptionsAnnuelles');

        return response()->json([
            'status'  => 'success',
            'message' => "Le statut de la CEB '{$ceb->nom}' est désormais {$nouveauStatut}.",
            'data'    => new CebResource($ceb),
        ]);
    }

    /**
     * Suppression d'une CEB.
     */
    public function destroy(Request $request, Ceb $ceb): JsonResponse
    {
        $this->authorizeTenant($request->user()?->paroisse_configuration_id ?? \App\Models\CatecheseConfiguration::first()?->id, $ceb->paroisse_configuration_id);

        if ($ceb->inscriptionsAnnuelles()->count() > 0) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Impossible de supprimer une CEB rattachée à des inscriptions annuelles.',
            ], 422);
        }

        $ceb->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'CEB supprimée avec succès.',
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
