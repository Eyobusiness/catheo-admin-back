<?php

namespace App\Services\Organisation;

use App\Models\Activite;
use App\Models\CampagnePelerinage;
use App\Models\InscriptionPelerinage;
use App\Models\Membre;
use App\Models\OperationOrganisation;
use App\Models\Organisation;
use App\Models\PaiementPelerinage;
use Illuminate\Support\Facades\DB;

class OrganisationStatistiqueService
{
    /**
     * Statistiques détaillées sur les membres de l'organisation.
     */
    public function getStatistiquesMembres(Organisation $organisation, array $filters = []): array
    {
        $query = Membre::where('organisation_id', $organisation->id);

        if (!empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        if (!empty($filters['sexe'])) {
            $query->where('sexe', strtoupper($filters['sexe']));
        }

        if (!empty($filters['fonction'])) {
            $query->where('fonction', $filters['fonction']);
        }

        if (!empty($filters['date_debut'])) {
            $query->whereDate('date_entree', '>=', $filters['date_debut']);
        }

        if (!empty($filters['date_fin'])) {
            $query->whereDate('date_entree', '<=', $filters['date_fin']);
        }

        $total = (clone $query)->count();
        $actifs = (clone $query)->where('statut', 'actif')->count();
        $inactifs = $total - $actifs;

        $repartitionSexe = (clone $query)
            ->select('sexe', DB::raw('count(*) as count'))
            ->groupBy('sexe')
            ->pluck('count', 'sexe')
            ->all();

        $repartitionFonction = (clone $query)
            ->whereNotNull('fonction')
            ->select('fonction', DB::raw('count(*) as count'))
            ->groupBy('fonction')
            ->pluck('count', 'fonction')
            ->all();

        // Évolution des adhésions par mois (derniers 12 mois ou période)
        $dateExpr = $this->getDateFormatExpression('date_entree');
        $evolutionAdhesions = (clone $query)
            ->whereNotNull('date_entree')
            ->select(DB::raw("{$dateExpr} as periode"), DB::raw('count(*) as count'))
            ->groupBy('periode')
            ->orderBy('periode', 'asc')
            ->pluck('count', 'periode')
            ->all();

        return [
            'total'                => $total,
            'actifs'               => $actifs,
            'inactifs'             => $inactifs,
            'repartition_sexe'     => [
                'M' => $repartitionSexe['M'] ?? 0,
                'F' => $repartitionSexe['F'] ?? 0,
            ],
            'repartition_fonction' => $repartitionFonction,
            'evolution_adhesions'  => $evolutionAdhesions,
        ];
    }

    /**
     * Statistiques détaillées sur les activités de l'organisation.
     */
    public function getStatistiquesActivites(Organisation $organisation, array $filters = []): array
    {
        $query = Activite::where('organisation_id', $organisation->id);

        if (!empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        if (!empty($filters['type_activite'])) {
            $query->where('type_activite', $filters['type_activite']);
        }

        if (!empty($filters['date_debut'])) {
            $query->whereDate('date_debut', '>=', $filters['date_debut']);
        }

        if (!empty($filters['date_fin'])) {
            $query->whereDate('date_fin', '<=', $filters['date_fin']);
        }

        $total = (clone $query)->count();
        $avgTaux = (clone $query)->avg('taux_execution') ?? 0;

        $repartitionStatut = (clone $query)
            ->select('statut', DB::raw('count(*) as count'))
            ->groupBy('statut')
            ->pluck('count', 'statut')
            ->all();

        $repartitionType = (clone $query)
            ->whereNotNull('type_activite')
            ->select('type_activite', DB::raw('count(*) as count'))
            ->groupBy('type_activite')
            ->pluck('count', 'type_activite')
            ->all();

        $dateExpr = $this->getDateFormatExpression('date_debut');
        $evolutionMensuelle = (clone $query)
            ->select(DB::raw("{$dateExpr} as periode"), DB::raw('count(*) as count'))
            ->groupBy('periode')
            ->orderBy('periode', 'asc')
            ->pluck('count', 'periode')
            ->all();

        return [
            'total'                => $total,
            'taux_moyen_execution' => round((float) $avgTaux, 2),
            'repartition_statut'   => $repartitionStatut,
            'repartition_type'     => $repartitionType,
            'activites_par_periode'=> $evolutionMensuelle,
        ];
    }

    /**
     * Statistiques globales et ciblées sur les pèlerinages.
     */
    public function getStatistiquesPelerinages(Organisation $organisation, array $filters = []): array
    {
        $campagnesQuery = CampagnePelerinage::where('organisation_id', $organisation->id);

        if (!empty($filters['campagne_id'])) {
            $campagnesQuery->where('id', $filters['campagne_id']);
        }

        if (!empty($filters['date_debut'])) {
            $campagnesQuery->whereDate('date_depart', '>=', $filters['date_debut']);
        }

        if (!empty($filters['date_fin'])) {
            $campagnesQuery->whereDate('date_fin', '<=', $filters['date_fin']);
        }

        $campagnes = $campagnesQuery->get();
        $campagneIds = $campagnes->pluck('id')->all();

        $totalCampagnes = $campagnes->count();
        $repartitionStatutCampagnes = $campagnes->groupBy('statut')->map->count()->all();
        $capaciteTotale = (int) $campagnes->sum('capacite');

        if (empty($campagneIds)) {
            return [
                'campagnes' => [
                    'total'              => 0,
                    'repartition_statut' => [],
                    'capacite_totale'    => 0,
                    'places_occupees'    => 0,
                    'places_restantes'   => 0,
                    'taux_occupation'    => 0.0,
                ],
                'inscriptions' => [
                    'total'                => 0,
                    'catheo'               => 0,
                    'externes'             => 0,
                    'payes'                => 0,
                    'partiellement_payes'  => 0,
                    'en_attente'           => 0,
                    'annules'              => 0,
                    'presents'             => 0,
                    'absents'              => 0,
                    'taux_presence'        => 0.0,
                ],
                'finances' => [
                    'montant_attendu'  => 0.00,
                    'montant_encaisse' => 0.00,
                    'solde_restant'    => 0.00,
                    'taux_recouvrement'=> 0.0,
                ],
            ];
        }

        $inscriptionsQuery = InscriptionPelerinage::whereIn('campagne_pelerinage_id', $campagneIds);

        if (!empty($filters['statut_inscription'])) {
            $inscriptionsQuery->where('statut_inscription', $filters['statut_inscription']);
        }

        if (!empty($filters['type_participant'])) {
            $inscriptionsQuery->where('type_participant', strtoupper($filters['type_participant']));
        }

        $inscriptions = $inscriptionsQuery->get();

        $totalInscrits = $inscriptions->count();
        $placesOccupees = $inscriptions->where('statut_inscription', '!=', InscriptionPelerinage::STATUT_ANNULEE)->count();
        $placesRestantes = $capaciteTotale > 0 ? max(0, $capaciteTotale - $placesOccupees) : null;
        $tauxOccupation = $capaciteTotale > 0 ? round(($placesOccupees / $capaciteTotale) * 100, 2) : null;

        $inscritsCatheo = $inscriptions->where('type_participant', InscriptionPelerinage::TYPE_CATECHUMENE)->count();
        $inscritsExternes = $inscriptions->where('type_participant', InscriptionPelerinage::TYPE_EXTERNE)->count();

        $payes = $inscriptions->where('statut_inscription', InscriptionPelerinage::STATUT_PAYEE)->count();
        $partiel = $inscriptions->where('statut_inscription', InscriptionPelerinage::STATUT_PARTIELLEMENT_PAYEE)->count();
        $attente = $inscriptions->where('statut_inscription', InscriptionPelerinage::STATUT_EN_ATTENTE)->count();
        $annules = $inscriptions->where('statut_inscription', InscriptionPelerinage::STATUT_ANNULEE)->count();

        $presents = $inscriptions->where('statut_participation', InscriptionPelerinage::PARTICIPATION_PRESENTE)->count();
        $absents = $inscriptions->where('statut_participation', InscriptionPelerinage::PARTICIPATION_ABSENTE)->count();
        $prevus = $inscriptions->where('statut_participation', InscriptionPelerinage::PARTICIPATION_PREVUE)->count();
        $tauxPresence = ($presents + $absents) > 0 ? round(($presents / ($presents + $absents)) * 100, 2) : 0.0;

        $montantAttendu = (float) $inscriptions->where('statut_inscription', '!=', InscriptionPelerinage::STATUT_ANNULEE)->sum('montant');
        $montantEncaisse = (float) PaiementPelerinage::whereHas('inscription', function ($q) use ($campagneIds) {
            $q->whereIn('campagne_pelerinage_id', $campagneIds);
        })->where('statut', PaiementPelerinage::STATUT_VALIDE)->sum('montant');

        $soldeRestant = max(0.0, round($montantAttendu - $montantEncaisse, 2));
        $tauxRecouvrement = $montantAttendu > 0 ? round(($montantEncaisse / $montantAttendu) * 100, 2) : 0.0;

        return [
            'campagnes' => [
                'total'              => $totalCampagnes,
                'repartition_statut' => $repartitionStatutCampagnes,
                'capacite_totale'    => $capaciteTotale,
                'places_occupees'    => $placesOccupees,
                'places_restantes'   => $placesRestantes,
                'taux_occupation'    => $tauxOccupation,
            ],
            'inscriptions' => [
                'total'               => $totalInscrits,
                'catheo'              => $inscritsCatheo,
                'externes'            => $inscritsExternes,
                'payes'               => $payes,
                'partiellement_payes' => $partiel,
                'en_attente'          => $attente,
                'annules'             => $annules,
                'presents'            => $presents,
                'absents'             => $absents,
                'prevus'              => $prevus,
                'taux_presence'       => $tauxPresence,
            ],
            'finances' => [
                'montant_attendu'   => $montantAttendu,
                'montant_encaisse'  => $montantEncaisse,
                'solde_restant'     => $soldeRestant,
                'taux_recouvrement' => $tauxRecouvrement,
            ],
        ];
    }

    /**
     * Statistiques financières de l'organisation.
     */
    public function getStatistiquesFinances(Organisation $organisation, array $filters = []): array
    {
        $query = OperationOrganisation::where('organisation_id', $organisation->id)
            ->where('statut', OperationOrganisation::STATUT_VALIDE);

        if (!empty($filters['date_debut'])) {
            $query->whereDate('date_operation', '>=', $filters['date_debut']);
        }

        if (!empty($filters['date_fin'])) {
            $query->whereDate('date_operation', '<=', $filters['date_fin']);
        }

        if (!empty($filters['campagne_id'])) {
            $query->where('campagne_pelerinage_id', $filters['campagne_id']);
        }

        $totalEntrees = (float) (clone $query)->where('type_operation', OperationOrganisation::TYPE_ENTREE)->sum('montant');
        $totalSorties = (float) (clone $query)->where('type_operation', OperationOrganisation::TYPE_SORTIE)->sum('montant');
        $solde = round($totalEntrees - $totalSorties, 2);

        // Recettes pèlerinages
        $recettesPelerinages = (float) (clone $query)
            ->where('type_operation', OperationOrganisation::TYPE_ENTREE)
            ->whereNotNull('campagne_pelerinage_id')
            ->sum('montant');

        $autresRecettes = max(0.0, round($totalEntrees - $recettesPelerinages, 2));

        // Évolution mensuelle (entrées vs sorties)
        $dateExpr = $this->getDateFormatExpression('date_operation');
        $evolutionEntrees = (clone $query)
            ->where('type_operation', OperationOrganisation::TYPE_ENTREE)
            ->select(DB::raw("{$dateExpr} as periode"), DB::raw('sum(montant) as total'))
            ->groupBy('periode')
            ->pluck('total', 'periode')
            ->all();

        $evolutionSorties = (clone $query)
            ->where('type_operation', OperationOrganisation::TYPE_SORTIE)
            ->select(DB::raw("{$dateExpr} as periode"), DB::raw('sum(montant) as total'))
            ->groupBy('periode')
            ->pluck('total', 'periode')
            ->all();

        $periodes = array_unique(array_merge(array_keys($evolutionEntrees), array_keys($evolutionSorties)));
        sort($periodes);

        $evolution = [];
        foreach ($periodes as $p) {
            $e = (float) ($evolutionEntrees[$p] ?? 0);
            $s = (float) ($evolutionSorties[$p] ?? 0);
            $evolution[] = [
                'periode' => $p,
                'entrees' => $e,
                'sorties' => $s,
                'solde'   => round($e - $s, 2),
            ];
        }

        // Répartition par mode de règlement
        $repartitionModes = (clone $query)
            ->whereNotNull('mode_reglement')
            ->select('mode_reglement', DB::raw('sum(montant) as total'), DB::raw('count(*) as count'))
            ->groupBy('mode_reglement')
            ->get()
            ->map(function ($item) {
                return [
                    'mode'  => $item->mode_reglement,
                    'total' => (float) $item->total,
                    'count' => (int) $item->count,
                ];
            });

        return [
            'total_entrees'        => $totalEntrees,
            'total_sorties'        => $totalSorties,
            'solde'                => $solde,
            'recettes_pelerinages' => $recettesPelerinages,
            'autres_recettes'      => $autresRecettes,
            'evolution_mensuelle'  => $evolution,
            'repartition_modes'    => $repartitionModes,
        ];
    }

    /**
     * Expression SQL de formatage de date compatible MySQL et SQLite.
     */
    protected function getDateFormatExpression(string $column): string
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            return "strftime('%Y-%m', {$column})";
        }
        return "DATE_FORMAT({$column}, '%Y-%m')";
    }
}
