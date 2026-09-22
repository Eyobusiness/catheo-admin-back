<?php

namespace App\Services\Organisation;

use App\Models\Activite;
use App\Models\AnneeCatechese;
use App\Models\CampagnePelerinage;
use App\Models\InscriptionAnnuelle;
use App\Models\InscriptionPelerinage;
use App\Models\Membre;
use App\Models\OperationOrganisation;
use App\Models\Organisation;
use App\Models\PaiementPelerinage;
use Illuminate\Support\Facades\DB;

class OrganisationRapportService
{
    public function __construct(
        protected CatheoPopulationService $catheoPopulationService
    ) {}

    /**
     * Génère le rapport annuel consolidé d'une organisation pour une année donnée.
     */
    public function getRapportAnnuel(Organisation $organisation, int $annee): array
    {
        $dateDebut = "{$annee}-01-01";
        $dateFin = "{$annee}-12-31";

        // A. Membres
        $membresTotal = Membre::where('organisation_id', $organisation->id)->count();
        $membresActifs = Membre::where('organisation_id', $organisation->id)->where('statut', 'actif')->count();
        $nouvellesAdhesions = Membre::where('organisation_id', $organisation->id)
            ->whereYear('date_entree', $annee)
            ->count();

        // B. Activités de l'année
        $activites = Activite::where('organisation_id', $organisation->id)
            ->where(function ($q) use ($annee, $dateDebut, $dateFin) {
                $q->whereYear('date_debut', $annee)
                  ->orWhereBetween('date_debut', [$dateDebut, $dateFin]);
            })
            ->get();

        $nbActivites = $activites->count();
        $activitesParStatut = $activites->groupBy('statut')->map->count()->all();
        $avgTauxActivites = $activites->avg('taux_execution') ?? 0;

        // C. Pèlerinages de l'année
        $campagnes = CampagnePelerinage::where('organisation_id', $organisation->id)
            ->whereYear('date_depart', $annee)
            ->get();

        $nbCampagnes = $campagnes->count();
        $campagneIds = $campagnes->pluck('id')->all();

        $inscriptions = !empty($campagneIds)
            ? InscriptionPelerinage::whereIn('campagne_pelerinage_id', $campagneIds)->get()
            : collect();

        $totalInscrits = $inscriptions->count();
        $presents = $inscriptions->where('statut_participation', InscriptionPelerinage::PARTICIPATION_PRESENTE)->count();
        $absents = $inscriptions->where('statut_participation', InscriptionPelerinage::PARTICIPATION_ABSENTE)->count();
        $tauxPresence = ($presents + $absents) > 0 ? round(($presents / ($presents + $absents)) * 100, 2) : 0.0;

        // D. Finances de l'année
        $entrees = (float) OperationOrganisation::where('organisation_id', $organisation->id)
            ->where('statut', OperationOrganisation::STATUT_VALIDE)
            ->where('type_operation', OperationOrganisation::TYPE_ENTREE)
            ->whereYear('date_operation', $annee)
            ->sum('montant');

        $sorties = (float) OperationOrganisation::where('organisation_id', $organisation->id)
            ->where('statut', OperationOrganisation::STATUT_VALIDE)
            ->where('type_operation', OperationOrganisation::TYPE_SORTIE)
            ->whereYear('date_operation', $annee)
            ->sum('montant');

        // E. Population CATHEO si connectée
        $catheoData = $this->getCatheoRapportData($organisation, $annee);

        return [
            'annee_exercice' => $annee,
            'organisation'   => [
                'id'                => $organisation->id,
                'nom'               => $organisation->nom,
                'code'              => $organisation->code,
                'type_organisation' => $organisation->type_organisation,
            ],
            'membres' => [
                'total'               => $membresTotal,
                'actifs'              => $membresActifs,
                'inactifs'            => $membresTotal - $membresActifs,
                'nouvelles_adhesions' => $nouvellesAdhesions,
            ],
            'activites' => [
                'total'                => $nbActivites,
                'repartition_statut'   => $activitesParStatut,
                'taux_moyen_execution' => round((float) $avgTauxActivites, 2),
            ],
            'pelerinages' => [
                'campagnes'           => $nbCampagnes,
                'total_participants'  => $totalInscrits,
                'presents'            => $presents,
                'absents'             => $absents,
                'taux_presence'       => $tauxPresence,
            ],
            'finances' => [
                'total_entrees' => $entrees,
                'total_sorties' => $sorties,
                'solde_net'     => round($entrees - $sorties, 2),
            ],
            'catheo' => $catheoData,
        ];
    }

    protected function getCatheoRapportData(Organisation $organisation, int $annee): array
    {
        $paroisseId = (int) $organisation->paroisse_configuration_id;
        if (!$paroisseId) {
            return ['catheo_connecte' => false];
        }

        $targetCodes = $this->catheoPopulationService->getTargetSectionCodes($organisation->type_organisation);
        if (empty($targetCodes)) {
            return ['catheo_connecte' => false];
        }

        // Chercher l'année pastorale correspondant à cette année (ou année courante)
        $anneeCatechese = AnneeCatechese::where('paroisse_configuration_id', $paroisseId)
            ->where(function ($q) use ($annee) {
                $q->whereYear('date_debut', $annee)
                  ->orWhereYear('date_fin', $annee);
            })
            ->latest('id')
            ->first() ?? AnneeCatechese::getAnneeCourante($paroisseId);

        if (!$anneeCatechese) {
            return ['catheo_connecte' => false];
        }

        $totalPopulation = InscriptionAnnuelle::where('paroisse_configuration_id', $paroisseId)
            ->where('annee_catechese_id', $anneeCatechese->id)
            ->whereHas('section', function ($q) use ($targetCodes) {
                $q->whereIn('code', $targetCodes);
            })
            ->count();

        return [
            'catheo_connecte'  => true,
            'annee_catechese'  => $anneeCatechese->libelle,
            'total_population' => $totalPopulation,
            'sections'         => $targetCodes,
        ];
    }
}
