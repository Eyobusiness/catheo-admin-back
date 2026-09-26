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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class OrganisationDashboardService
{
    public function __construct(
        protected CatheoPopulationService $catheoPopulationService
    ) {}

    /**
     * Calcule et retourne le tableau de bord complet de l'organisation.
     */
    public function getDashboard(Organisation $organisation, bool $fresh = false): array
    {
        $cacheKey = "org_{$organisation->id}_dashboard_data";

        if ($fresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, 120, function () use ($organisation) {
            return [
                'organisation' => [
                    'id'                => $organisation->id,
                    'uuid'              => $organisation->uuid,
                    'code'              => $organisation->code,
                    'nom'               => $organisation->nom,
                    'type_organisation' => $organisation->type_organisation,
                    'statut'            => $organisation->statut,
                ],
                'membres'     => $this->getMembresMetrics($organisation),
                'activites'   => $this->getActivitesMetrics($organisation),
                'pelerinages' => $this->getPelerinagesMetrics($organisation),
                'finances'    => $this->getFinancesMetrics($organisation),
                'catheo'      => $this->getCatheoMetrics($organisation),
            ];
        });
    }

    protected function getMembresMetrics(Organisation $organisation): array
    {
        $total = Membre::where('organisation_id', $organisation->id)->count();
        $actifs = Membre::where('organisation_id', $organisation->id)->where('statut', 'actif')->count();
        $inactifs = $total - $actifs;

        return [
            'total'    => $total,
            'actifs'   => $actifs,
            'inactifs' => $inactifs,
        ];
    }

    protected function getActivitesMetrics(Organisation $organisation): array
    {
        $stats = Activite::where('organisation_id', $organisation->id)
            ->select('statut', DB::raw('count(*) as count'), DB::raw('avg(taux_execution) as avg_taux'))
            ->groupBy('statut')
            ->pluck('count', 'statut')
            ->all();

        $total = array_sum($stats);
        $avgTaux = Activite::where('organisation_id', $organisation->id)->avg('taux_execution') ?? 0;

        return [
            'total'               => $total,
            'brouillon'           => $stats[Activite::STATUT_BROUILLON] ?? 0,
            'planifiees'          => $stats[Activite::STATUT_PLANIFIEE] ?? 0,
            'en_cours'            => $stats[Activite::STATUT_EN_COURS] ?? 0,
            'terminees'           => $stats[Activite::STATUT_TERMINEE] ?? 0,
            'annulees'            => $stats[Activite::STATUT_ANNULEE] ?? 0,
            'taux_moyen_execution'=> round((float) $avgTaux, 2),
        ];
    }

    protected function getPelerinagesMetrics(Organisation $organisation): array
    {
        // MÃ©triques campagnes
        $campagnes = CampagnePelerinage::where('organisation_id', $organisation->id)->get();
        $campagnesTotal = $campagnes->count();
        $campagnesOuvertes = $campagnes->where('statut', CampagnePelerinage::STATUT_OUVERTE)->count();
        $campagnesCloturees = $campagnes->where('statut', CampagnePelerinage::STATUT_CLOTUREE)->count();
        $campagnesAnnulees = $campagnes->where('statut', CampagnePelerinage::STATUT_ANNULEE)->count();

        $campagneIds = $campagnes->pluck('id')->all();

        if (empty($campagneIds)) {
            return [
                'campagnes_total'               => 0,
                'campagnes_ouvertes'            => 0,
                'campagnes_cloturees'           => 0,
                'campagnes_annulees'            => 0,
                'capacite_totale'               => 0,
                'places_occupees'               => 0,
                'places_restantes'              => 0,
                'total_inscrits'                => 0,
                'inscrits_payes'                => 0,
                'inscrits_partiellement_payes'  => 0,
                'inscrits_en_attente'           => 0,
                'inscrits_annules'              => 0,
                'inscrits_presents'             => 0,
                'inscrits_absents'              => 0,
                'montant_attendu'               => 0.00,
                'montant_encaisse'              => 0.00,
                'reste_a_encaisser'             => 0.00,
            ];
        }

        // MÃ©triques inscriptions
        $inscriptions = InscriptionPelerinage::whereIn('campagne_pelerinage_id', $campagneIds)->get();
        $totalInscrits = $inscriptions->count();
        $inscritsPayes = $inscriptions->where('statut_inscription', InscriptionPelerinage::STATUT_PAYEE)->count();
        $inscritsPartiel = $inscriptions->where('statut_inscription', InscriptionPelerinage::STATUT_PARTIELLEMENT_PAYEE)->count();
        $inscritsAttente = $inscriptions->where('statut_inscription', InscriptionPelerinage::STATUT_EN_ATTENTE)->count();
        $inscritsAnnules = $inscriptions->where('statut_inscription', InscriptionPelerinage::STATUT_ANNULEE)->count();
        $inscritsPresents = $inscriptions->where('statut_participation', InscriptionPelerinage::PARTICIPATION_PRESENTE)->count();
        $inscritsAbsents = $inscriptions->where('statut_participation', InscriptionPelerinage::PARTICIPATION_ABSENTE)->count();

        $capaciteTotale = (int) $campagnes->sum('capacite');
        $placesOccupees = $inscriptions->where('statut_inscription', '!=', InscriptionPelerinage::STATUT_ANNULEE)->count();
        $placesRestantes = $capaciteTotale > 0 ? max(0, $capaciteTotale - $placesOccupees) : null;

        $montantAttendu = (float) $inscriptions->where('statut_inscription', '!=', InscriptionPelerinage::STATUT_ANNULEE)->sum('montant');
        $montantEncaisse = (float) PaiementPelerinage::whereHas('inscription', function ($q) use ($campagneIds) {
            $q->whereIn('campagne_pelerinage_id', $campagneIds);
        })->where('statut', PaiementPelerinage::STATUT_VALIDE)->sum('montant');

        $resteAEncaisser = max(0.0, round($montantAttendu - $montantEncaisse, 2));

        return [
            'campagnes_total'               => $campagnesTotal,
            'campagnes_ouvertes'            => $campagnesOuvertes,
            'campagnes_cloturees'           => $campagnesCloturees,
            'campagnes_annulees'            => $campagnesAnnulees,
            'capacite_totale'               => $capaciteTotale,
            'places_occupees'               => $placesOccupees,
            'places_restantes'              => $placesRestantes,
            'total_inscrits'                => $totalInscrits,
            'inscrits_payes'                => $inscritsPayes,
            'inscrits_partiellement_payes'  => $inscritsPartiel,
            'inscrits_en_attente'           => $inscritsAttente,
            'inscrits_annules'              => $inscritsAnnules,
            'inscrits_presents'             => $inscritsPresents,
            'inscrits_absents'              => $inscritsAbsents,
            'montant_attendu'               => $montantAttendu,
            'montant_encaisse'              => $montantEncaisse,
            'reste_a_encaisser'             => $resteAEncaisser,
        ];
    }

    protected function getFinancesMetrics(Organisation $organisation): array
    {
        $entrees = (float) OperationOrganisation::where('organisation_id', $organisation->id)
            ->where('statut', OperationOrganisation::STATUT_VALIDE)
            ->where('type_operation', OperationOrganisation::TYPE_ENTREE)
            ->sum('montant');

        $sorties = (float) OperationOrganisation::where('organisation_id', $organisation->id)
            ->where('statut', OperationOrganisation::STATUT_VALIDE)
            ->where('type_operation', OperationOrganisation::TYPE_SORTIE)
            ->sum('montant');

        return [
            'total_entrees' => $entrees,
            'total_sorties' => $sorties,
            'solde_caisse'  => round($entrees - $sorties, 2),
        ];
    }

    public function getCatheoMetrics(Organisation $organisation): array
    {
        $paroisseId = (int) $organisation->paroisse_configuration_id;
        if (!$paroisseId) {
            return ['catheo_connecte' => false];
        }

        $anneeCourante = AnneeCatechese::getAnneeCourante($paroisseId);
        if (!$anneeCourante) {
            return [
                'catheo_connecte' => false,
                'message'         => 'Aucune annÃ©e catÃ©chÃ©tique active sur la paroisse.',
            ];
        }

        $targetCodes = $this->catheoPopulationService->getTargetSectionCodes($organisation->type_organisation);
        if (empty($targetCodes)) {
            return [
                'catheo_connecte' => false,
                'message'         => 'Aucune correspondance de section pour ce type d\'organisation.',
            ];
        }

        $typeOrg = strtoupper($organisation->type_organisation);

        // RequÃªte de base sur les inscriptions annuelles de la paroisse sur l'annÃ©e active
        $baseQuery = InscriptionAnnuelle::where('paroisse_configuration_id', $paroisseId)
            ->where('annee_catechese_id', $anneeCourante->id)
            ->whereHas('section', function ($q) use ($targetCodes, $typeOrg) {
                $q->where(function ($sub) use ($targetCodes, $typeOrg) {
                    $sub->whereIn('code', $targetCodes);
                    if ($typeOrg === Organisation::TYPE_OPPE) {
                        $sub->orWhere('code', 'like', 'SEC-ENF%')
                            ->orWhere('nom', 'like', '%enfant%')
                            ->orWhere('nom', 'like', '%primaire%')
                            ->orWhere('nom', 'like', '%college%')
                            ->orWhere('nom', 'like', '%collÃ¨ge%');
                    } elseif ($typeOrg === Organisation::TYPE_OPPJ) {
                        $sub->orWhere('code', 'like', 'SEC-JEUN%')
                            ->orWhere('nom', 'like', '%jeune%');
                    } elseif ($typeOrg === Organisation::TYPE_OPPA) {
                        $sub->orWhere('code', 'like', 'SEC-ADULT%')
                            ->orWhere('nom', 'like', '%adulte%');
                    }
                });
            });

        $total = (clone $baseQuery)->count();

        // RÃ©partition par niveau
        $parNiveau = (clone $baseQuery)
            ->with('niveau')
            ->select('niveau_id', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('niveau_id')
            ->get()
            ->map(function ($item) {
                return [
                    'niveau_id' => $item->niveau_id,
                    'niveau'    => $item->niveau?->nom ?? 'Inconnu',
                    'total'     => $item->total,
                ];
            });

        // RÃ©partition par classe
        $parClasse = (clone $baseQuery)
            ->with('classe')
            ->select('classe_id', \Illuminate\Support\Facades\DB::raw('count(*) as total'))
            ->groupBy('classe_id')
            ->get()
            ->map(function ($item) {
                return [
                    'classe_id' => $item->classe_id,
                    'classe'    => $item->classe?->nom ?? 'Non assignÃ©',
                    'total'     => $item->total,
                ];
            });

        $result = [
            'catheo_connecte'      => true,
            'annee_catechese'      => $anneeCourante->libelle,
            'total_population'     => $total,
            'repartition_niveaux'  => $parNiveau,
            'repartition_classes'  => $parClasse,
        ];

        // Ventilation spÃ©cifique selon le produit
        if ($typeOrg === Organisation::TYPE_OPPE) {
            $totalPrimaire = InscriptionAnnuelle::where('paroisse_configuration_id', $paroisseId)
                ->where('annee_catechese_id', $anneeCourante->id)
                ->whereHas('section', function ($q) {
                    $q->whereIn('code', CatheoPopulationService::CODES_ENFANTS_PRIMAIRE)
                      ->orWhere('code', 'like', 'SEC-ENF%PRI%')
                      ->orWhere('nom', 'like', '%primaire%');
                })->count();

            $totalCollege = InscriptionAnnuelle::where('paroisse_configuration_id', $paroisseId)
                ->where('annee_catechese_id', $anneeCourante->id)
                ->whereHas('section', function ($q) {
                    $q->whereIn('code', CatheoPopulationService::CODES_ENFANTS_COLLEGE)
                      ->orWhere('code', 'like', 'SEC-ENF%COL%')
                      ->orWhere('nom', 'like', '%collÃ¨ge%')
                      ->orWhere('nom', 'like', '%college%');
                })->count();

            $result['total_primaire'] = $totalPrimaire;
            $result['total_college']  = $totalCollege;
        } elseif ($typeOrg === Organisation::TYPE_OPPJ) {
            $result['total_jeunes'] = $total;
        } elseif ($typeOrg === Organisation::TYPE_OPPA) {
            $result['total_adultes'] = $total;
        }

        return $result;
    }

}
