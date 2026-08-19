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
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InscriptionAnnuelleController extends Controller
{
    /**
     * Liste des inscriptions annuelles avec filtres.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $query = InscriptionAnnuelle::with(['catechumene', 'anneeCatechese', 'section', 'niveau', 'classe', 'ceb', 'mouvement'])
            ->where('paroisse_configuration_id', $paroisseId);

        if ($request->filled('annee_catechese_id')) {
            $anneeId = AnneeCatechese::where('uuid', $request->annee_catechese_id)->value('id');
            if ($anneeId) {
                $query->where('annee_catechese_id', $anneeId);
            }
        }

        if ($request->filled('section_id')) {
            $sectionId = Section::where('uuid', $request->section_id)->value('id');
            if ($sectionId) {
                $query->where('section_id', $sectionId);
            }
        }

        if ($request->filled('niveau_id')) {
            $niveauId = Niveau::where('uuid', $request->niveau_id)->value('id');
            if ($niveauId) {
                $query->where('niveau_id', $niveauId);
            }
        }

        if ($request->filled('classe_id')) {
            $classeId = Classe::where('uuid', $request->classe_id)->value('id');
            if ($classeId) {
                $query->where('classe_id', $classeId);
            }
        }

        if ($request->filled('statut_inscription')) {
            $query->where('statut_inscription', $request->statut_inscription);
        }

        $inscriptions = $query->latest()->paginate($request->get('per_page', 20));

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
            'catechumene_id'          => ['required', 'string', 'exists:catechumenes,uuid'],
            'annee_catechese_id'      => ['required', 'string', 'exists:annee_catecheses,uuid'],
            'section_id'              => ['nullable', 'string', 'exists:sections,uuid'],
            'niveau_id'               => ['required', 'string', 'exists:niveaux,uuid'],
            'classe_id'               => ['nullable', 'string', 'exists:classes,uuid'],
            'ceb_id'                  => ['nullable', 'string', 'exists:cebs,uuid'],
            'mouvement_id'            => ['nullable', 'string', 'exists:mouvements,uuid'],
            'date_inscription'        => ['nullable', 'date'],
            'frais_inscription_payes' => ['nullable', 'boolean'],
            'observation'             => ['nullable', 'string'],
        ]);

        $catechumene = Catechumene::where('uuid', $validated['catechumene_id'])->firstOrFail();
        $annee = AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->firstOrFail();
        $niveau = Niveau::where('uuid', $validated['niveau_id'])->firstOrFail();

        $validated['paroisse_configuration_id'] = $paroisseId;
        $validated['catechumene_id'] = $catechumene->id;
        $validated['annee_catechese_id'] = $annee->id;
        $validated['niveau_id'] = $niveau->id;
        $validated['section_id'] = !empty($validated['section_id'])
            ? Section::where('uuid', $validated['section_id'])->value('id')
            : $niveau->section_id;
        $validated['date_inscription'] = $validated['date_inscription'] ?? now()->toDateString();
        $validated['statut_inscription'] = 'valide';

        if (!empty($validated['classe_id'])) {
            $classe = Classe::where('uuid', $validated['classe_id'])->firstOrFail();
            $validated['classe_id'] = $classe->id;
        }

        if (!empty($validated['ceb_id'])) {
            $ceb = Ceb::where('uuid', $validated['ceb_id'])->firstOrFail();
            $validated['ceb_id'] = $ceb->id;
        }

        if (!empty($validated['mouvement_id'])) {
            $mouvement = Mouvement::where('uuid', $validated['mouvement_id'])->firstOrFail();
            $validated['mouvement_id'] = $mouvement->id;
        }

        // Générer un code d'inscription unique si absent
        $count = InscriptionAnnuelle::where('paroisse_configuration_id', $paroisseId)
            ->where('annee_catechese_id', $annee->id)
            ->count();
        $prefix = explode('-', $annee->libelle ?? date('Y'))[0] ?? date('Y');
        $validated['code_inscription'] = sprintf("INS-%s-%04d", $prefix, $count + 1);

        $inscription = InscriptionAnnuelle::create($validated);
        $inscription->load(['catechumene', 'anneeCatechese', 'section', 'niveau', 'classe', 'ceb', 'mouvement']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Catéchumène inscrit pour l\'année pastorale avec succès.',
            'data'    => new InscriptionAnnuelleResource($inscription),
        ], 201);
    }

    /**
     * Détails d'une inscription.
     */
    public function show(Request $request, InscriptionAnnuelle $inscription): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $inscription->paroisse_configuration_id);

        $inscription->load(['catechumene', 'anneeCatechese', 'section', 'niveau', 'classe', 'ceb', 'mouvement']);

        return response()->json([
            'status' => 'success',
            'data'   => new InscriptionAnnuelleResource($inscription),
        ]);
    }

    /**
     * Mettre à jour une inscription (ex: réaffecter de classe, section, valider le paiement).
     */
    public function update(Request $request, InscriptionAnnuelle $inscription): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $inscription->paroisse_configuration_id);

        $validated = $request->validate([
            'section_id'              => ['sometimes', 'nullable', 'string', 'exists:sections,uuid'],
            'niveau_id'               => ['sometimes', 'required', 'string', 'exists:niveaux,uuid'],
            'classe_id'               => ['nullable', 'string', 'exists:classes,uuid'],
            'ceb_id'                  => ['nullable', 'string', 'exists:cebs,uuid'],
            'mouvement_id'            => ['nullable', 'string', 'exists:mouvements,uuid'],
            'statut_inscription'      => ['nullable', 'string', 'in:inscrit,valide,en_attente,abandon'],
            'frais_inscription_payes' => ['nullable', 'boolean'],
            'observation'             => ['nullable', 'string'],
        ]);

        if (!empty($validated['niveau_id'])) {
            $niveau = Niveau::where('uuid', $validated['niveau_id'])->firstOrFail();
            $validated['niveau_id'] = $niveau->id;
            if (empty($validated['section_id'])) {
                $validated['section_id'] = $niveau->section_id;
            }
        }

        if (array_key_exists('section_id', $validated)) {
            $validated['section_id'] = $validated['section_id']
                ? Section::where('uuid', $validated['section_id'])->value('id')
                : null;
        }

        if (array_key_exists('classe_id', $validated)) {
            $validated['classe_id'] = $validated['classe_id']
                ? Classe::where('uuid', $validated['classe_id'])->value('id')
                : null;
        }

        if (array_key_exists('ceb_id', $validated)) {
            $validated['ceb_id'] = $validated['ceb_id']
                ? Ceb::where('uuid', $validated['ceb_id'])->value('id')
                : null;
        }

        if (array_key_exists('mouvement_id', $validated)) {
            $validated['mouvement_id'] = $validated['mouvement_id']
                ? Mouvement::where('uuid', $validated['mouvement_id'])->value('id')
                : null;
        }

        $inscription->update($validated);
        $inscription->load(['catechumene', 'anneeCatechese', 'section', 'niveau', 'classe', 'ceb', 'mouvement']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Inscription mise à jour avec succès.',
            'data'    => new InscriptionAnnuelleResource($inscription),
        ]);
    }

    /**
     * Annuler/Supprimer une inscription.
     */
    public function destroy(Request $request, InscriptionAnnuelle $inscription): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $inscription->paroisse_configuration_id);

        $inscription->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Inscription supprimée avec succès.',
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
