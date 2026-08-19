<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CalculerBulletinRequest;
use App\Http\Resources\Api\V1\BulletinTrimestrielResource;
use App\Models\BulletinTrimestriel;
use App\Models\Classe;
use App\Models\Evaluation;
use App\Models\InscriptionAnnuelle;
use App\Models\ModuleTrimestriel;
use App\Models\Note;
use App\Models\Presence;
use App\Models\Seance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BulletinTrimestrielController extends Controller
{
    /**
     * Liste des bulletins trimestriels.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $query = BulletinTrimestriel::with(['inscriptionAnnuelle.catechumene', 'moduleTrimestriel'])
            ->where('paroisse_configuration_id', $paroisseId);

        if ($request->filled('classe_id')) {
            $classeId = Classe::where('uuid', $request->classe_id)->value('id');
            if ($classeId) {
                $query->whereHas('inscriptionAnnuelle', function ($q) use ($classeId) {
                    $q->where('classe_id', $classeId);
                });
            }
        }

        $bulletins = $query->orderBy('rang')->get();

        return response()->json([
            'status' => 'success',
            'data' => BulletinTrimestrielResource::collection($bulletins),
        ]);
    }

    /**
     * Calculer automatiquement les bulletins trimestriels d'une classe.
     */
    public function calculer(CalculerBulletinRequest $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;
        $validated = $request->validated();

        $classe = Classe::where('uuid', $validated['classe_id'])->firstOrFail();
        $module = ModuleTrimestriel::where('uuid', $validated['module_trimestriel_id'])->firstOrFail();

        $this->authorizeTenant($paroisseId, $classe->paroisse_configuration_id);

        $inscriptions = InscriptionAnnuelle::where('classe_id', $classe->id)
            ->where('paroisse_configuration_id', $paroisseId)
            ->get();

        $evaluations = Evaluation::where('classe_id', $classe->id)
            ->where('module_trimestriel_id', $module->id)
            ->get();

        $evalIds = $evaluations->pluck('id');
        $totalSeancesCount = Seance::where('classe_id', $classe->id)
            ->where('module_trimestriel_id', $module->id)
            ->count();

        $bulletinsData = [];

        DB::transaction(function () use ($paroisseId, $classe, $module, $inscriptions, $evaluations, $evalIds, $totalSeancesCount, &$bulletinsData) {
            foreach ($inscriptions as $insc) {
                $catId = $insc->catechumene_id;

                // 1. Calculer la moyenne ponderée des notes obtenues sur le trimestre
                $notes = Note::whereIn('evaluation_id', $evalIds)
                    ->where('catechumene_id', $catId)
                    ->get();

                $sommePonderee = 0.0;
                $sommeCoeff = 0.0;

                foreach ($notes as $note) {
                    $eval = $evaluations->firstWhere('id', $note->evaluation_id);
                    if ($eval) {
                        $sommePonderee += ($note->note_obtenue / $eval->note_max * 20.0) * $eval->coefficient;
                        $sommeCoeff += $eval->coefficient;
                    }
                }

                $moyenne = $sommeCoeff > 0 ? round($sommePonderee / $sommeCoeff, 2) : 0.00;

                // 2. Décompte des absences
                $absencesCount = Presence::where('catechumene_id', $catId)
                    ->whereIn('seance_id', Seance::where('classe_id', $classe->id)->where('module_trimestriel_id', $module->id)->pluck('id'))
                    ->where('statut_presence', 'absent')
                    ->count();

                // 3. Appreciation globale
                $appreciation = 'Passable';
                if ($moyenne >= 16) $appreciation = 'Très Bien';
                elseif ($moyenne >= 14) $appreciation = 'Bien';
                elseif ($moyenne >= 12) $appreciation = 'Assez Bien';
                elseif ($moyenne < 10) $appreciation = 'Insuffisant';

                $bulletin = BulletinTrimestriel::updateOrCreate(
                    [
                        'paroisse_configuration_id' => $paroisseId,
                        'inscription_annuelle_id' => $insc->id,
                        'module_trimestriel_id' => $module->id,
                    ],
                    [
                        'moyenne_trimestrielle' => $moyenne,
                        'total_absences' => $absencesCount,
                        'total_seances' => $totalSeancesCount,
                        'appreciation_generale' => $appreciation,
                    ]
                );

                $bulletinsData[] = $bulletin;
            }

            // 4. Attribution des rangs de la classe
            $collection = BulletinTrimestriel::whereIn('inscription_annuelle_id', $inscriptions->pluck('id'))
                ->where('module_trimestriel_id', $module->id)
                ->orderByDesc('moyenne_trimestrielle')
                ->get();

            $rang = 1;
            foreach ($collection as $b) {
                $b->update(['rang' => $rang++]);
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Bulletins trimestriels calculés et classés avec succès.',
            'count' => count($bulletinsData),
        ]);
    }

    /**
     * Obtenir le bulletin d'un élève.
     */
    public function show(Request $request, BulletinTrimestriel $bulletinTrimestriel): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $bulletinTrimestriel->paroisse_configuration_id);

        $bulletinTrimestriel->load(['inscriptionAnnuelle.catechumene', 'moduleTrimestriel']);

        return response()->json([
            'status' => 'success',
            'data' => new BulletinTrimestrielResource($bulletinTrimestriel),
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
