<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\InscriptionAnnuelleResource;
use App\Models\AnneeCatechese;
use App\Models\Ceb;
use App\Models\Classe;
use App\Models\Catechumene;
use App\Models\InscriptionAnnuelle;
use App\Models\Mouvement;
use App\Models\Niveau;
use App\Models\OperationPaiement;
use App\Models\Section;
use App\Models\Tarif;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InscriptionAnnuelleController extends Controller
{
    /**
     * Liste des inscriptions annuelles avec filtres.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id ?? \App\Models\CatecheseConfiguration::value('id');

        $query = InscriptionAnnuelle::with(['catechumene', 'anneeCatechese', 'section', 'niveau.section', 'classe', 'ceb', 'mouvement']);

        if ($paroisseId) {
            $query->where(function ($q) use ($paroisseId) {
                $q->where('paroisse_configuration_id', $paroisseId)
                  ->orWhereNull('paroisse_configuration_id');
            });
        }

        if ($request->filled('annee_catechese_id') && !in_array(strtolower($request->annee_catechese_id), ['all', 'tous', 'undefined', 'null'])) {
            $val = $request->annee_catechese_id;
            $anneeId = is_numeric($val) ? (int) $val : AnneeCatechese::where('uuid', $val)->value('id');
            if ($anneeId) {
                $query->where('annee_catechese_id', $anneeId);
            }
        }

        if ($request->filled('section_id') && !in_array(strtolower($request->section_id), ['all', 'tous', 'undefined', 'null'])) {
            $val = $request->section_id;
            $sectionId = is_numeric($val) ? (int) $val : Section::where('uuid', $val)->value('id');
            if ($sectionId) {
                $query->where('section_id', $sectionId);
            }
        }

        if ($request->filled('niveau_id') && !in_array(strtolower($request->niveau_id), ['all', 'tous', 'undefined', 'null'])) {
            $val = $request->niveau_id;
            $niveauId = is_numeric($val) ? (int) $val : Niveau::where('uuid', $val)->value('id');
            if ($niveauId) {
                $query->where('niveau_id', $niveauId);
            }
        }

        if ($request->filled('classe_id') && !in_array(strtolower($request->classe_id), ['all', 'tous', 'undefined', 'null'])) {
            $val = $request->classe_id;
            $classeId = is_numeric($val) ? (int) $val : Classe::where('uuid', $val)->value('id');
            if ($classeId) {
                $query->where('classe_id', $classeId);
            }
        }

        if ($request->filled('ceb_id') && !in_array(strtolower($request->ceb_id), ['all', 'tous', 'undefined', 'null'])) {
            $val = $request->ceb_id;
            $cebId = is_numeric($val) ? (int) $val : Ceb::where('uuid', $val)->value('id');
            if ($cebId) {
                $query->where('ceb_id', $cebId);
            }
        }

        if ($request->filled('statut_inscription') && !in_array(strtolower($request->statut_inscription), ['all', 'tous', 'undefined', 'null'])) {
            $query->where('statut_inscription', $request->statut_inscription);
        } elseif ($request->filled('statut') && !in_array(strtolower($request->statut), ['all', 'tous', 'undefined', 'null'])) {
            $query->where('statut_inscription', $request->statut);
        }

        if ($request->filled('frais_payes')) {
            $query->where('frais_inscription_payes', filter_var($request->frais_payes, FILTER_VALIDATE_BOOLEAN));
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
                $q->where('code_inscription', 'like', "%{$search}%")
                  ->orWhere('uuid', $search)
                  ->orWhereHas('catechumene', function ($cq) use ($search, $concat1, $concat2) {
                      $cq->where('nom', 'like', "%{$search}%")
                         ->orWhere('prenoms', 'like', "%{$search}%")
                         ->orWhere('matricule', 'like', "%{$search}%")
                         ->orWhere('uuid', $search)
                         ->orWhere('telephone', 'like', "%{$search}%")
                         ->orWhereRaw("{$concat1} LIKE ?", ["%{$search}%"])
                         ->orWhereRaw("{$concat2} LIKE ?", ["%{$search}%"]);

                      $words = preg_split('/\s+/', $search);
                      if (count($words) > 1) {
                          $cq->orWhere(function ($subQ) use ($words) {
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
            });
        }

        if ($request->boolean('all') || $request->get('per_page') === 'all') {
            $items = $query->latest()->get();
            return response()->json([
                'status' => 'success',
                'data'   => InscriptionAnnuelleResource::collection($items),
            ]);
        }

        $perPage = (int) $request->get('per_page', 20);
        $inscriptions = $query->latest()->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data'   => InscriptionAnnuelleResource::collection($inscriptions->items()),
            'meta'   => [
                'current_page' => $inscriptions->currentPage(),
                'last_page'    => $inscriptions->lastPage(),
                'per_page'     => $inscriptions->perPage(),
                'total'        => $inscriptions->total(),
            ],
        ]);
    }

    /**
     * Inscrire un catéchumène pour une année pastorale.
     */
    public function store(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $validated = $request->validate([
            'catechumene_id'          => ['required', 'string'],
            'annee_catechese_id'      => ['required', 'string'],
            'section_id'              => ['nullable', 'string'],
            'niveau_id'               => ['required', 'string'],
            'classe_id'               => ['nullable', 'string'],
            'ceb_id'                  => ['nullable', 'string'],
            'mouvement_id'            => ['nullable', 'string'],
            'tarif_id'                => ['nullable', 'string'],
            'date_inscription'        => ['nullable', 'date'],
            'frais_inscription_payes' => ['nullable', 'boolean'],
            'observation'             => ['nullable', 'string'],
        ]);

        $catechumene = is_numeric($validated['catechumene_id'])
            ? Catechumene::findOrFail($validated['catechumene_id'])
            : Catechumene::where('uuid', $validated['catechumene_id'])->firstOrFail();

        $annee = is_numeric($validated['annee_catechese_id'])
            ? AnneeCatechese::findOrFail($validated['annee_catechese_id'])
            : AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->firstOrFail();

        $niveau = is_numeric($validated['niveau_id'])
            ? Niveau::findOrFail($validated['niveau_id'])
            : Niveau::where('uuid', $validated['niveau_id'])->firstOrFail();

        $paroisseId = $paroisseId ?? $catechumene->paroisse_configuration_id ?? \App\Models\CatecheseConfiguration::value('id');

        $validated['paroisse_configuration_id'] = $paroisseId;
        $validated['catechumene_id'] = $catechumene->id;
        $validated['annee_catechese_id'] = $annee->id;
        $validated['niveau_id'] = $niveau->id;
        $validated['section_id'] = !empty($validated['section_id'])
            ? (is_numeric($validated['section_id']) ? (int)$validated['section_id'] : Section::where('uuid', $validated['section_id'])->value('id'))
            : $niveau->section_id;
        $validated['date_inscription'] = $validated['date_inscription'] ?? now()->toDateString();
        $validated['statut_inscription'] = 'valide';

        if (!empty($validated['classe_id'])) {
            $validated['classe_id'] = is_numeric($validated['classe_id'])
                ? (int)$validated['classe_id']
                : Classe::where('uuid', $validated['classe_id'])->value('id');
        }

        if (!empty($validated['ceb_id'])) {
            $validated['ceb_id'] = is_numeric($validated['ceb_id'])
                ? (int)$validated['ceb_id']
                : Ceb::where('uuid', $validated['ceb_id'])->value('id');
        }

        if (!empty($validated['mouvement_id'])) {
            $validated['mouvement_id'] = is_numeric($validated['mouvement_id'])
                ? (int)$validated['mouvement_id']
                : Mouvement::where('uuid', $validated['mouvement_id'])->value('id');
        }

        // Générer un code d'inscription unique si absent
        $count = InscriptionAnnuelle::where('paroisse_configuration_id', $paroisseId)
            ->where('annee_catechese_id', $annee->id)
            ->count();
        $prefix = explode('-', $annee->libelle ?? date('Y'))[0] ?? date('Y');
        $validated['code_inscription'] = sprintf("INS-%s-%04d", $prefix, $count + 1);

        $inscription = InscriptionAnnuelle::create($validated);
        $inscription->load(['catechumene', 'anneeCatechese', 'section', 'niveau', 'classe', 'ceb', 'mouvement']);

        // Déclencher une opération de paiement en attente dans la finance UNIQUEMENT si un tarif réel existe
        if (!$inscription->frais_inscription_payes) {
            $tarif = $this->resolveTarifForInscription($paroisseId, $annee->id, $niveau, $request->input('tarif_id'));

            if ($tarif) {
                $refCount = OperationPaiement::where('paroisse_configuration_id', $paroisseId)->count() + 1;
                $reference = 'OP-' . date('Y') . '-' . sprintf('%04d', $refCount);

                OperationPaiement::updateOrCreate(
                    [
                        'paroisse_configuration_id' => $paroisseId,
                        'catechumene_id'            => $catechumene->id,
                        'annee_catechese_id'        => $annee->id,
                        'tarif_id'                  => $tarif->id,
                        'statut'                    => 'en_attente',
                    ],
                    [
                        'reference'    => $reference,
                        'libelle'      => "{$tarif->intitule} - {$catechumene->nom_complet} ({$niveau->nom})",
                        'montant'      => (float) $tarif->montant,
                        'montant_paye' => 0,
                        'echeance'     => $tarif->periode_fin?->toDateString() ?? now()->addMonths(1)->toDateString(),
                    ]
                );
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Catéchumène inscrit pour l\'année pastorale avec succès.',
            'data'    => new InscriptionAnnuelleResource($inscription),
        ], 201);
    }

    /**
     * Résout une instance d'InscriptionAnnuelle par UUID, ID ou relations.
     */
    private function resolveInscription(mixed $inscription): InscriptionAnnuelle
    {
        if ($inscription instanceof InscriptionAnnuelle) {
            return $inscription;
        }

        $item = is_numeric($inscription)
            ? InscriptionAnnuelle::find((int) $inscription)
            : InscriptionAnnuelle::where('uuid', $inscription)->first();

        if (!$item) {
            $item = InscriptionAnnuelle::where('code_inscription', $inscription)
                ->orWhereHas('catechumene', function ($q) use ($inscription) {
                    $q->where('uuid', $inscription)->orWhere('matricule', $inscription);
                })
                ->first();
        }

        if (!$item) {
            abort(response()->json([
                'status'  => 'error',
                'message' => 'Inscription annuelle introuvable.',
            ], 404));
        }

        return $item;
    }

    /**
     * Détails d'une inscription.
     */
    public function show(Request $request, mixed $inscription): JsonResponse
    {
        $item = $this->resolveInscription($inscription);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $item->paroisse_configuration_id);

        $item->load(['catechumene', 'anneeCatechese', 'section', 'niveau.section', 'classe', 'ceb', 'mouvement']);

        return response()->json([
            'status' => 'success',
            'data'   => new InscriptionAnnuelleResource($item),
        ]);
    }

    /**
     * Mettre à jour une inscription / affectation (ex: classe, section, niveau, paiement).
     */
    public function update(Request $request, mixed $inscription): JsonResponse
    {
        $item = $this->resolveInscription($inscription);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $item->paroisse_configuration_id);

        $validated = $request->validate([
            'section_id'              => ['nullable', 'string'],
            'niveau_id'               => ['nullable', 'string'],
            'classe_id'               => ['nullable', 'string'],
            'ceb_id'                  => ['nullable', 'string'],
            'mouvement_id'            => ['nullable', 'string'],
            'statut_inscription'      => ['nullable', 'string'],
            'statut'                  => ['nullable', 'string'],
            'frais_inscription_payes' => ['nullable', 'boolean'],
            'frais_payes'             => ['nullable', 'boolean'],
            'observation'             => ['nullable', 'string'],
        ]);

        // Résolution de la classe
        if (array_key_exists('classe_id', $validated)) {
            $cVal = $validated['classe_id'];
            if (!empty($cVal)) {
                $classe = is_numeric($cVal) ? Classe::with('niveau')->find($cVal) : Classe::with('niveau')->where('uuid', $cVal)->first();
                if ($classe) {
                    $validated['classe_id'] = $classe->id;
                    // Auto-dériver le niveau et la section depuis la classe si non fournis
                    if (empty($validated['niveau_id'])) {
                        $validated['niveau_id'] = $classe->niveau_id;
                    }
                    if (empty($validated['section_id']) && $classe->niveau) {
                        $validated['section_id'] = $classe->niveau->section_id;
                    }
                } else {
                    $validated['classe_id'] = null;
                }
            } else {
                $validated['classe_id'] = null;
            }
        }

        // Résolution du niveau
        if (array_key_exists('niveau_id', $validated) && !empty($validated['niveau_id'])) {
            $nVal = $validated['niveau_id'];
            $niveau = is_numeric($nVal) ? Niveau::find($nVal) : Niveau::where('uuid', $nVal)->first();
            if ($niveau) {
                $validated['niveau_id'] = $niveau->id;
                if (empty($validated['section_id'])) {
                    $validated['section_id'] = $niveau->section_id;
                }
            }
        }

        // Résolution de la section
        if (array_key_exists('section_id', $validated)) {
            $sVal = $validated['section_id'];
            $validated['section_id'] = !empty($sVal)
                ? (is_numeric($sVal) ? (int) $sVal : Section::where('uuid', $sVal)->value('id'))
                : null;
        }

        // Résolution du CEB
        if (array_key_exists('ceb_id', $validated)) {
            $cebVal = $validated['ceb_id'];
            $validated['ceb_id'] = !empty($cebVal)
                ? (is_numeric($cebVal) ? (int) $cebVal : Ceb::where('uuid', $cebVal)->value('id'))
                : null;
        }

        // Résolution du Mouvement
        if (array_key_exists('mouvement_id', $validated)) {
            $mVal = $validated['mouvement_id'];
            $validated['mouvement_id'] = !empty($mVal)
                ? (is_numeric($mVal) ? (int) $mVal : Mouvement::where('uuid', $mVal)->value('id'))
                : null;
        }

        if (isset($validated['statut'])) {
            $validated['statut_inscription'] = $validated['statut'];
        }

        if (isset($validated['frais_payes'])) {
            $validated['frais_inscription_payes'] = $validated['frais_payes'];
        }

        $item->update($validated);
        $item->load(['catechumene', 'anneeCatechese', 'section', 'niveau.section', 'classe', 'ceb', 'mouvement']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Affectation et inscription mises à jour avec succès.',
            'data'    => new InscriptionAnnuelleResource($item),
        ]);
    }

    /**
     * Affectation de catéchumène(s) à une classe (individuelle ou par lot).
     * Route: POST /api/v1/inscriptions-annuelles/affecter
     */
    public function affecter(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $validated = $request->validate([
            'classe_id'         => ['required', 'string'],
            'inscription_ids'   => ['nullable', 'array'],
            'inscription_ids.*' => ['string'],
            'catechumene_ids'   => ['nullable', 'array'],
            'catechumene_ids.*' => ['string'],
            'inscription_id'    => ['nullable', 'string'],
            'catechumene_id'    => ['nullable', 'string'],
        ]);

        $cVal = $validated['classe_id'];
        $classe = is_numeric($cVal)
            ? Classe::with('niveau')->find($cVal)
            : Classe::with('niveau')->where('uuid', $cVal)->first();

        if (!$classe) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Classe introuvable.',
            ], 404);
        }

        $updatedCount = 0;

        // 1. Affectation par IDs d'inscriptions
        $inscriptionIds = $validated['inscription_ids'] ?? [];
        if (!empty($validated['inscription_id'])) {
            $inscriptionIds[] = $validated['inscription_id'];
        }

        foreach ($inscriptionIds as $insId) {
            try {
                $ins = $this->resolveInscription($insId);
                $ins->update([
                    'classe_id'  => $classe->id,
                    'niveau_id'  => $classe->niveau_id ?? $ins->niveau_id,
                    'section_id' => $classe->niveau?->section_id ?? $ins->section_id,
                ]);
                $updatedCount++;
            } catch (\Throwable $e) {
                // Continue
            }
        }

        // 2. Affectation par IDs de catéchumènes
        $catIds = $validated['catechumene_ids'] ?? [];
        if (!empty($validated['catechumene_id'])) {
            $catIds[] = $validated['catechumene_id'];
        }

        foreach ($catIds as $cId) {
            try {
                $cat = is_numeric($cId) ? Catechumene::find($cId) : Catechumene::where('uuid', $cId)->first();
                if ($cat) {
                    $ins = InscriptionAnnuelle::where('catechumene_id', $cat->id)->latest()->first();
                    if ($ins) {
                        $ins->update([
                            'classe_id'  => $classe->id,
                            'niveau_id'  => $classe->niveau_id ?? $ins->niveau_id,
                            'section_id' => $classe->niveau?->section_id ?? $ins->section_id,
                        ]);
                        $updatedCount++;
                    }
                }
            } catch (\Throwable $e) {
                // Continue
            }
        }

        return response()->json([
            'status'  => 'success',
            'message' => "Affectation réussie pour {$updatedCount} catéchumène(s) dans la classe '{$classe->nom}'.",
        ]);
    }

    /**
     * Annuler/Supprimer une inscription.
     */
    public function destroy(Request $request, mixed $inscription): JsonResponse
    {
        $item = $this->resolveInscription($inscription);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $item->paroisse_configuration_id);

        $item->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Inscription supprimée avec succès.',
        ]);
    }

    /**
     * Résout le tarif d'inscription approprié pour un niveau et une année pastorale.
     */
    private function resolveTarifForInscription(int $paroisseId, ?int $anneeId, ?Niveau $niveau, ?string $explicitTarifId = null): ?Tarif
    {
        return Tarif::resolveForInscription($paroisseId, $anneeId, $niveau, $explicitTarifId);
    }

    private function authorizeTenant(?int $userParoisseId, ?int $targetParoisseId): void
    {
        if ($userParoisseId && $targetParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
