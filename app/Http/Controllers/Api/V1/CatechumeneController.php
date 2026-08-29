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
        $paroisseId = $request->user()->paroisse_configuration_id ?? \App\Models\CatecheseConfiguration::value('id');

        $query = Catechumene::with([
            'inscriptionsAnnuelles' => function ($q) {
                $q->latest('id');
            },
            'inscriptionsAnnuelles.anneeCatechese',
            'inscriptionsAnnuelles.section',
            'inscriptionsAnnuelles.niveau.section',
            'inscriptionsAnnuelles.classe',
            'ceb',
            'parrainsMarraines',
        ]);

        if ($paroisseId) {
            $query->where(function ($q) use ($paroisseId) {
                $q->where('paroisse_configuration_id', $paroisseId)
                  ->orWhereNull('paroisse_configuration_id');
            });
        }

        if ($request->filled('statut') && !in_array(strtolower($request->statut), ['all', 'tous', 'undefined', 'null'])) {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('annee_catechese_id') && !in_array(strtolower($request->annee_catechese_id), ['all', 'tous', 'undefined', 'null'])) {
            $val = $request->annee_catechese_id;
            $anneeId = is_numeric($val) ? (int) $val : AnneeCatechese::where('uuid', $val)->value('id');
            if ($anneeId) {
                $query->whereHas('inscriptionsAnnuelles', function ($q) use ($anneeId) {
                    $q->where('annee_catechese_id', $anneeId);
                });
            }
        }

        if ($request->filled('section_id') && !in_array(strtolower($request->section_id), ['all', 'tous', 'undefined', 'null'])) {
            $val = $request->section_id;
            $sectionId = is_numeric($val) ? (int) $val : Section::where('uuid', $val)->value('id');
            if ($sectionId) {
                $query->whereHas('inscriptionsAnnuelles', function ($q) use ($sectionId) {
                    $q->where('section_id', $sectionId);
                });
            }
        }

        if ($request->filled('niveau_id') && !in_array(strtolower($request->niveau_id), ['all', 'tous', 'undefined', 'null'])) {
            $val = $request->niveau_id;
            $niveauId = is_numeric($val) ? (int) $val : Niveau::where('uuid', $val)->value('id');
            if ($niveauId) {
                $query->whereHas('inscriptionsAnnuelles', function ($q) use ($niveauId) {
                    $q->where('niveau_id', $niveauId);
                });
            }
        }

        if ($request->filled('classe_id') && !in_array(strtolower($request->classe_id), ['all', 'tous', 'undefined', 'null'])) {
            $val = $request->classe_id;
            $classeId = is_numeric($val) ? (int) $val : Classe::where('uuid', $val)->value('id');
            if ($classeId) {
                $query->whereHas('inscriptionsAnnuelles', function ($q) use ($classeId) {
                    $q->where('classe_id', $classeId);
                });
            }
        }

        if ($request->filled('ceb_id') && !in_array(strtolower($request->ceb_id), ['all', 'tous', 'undefined', 'null'])) {
            $val = $request->ceb_id;
            $cebId = is_numeric($val) ? (int) $val : Ceb::where('uuid', $val)->value('id');
            if ($cebId) {
                $query->where('ceb_id', $cebId);
            }
        }

        // Filtre selon le statut de paiement des frais d'inscription
        if ($request->boolean('payes_uniquement') || $request->input('statut_paiement') === 'paye') {
            $query->whereHas('inscriptionsAnnuelles', function ($q) {
                $q->where('frais_inscription_payes', true);
            });
        } elseif ($request->input('statut_paiement') === 'non_paye' || $request->input('statut_paiement') === 'en_attente') {
            $query->whereHas('inscriptionsAnnuelles', function ($q) {
                $q->where('frais_inscription_payes', false);
            });
        }

        $search = $request->input('search')
            ?? $request->input('q')
            ?? $request->input('query')
            ?? $request->input('terme')
            ?? $request->input('term');

        if (!empty($search)) {
            $search = trim($search);
            $isSqlite = \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite';
            $concat1 = $isSqlite ? "(nom || ' ' || prenoms)" : "CONCAT(nom, ' ', prenoms)";
            $concat2 = $isSqlite ? "(prenoms || ' ' || nom)" : "CONCAT(prenoms, ' ', nom)";

            $query->where(function ($q) use ($search, $concat1, $concat2) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenoms', 'like', "%{$search}%")
                  ->orWhere('matricule', 'like', "%{$search}%")
                  ->orWhere('uuid', $search)
                  ->orWhere('telephone', 'like', "%{$search}%")
                  ->orWhere('telephone_pere', 'like', "%{$search}%")
                  ->orWhere('telephone_mere', 'like', "%{$search}%")
                  ->orWhere('telephone_tuteur', 'like', "%{$search}%");

                $q->orWhereRaw("{$concat1} LIKE ?", ["%{$search}%"])
                  ->orWhereRaw("{$concat2} LIKE ?", ["%{$search}%"]);

                $words = preg_split('/\s+/', $search);
                if (count($words) > 1) {
                    $q->orWhere(function ($subQ) use ($words) {
                        foreach ($words as $word) {
                            $subQ->where(function ($wQ) use ($word) {
                                $wQ->where('nom', 'like', "%{$word}%")
                                   ->orWhere('prenoms', 'like', "%{$word}%")
                                   ->orWhere('matricule', 'like', "%{$word}%");
                            });
                        }
                    });
                }
            });
        }

        if ($request->boolean('all') || $request->get('per_page') === 'all') {
            $items = $query->latest()->get();
            return response()->json([
                'status' => 'success',
                'data'   => CatechumeneResource::collection($items),
            ]);
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
     * Recherche rapide par Matricule pour les guichets d'inscription.
     */
    public function showByMatricule(Request $request, string $code): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;
        $trimmedCode = strtolower(trim($code));

        $catechumene = Catechumene::with([
            'ceb',
            'inscriptionsAnnuelles.anneeCatechese',
            'inscriptionsAnnuelles.section',
            'inscriptionsAnnuelles.niveau',
            'inscriptionsAnnuelles.classe',
            'parrainsMarraines',
        ])
        ->where('paroisse_configuration_id', $paroisseId)
        ->where(function ($q) use ($trimmedCode) {
            $q->whereRaw('LOWER(matricule) = ?', [$trimmedCode]);
        })
        ->first();

        if (!$catechumene) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Aucun catéchumène trouvé avec ce matricule.',
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

        // Génération du matricule officiel unique (ex: CIM26-1001124002A)
        $validated['matricule'] = app(\App\Services\MatriculeGeneratorService::class)->generate($paroisseId);
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
