<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ClasseResource;
use App\Models\AnneeCatechese;
use App\Models\Classe;
use App\Models\Niveau;
use App\Traits\HasPaginatedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClasseController extends Controller
{
    use HasPaginatedResponse;

    /**
     * Liste des classes avec filtres optionnels.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $query = Classe::with(['anneeCatechese', 'niveau.section'])
            ->withCount('inscriptionsAnnuelles')
            ->where('paroisse_configuration_id', $paroisseId);

        if ($request->filled('annee_catechese_id')) {
            $anneeId = AnneeCatechese::where('uuid', $request->annee_catechese_id)->value('id');
            if ($anneeId) {
                $query->where('annee_catechese_id', $anneeId);
            }
        } else {
            // Par défaut, si l'année n'est pas spécifiée, afficher l'année active de la paroisse
            $anneeActiveId = AnneeCatechese::where('paroisse_configuration_id', $paroisseId)
                ->where('est_active', true)
                ->value('id');
            if ($anneeActiveId) {
                $query->where('annee_catechese_id', $anneeActiveId);
            }
        }

        if ($request->filled('niveau_id')) {
            $niveauId = Niveau::where('uuid', $request->niveau_id)->value('id');
            if ($niveauId) {
                $query->where('niveau_id', $niveauId);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%");
            });
        }

        if ($request->filled('statut') && strtolower($request->statut) !== 'tous') {
            $query->where('statut', strtolower($request->statut));
        }

        $classes = $query->latest()->paginate($this->getPerPage($request));

        return $this->paginatedResponse($classes, ClasseResource::class);
    }

    /**
     * Création d'une nouvelle classe.
     */
    public function store(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $validated = $request->validate([
            'niveau_id'          => ['required', 'string', 'exists:niveaux,uuid'],
            'annee_catechese_id' => ['nullable', 'string', 'exists:annee_catecheses,uuid'],
            'nom'                => ['required', 'string', 'max:255'],
            'capacite_max'       => ['nullable', 'integer', 'min:1'],
            'statut'             => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $niveau = Niveau::where('uuid', $validated['niveau_id'])->firstOrFail();

        // Récupération de l'année pastorale fournie ou active
        if (!empty($validated['annee_catechese_id'])) {
            $annee = AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->firstOrFail();
            $anneeId = $annee->id;
        } else {
            $anneeId = AnneeCatechese::where('paroisse_configuration_id', $paroisseId)
                ->where('est_active', true)
                ->value('id');
            if (!$anneeId) {
                $anneeId = AnneeCatechese::where('paroisse_configuration_id', $paroisseId)->latest()->value('id');
            }
        }

        $classe = Classe::create([
            'paroisse_configuration_id' => $paroisseId,
            'annee_catechese_id'        => $anneeId,
            'niveau_id'                 => $niveau->id,
            'nom'                       => $validated['nom'],
            'capacite_max'              => $validated['capacite_max'] ?? 30,
            'statut'                    => $validated['statut'] ?? 'active',
        ]);

        $classe->load(['anneeCatechese', 'niveau.section']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Classe créée avec succès.',
            'data'    => new ClasseResource($classe),
        ], 201);
    }

    /**
     * Détails d'une classe par son UUID.
     */
    public function show(Request $request, Classe $classe): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $classe->paroisse_configuration_id);

        $classe->load(['anneeCatechese', 'niveau.section'])->loadCount('inscriptionsAnnuelles');

        return response()->json([
            'status' => 'success',
            'data'   => new ClasseResource($classe),
        ]);
    }

    /**
     * Mise à jour d'une classe.
     */
    public function update(Request $request, Classe $classe): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $classe->paroisse_configuration_id);

        $validated = $request->validate([
            'niveau_id'          => ['sometimes', 'required', 'string', 'exists:niveaux,uuid'],
            'annee_catechese_id' => ['sometimes', 'nullable', 'string', 'exists:annee_catecheses,uuid'],
            'nom'                => ['sometimes', 'required', 'string', 'max:255'],
            'capacite_max'       => ['nullable', 'integer', 'min:1'],
            'statut'             => ['nullable', 'string', 'in:active,inactive'],
        ]);

        $updateData = [];

        if (isset($validated['nom'])) {
            $updateData['nom'] = $validated['nom'];
        }

        if (array_key_exists('capacite_max', $validated)) {
            $updateData['capacite_max'] = $validated['capacite_max'] ?? 30;
        }

        if (isset($validated['statut'])) {
            $updateData['statut'] = $validated['statut'];
        }

        if (!empty($validated['annee_catechese_id'])) {
            $annee = AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->firstOrFail();
            $updateData['annee_catechese_id'] = $annee->id;
        }

        if (!empty($validated['niveau_id'])) {
            $niveau = Niveau::where('uuid', $validated['niveau_id'])->firstOrFail();
            $updateData['niveau_id'] = $niveau->id;
        }

        $classe->update($updateData);
        $classe->load(['anneeCatechese', 'niveau.section'])->loadCount('inscriptionsAnnuelles');

        return response()->json([
            'status'  => 'success',
            'message' => 'Classe mise à jour avec succès.',
            'data'    => new ClasseResource($classe),
        ]);
    }

    /**
     * Suppression d'une classe.
     */
    public function destroy(Request $request, Classe $classe): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $classe->paroisse_configuration_id);

        $classe->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Classe supprimée avec succès.',
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
