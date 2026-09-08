<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\DecisionFinAnneeResource;
use App\Models\AnneeCatechese;
use App\Models\BulletinTrimestriel;
use App\Models\CatecheseConfiguration;
use App\Models\Catechumene;
use App\Models\Classe;
use App\Models\DecisionFinAnnee;
use App\Models\InscriptionAnnuelle;
use App\Models\Niveau;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DecisionFinAnneeController extends Controller
{
    /**
     * Liste des décisions de fin d'année / Bilan annuel avec filtres.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id ?? CatecheseConfiguration::first()?->id;

        $classeParam = $request->input('classe_id') ?? $request->input('classe');
        $anneeParam = $request->input('annee_catechese_id') ?? $request->input('anneePastorale') ?? $request->input('annee_pastorale');

        $anneeId = null;
        if ($anneeParam) {
            $anneeId = AnneeCatechese::where('uuid', $anneeParam)
                ->orWhere('libelle', $anneeParam)
                ->orWhere('id', $anneeParam)
                ->value('id');
        }
        if (!$anneeId) {
            $anneeId = AnneeCatechese::getAnneeCourante($paroisseId)?->id ?? AnneeCatechese::first()?->id;
        }

        $classeId = null;
        if ($classeParam) {
            $classeId = Classe::where('uuid', $classeParam)
                ->orWhere('nom', $classeParam)
                ->orWhere('id', $classeParam)
                ->value('id');
        }

        // Si une classe ou une année est demandée, on s'assure que chaque inscription a un enregistrement de décision
        if ($classeId || $anneeId) {
            $inscriptions = InscriptionAnnuelle::with(['catechumene', 'niveau.section', 'classe', 'anneeCatechese'])
                ->where('paroisse_configuration_id', $paroisseId)
                ->when($anneeId, fn($q) => $q->where('annee_catechese_id', $anneeId))
                ->when($classeId, fn($q) => $q->where('classe_id', $classeId))
                ->get();

            foreach ($inscriptions as $inscr) {
                $avgBulletin = BulletinTrimestriel::where('inscription_annuelle_id', $inscr->id)->avg('moyenne_trimestrielle');
                $moyenne = $avgBulletin ? round((float) $avgBulletin, 2) : 12.00;
                $defaultDec = $moyenne >= 10.0 ? 'admis' : 'redouble';

                DecisionFinAnnee::firstOrCreate(
                    [
                        'paroisse_configuration_id' => $paroisseId,
                        'inscription_annuelle_id'   => $inscr->id,
                    ],
                    [
                        'moyenne_annuelle' => $moyenne,
                        'decision'         => $defaultDec,
                        'date_decision'    => now()->toDateString(),
                        'observations'     => json_encode([
                            'presenceCoursPct'   => 92,
                            'presenceMesse'      => 'Régulière',
                            'presenceCEB'        => 'Actif',
                            'presenceMouvement'  => 'Régulière',
                        ]),
                    ]
                );
            }
        }

        $query = DecisionFinAnnee::with(['inscriptionAnnuelle.catechumene', 'inscriptionAnnuelle.niveau.section', 'inscriptionAnnuelle.classe', 'inscriptionAnnuelle.anneeCatechese'])
            ->where('paroisse_configuration_id', $paroisseId);

        if ($anneeId) {
            $query->whereHas('inscriptionAnnuelle', fn($q) => $q->where('annee_catechese_id', $anneeId));
        }

        if ($classeId) {
            $query->whereHas('inscriptionAnnuelle', fn($q) => $q->where('classe_id', $classeId));
        }

        if ($request->filled('decision')) {
            $dec = strtolower($request->input('decision'));
            if ($dec === 'admis') $query->whereIn('decision', ['admis', 'sacrement_valide']);
            elseif ($dec === 'non admis' || $dec === 'non_admis') $query->whereIn('decision', ['redouble', 'exclu']);
            elseif ($dec === 'ajourné' || $dec === 'ajourne') $query->where('decision', 'redouble');
            else $query->where('decision', $dec);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->whereHas('inscriptionAnnuelle.catechumene', function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenoms', 'like', "%{$search}%")
                  ->orWhere('matricule', 'like', "%{$search}%");
            });
        }

        $decisions = $query->latest('date_decision')->get();

        return response()->json([
            'status' => 'success',
            'meta'   => [
                'total_elements' => $decisions->count(),
            ],
            'data'   => DecisionFinAnneeResource::collection($decisions),
        ]);
    }

    /**
     * Enregistrer ou valider une décision de fin d'année (individuelle ou en lot).
     */
    public function store(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id ?? CatecheseConfiguration::first()?->id;

        // Cas 1 : Validation globale de la classe
        if ($request->boolean('valide') || $request->has('annee_pastorale')) {
            $classeParam = $request->input('classe');
            $anneeParam = $request->input('annee_pastorale') ?? $request->input('anneePastorale');

            $anneeId = AnneeCatechese::where('libelle', $anneeParam)->orWhere('uuid', $anneeParam)->orWhere('id', $anneeParam)->value('id')
                ?? AnneeCatechese::getAnneeCourante($paroisseId)?->id;

            $classeId = Classe::where('nom', $classeParam)->orWhere('uuid', $classeParam)->orWhere('id', $classeParam)->value('id');

            if ($classeId && $anneeId) {
                $inscriptions = InscriptionAnnuelle::with('catechumene')
                    ->where('classe_id', $classeId)
                    ->where('annee_catechese_id', $anneeId)
                    ->get();

                $deliberations = $request->input('deliberations', []);
                $delibsKeyed = [];
                if (is_array($deliberations)) {
                    foreach ($deliberations as $delib) {
                        if (!empty($delib['matricule'])) {
                            $delibsKeyed[strtolower(trim($delib['matricule']))] = $delib;
                        }
                        if (!empty($delib['catechumeneId'])) {
                            $delibsKeyed[$delib['catechumeneId']] = $delib;
                        }
                        if (!empty($delib['catechumene_id'])) {
                            $delibsKeyed[$delib['catechumene_id']] = $delib;
                        }
                    }
                }

                foreach ($inscriptions as $inscr) {
                    $cat = $inscr->catechumene;
                    $matchedDelib = null;
                    if ($cat) {
                        if (!empty($cat->matricule) && isset($delibsKeyed[strtolower(trim($cat->matricule))])) {
                            $matchedDelib = $delibsKeyed[strtolower(trim($cat->matricule))];
                        } elseif (!empty($cat->uuid) && isset($delibsKeyed[$cat->uuid])) {
                            $matchedDelib = $delibsKeyed[$cat->uuid];
                        } elseif (isset($delibsKeyed[$cat->id])) {
                            $matchedDelib = $delibsKeyed[$cat->id];
                        }
                    }

                    // Décision choisie par l'animateur (priorité absolue)
                    $chosenDecision = $matchedDelib['decision'] ?? 'Admis';
                    $moy = isset($matchedDelib['moyenneGenerale']) ? (float)$matchedDelib['moyenneGenerale'] : null;

                    DecisionFinAnnee::updateOrCreate(
                        [
                            'paroisse_configuration_id' => $paroisseId,
                            'inscription_annuelle_id'   => $inscr->id,
                        ],
                        [
                            'decision'         => $chosenDecision,
                            'moyenne_annuelle' => $moy,
                            'date_decision'    => now()->toDateString(),
                            'observations'     => json_encode([
                                'presenceCoursNb'   => $matchedDelib['presenceCoursNb'] ?? null,
                                'presenceMesse'     => $matchedDelib['presenceMesse'] ?? 0,
                                'presenceCEB'       => $matchedDelib['presenceCEB'] ?? 0,
                                'presenceMouvement' => $matchedDelib['presenceMouvement'] ?? 0,
                                'decision'          => $chosenDecision,
                            ]),
                        ]
                    );
                }
            }

            return response()->json([
                'status'  => 'success',
                'message' => 'Bilan annuel de la classe validé avec succès.',
            ]);
        }

        // Cas 2 : Enregistrement ou mise à jour par catéchumène / inscription
        $catId = $request->input('catechumeneId') ?? $request->input('catechumene_id');
        $inscrId = $request->input('inscription_annuelle_id');

        $inscription = null;
        if ($inscrId) {
            $inscription = InscriptionAnnuelle::where('uuid', $inscrId)->orWhere('id', $inscrId)->first();
        } elseif ($catId) {
            $catechumene = Catechumene::where('uuid', $catId)->orWhere('id', $catId)->first();
            if ($catechumene) {
                $inscription = InscriptionAnnuelle::where('catechumene_id', $catechumene->id)->latest()->first();
            }
        }

        if (!$inscription) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Inscription annuelle introuvable pour ce catéchumène.',
            ], 422);
        }

        $rawDecision = strtolower($request->input('decision', 'admis'));
        $decisionDb = match($rawDecision) {
            'admis', 'sacrement_valide' => 'admis',
            'non admis', 'non_admis', 'redouble' => 'redouble',
            'ajourne', 'ajourné' => 'redouble',
            'exclu' => 'exclu',
            default => 'admis',
        };

        $obsData = [
            'presenceCoursPct'   => $request->input('presenceCoursPct', 90),
            'presenceMesse'      => $request->input('presenceMesse', 'Régulière'),
            'presenceCEB'        => $request->input('presenceCEB', 'Actif'),
            'presenceMouvement'  => $request->input('presenceMouvement', 'Régulière'),
        ];

        $decision = DecisionFinAnnee::updateOrCreate(
            [
                'paroisse_configuration_id' => $paroisseId,
                'inscription_annuelle_id'   => $inscription->id,
            ],
            [
                'moyenne_annuelle' => $request->input('moyenneGenerale') ?? $request->input('moyenne_annuelle'),
                'decision'         => $decisionDb,
                'mention'          => $request->input('mention'),
                'sacrement_recu'   => $request->boolean('sacrement_recu'),
                'date_decision'    => $request->input('date_decision', now()->toDateString()),
                'observations'     => json_encode($obsData),
            ]
        );

        $decision->load(['inscriptionAnnuelle.catechumene', 'inscriptionAnnuelle.niveau.section', 'inscriptionAnnuelle.classe', 'inscriptionAnnuelle.anneeCatechese']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Décision de fin d\'année enregistrée avec succès.',
            'data'    => new DecisionFinAnneeResource($decision),
        ], 201);
    }

    /**
     * Afficher les détails d'une décision.
     */
    public function show(Request $request, mixed $decision): JsonResponse
    {
        $model = $this->resolveDecision($decision);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $model->paroisse_configuration_id);

        $model->load(['inscriptionAnnuelle.catechumene', 'inscriptionAnnuelle.niveau.section', 'inscriptionAnnuelle.classe', 'inscriptionAnnuelle.anneeCatechese']);

        return response()->json([
            'status' => 'success',
            'data'   => new DecisionFinAnneeResource($model),
        ]);
    }

    /**
     * Supprimer une décision.
     */
    public function destroy(Request $request, mixed $decision): JsonResponse
    {
        $model = $this->resolveDecision($decision);
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $model->paroisse_configuration_id);

        $model->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Décision supprimée avec succès.',
        ]);
    }

    private function resolveDecision(mixed $decision): DecisionFinAnnee
    {
        if ($decision instanceof DecisionFinAnnee && $decision->exists) {
            return $decision;
        }

        $identifier = is_object($decision) ? ($decision->uuid ?? $decision->id ?? null) : $decision;

        return DecisionFinAnnee::where('uuid', $identifier)
            ->orWhere('id', $identifier)
            ->firstOrFail();
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}

