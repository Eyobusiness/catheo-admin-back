<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCatechumeneRequest;
use App\Http\Requests\Api\V1\UpdateCatechumeneRequest;
use App\Http\Resources\Api\V1\CatechumeneResource;
use App\Models\AnneeCatechese;
use App\Models\Ceb;
use App\Models\Classe;
use App\Models\Catechumene;
use App\Models\Niveau;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class CatechumeneController extends Controller
{
    /**
     * Liste filtrée et paginée des catéchumènes de la paroisse par Section, Niveau, Classe et Année.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $query = Catechumene::with([
            'inscriptionsAnnuelles.anneeCatechese',
            'inscriptionsAnnuelles.section',
            'inscriptionsAnnuelles.niveau',
            'inscriptionsAnnuelles.classe',
            'ceb',
            'parrainsMarraines',
        ])->where('paroisse_configuration_id', $paroisseId);

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('annee_catechese_id')) {
            $anneeId = AnneeCatechese::where('uuid', $request->annee_catechese_id)->value('id');
            if ($anneeId) {
                $query->whereHas('inscriptionsAnnuelles', function ($q) use ($anneeId) {
                    $q->where('annee_catechese_id', $anneeId);
                });
            }
        }

        if ($request->filled('section_id')) {
            $sectionId = Section::where('uuid', $request->section_id)->value('id');
            if ($sectionId) {
                $query->whereHas('inscriptionsAnnuelles', function ($q) use ($sectionId) {
                    $q->where('section_id', $sectionId);
                });
            }
        }

        if ($request->filled('niveau_id')) {
            $niveauId = Niveau::where('uuid', $request->niveau_id)->value('id');
            if ($niveauId) {
                $query->whereHas('inscriptionsAnnuelles', function ($q) use ($niveauId) {
                    $q->where('niveau_id', $niveauId);
                });
            }
        }

        if ($request->filled('classe_id')) {
            $classeId = Classe::where('uuid', $request->classe_id)->value('id');
            if ($classeId) {
                $query->whereHas('inscriptionsAnnuelles', function ($q) use ($classeId) {
                    $q->where('classe_id', $classeId);
                });
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenoms', 'like', "%{$search}%")
                  ->orWhere('code_catechumene', 'like', "%{$search}%")
                  ->orWhere('telephone', 'like', "%{$search}%")
                  ->orWhere('telephone_pere', 'like', "%{$search}%")
                  ->orWhere('telephone_mere', 'like', "%{$search}%")
                  ->orWhere('telephone_tuteur', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->get('per_page', 15);
        $catechumenes = $query->latest()->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data'   => CatechumeneResource::collection($catechumenes->items()),
            'meta'   => [
                'current_page' => $catechumenes->currentPage(),
                'last_page'    => $catechumenes->lastPage(),
                'per_page'     => $catechumenes->perPage(),
                'total'        => $catechumenes->total(),
            ],
        ]);
    }

    /**
     * Recherche rapide par Matricule / Code Catéchumène pour les guichets d'inscription.
     */
    public function showByMatricule(Request $request, string $code): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $catechumene = Catechumene::with([
            'ceb',
            'inscriptionsAnnuelles.anneeCatechese',
            'inscriptionsAnnuelles.section',
            'inscriptionsAnnuelles.niveau',
            'inscriptionsAnnuelles.classe',
            'parrainsMarraines',
        ])
        ->where('paroisse_configuration_id', $paroisseId)
        ->where('code_catechumene', trim($code))
        ->first();

        if (!$catechumene) {
            return response()->json([
                'status'  => 'error',
                'message' => "Aucun catéchumène trouvé avec le matricule '{$code}'.",
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => new CatechumeneResource($catechumene),
        ]);
    }

    /**
     * Création directe d'une fiche catéchumène par l'administration.
     */
    public function store(StoreCatechumeneRequest $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;
        $validated = $request->validated();

        if (!empty($validated['ceb_id'])) {
            $ceb = Ceb::where('uuid', $validated['ceb_id'])->firstOrFail();
            $validated['ceb_id'] = $ceb->id;
        }

        if (empty($validated['photo_path']) && !empty($validated['photo_url'])) {
            $validated['photo_path'] = $validated['photo_url'];
        }
        unset($validated['photo_url']);

        $validated['paroisse_configuration_id'] = $paroisseId;

        // Génération du matricule unique (ex: CAT-2026-0001)
        $anneeCourante = date('Y');
        $count = Catechumene::where('paroisse_configuration_id', $paroisseId)
            ->where('code_catechumene', 'like', "CAT-{$anneeCourante}-%")
            ->count();
        $validated['code_catechumene'] = sprintf("CAT-%s-%04d", $anneeCourante, $count + 1);
        $validated['password'] = Hash::make('12345678');
        $validated['statut'] = $validated['statut'] ?? 'actif';

        $catechumene = Catechumene::create($validated);
        $catechumene->load(['ceb', 'parrainsMarraines']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Catéchumène créé avec succès.',
            'data'    => new CatechumeneResource($catechumene),
        ], 201);
    }

    /**
     * Détails complets d'un catéchumène.
     */
    public function show(Request $request, Catechumene $catechumene): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $catechumene->paroisse_configuration_id);

        $catechumene->load([
            'ceb',
            'inscriptionsAnnuelles.anneeCatechese',
            'inscriptionsAnnuelles.section',
            'inscriptionsAnnuelles.niveau',
            'inscriptionsAnnuelles.classe',
            'parrainsMarraines',
        ]);

        return response()->json([
            'status' => 'success',
            'data'   => new CatechumeneResource($catechumene),
        ]);
    }

    /**
     * Mise à jour d'un catéchumène.
     */
    public function update(UpdateCatechumeneRequest $request, Catechumene $catechumene): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $catechumene->paroisse_configuration_id);
        $validated = $request->validated();

        if (array_key_exists('ceb_id', $validated)) {
            if ($validated['ceb_id']) {
                $ceb = Ceb::where('uuid', $validated['ceb_id'])->firstOrFail();
                $validated['ceb_id'] = $ceb->id;
            } else {
                $validated['ceb_id'] = null;
            }
        }

        if (empty($validated['photo_path']) && !empty($validated['photo_url'])) {
            $validated['photo_path'] = $validated['photo_url'];
        }
        unset($validated['photo_url']);

        $catechumene->update($validated);
        $catechumene->load([
            'ceb',
            'inscriptionsAnnuelles.anneeCatechese',
            'inscriptionsAnnuelles.section',
            'inscriptionsAnnuelles.niveau',
            'inscriptionsAnnuelles.classe',
            'parrainsMarraines',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Catéchumène mis à jour avec succès.',
            'data'    => new CatechumeneResource($catechumene),
        ]);
    }

    /**
     * Suppression d'un catéchumène.
     */
    public function destroy(Request $request, Catechumene $catechumene): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $catechumene->paroisse_configuration_id);

        $catechumene->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Catéchumène supprimé avec succès.',
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
