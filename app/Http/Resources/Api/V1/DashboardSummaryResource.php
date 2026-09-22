<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DashboardSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $summary = [
            'catechumenes_actifs'        => (int) ($this->resource['summary']['catechumenes_actifs'] ?? $this->resource['kpis']['total_catechumenes'] ?? 0),
            'sections'                   => (int) ($this->resource['summary']['sections'] ?? $this->resource['kpis']['total_sections'] ?? 0),
            'classes'                    => (int) ($this->resource['summary']['classes'] ?? $this->resource['kpis']['total_classes'] ?? 0),
            'animateurs'                 => (int) ($this->resource['summary']['animateurs'] ?? $this->resource['kpis']['total_animateurs'] ?? 0),
            'preinscriptions_en_attente' => (int) ($this->resource['summary']['preinscriptions_en_attente'] ?? $this->resource['kpis']['preinscriptions_en_attente'] ?? 0),
        ];

        $kpis = [
            'total_catechumenes'        => $summary['catechumenes_actifs'],
            'catechumenes_actifs'       => $summary['catechumenes_actifs'],
            'total_sections'            => $summary['sections'],
            'total_classes'             => $summary['classes'],
            'total_animateurs'          => $summary['animateurs'],
            'preinscriptions_en_attente'=> $summary['preinscriptions_en_attente'],
        ];

        $sacrements = [
            'bapteme'            => (int) ($this->resource['sacrements']['bapteme'] ?? $this->resource['preparation_sacrements']['bapteme_candidats'] ?? 0),
            'premiere_communion' => (int) ($this->resource['sacrements']['premiere_communion'] ?? $this->resource['preparation_sacrements']['premiere_communion_candidats'] ?? 0),
            'confirmation'       => (int) ($this->resource['sacrements']['confirmation'] ?? $this->resource['preparation_sacrements']['confirmation_candidats'] ?? 0),
        ];

        $preparationSacrements = [
            'bapteme_candidats'            => $sacrements['bapteme'],
            'premiere_communion_candidats' => $sacrements['premiere_communion'],
            'confirmation_candidats'       => $sacrements['confirmation'],
        ];

        $finances = $this->resource['situation_financiere'] ?? [
            'montant_attendu'   => 0,
            'montant_encaisse'  => 0,
            'reste_a_payer'     => 0,
            'taux_recouvrement' => 100,
        ];

        $alertesData = $this->resource['alertes'] ?? [];

        return [
            'annee_active'         => $this->resource['annee_active'] ?? null,
            'summary'              => $summary,
            'kpis'                 => $kpis,
            'effectifs'            => [
                'par_section' => $this->resource['effectifs']['par_section'] ?? $this->resource['repartition_sections'] ?? [],
                'par_niveau'  => $this->resource['effectifs']['par_niveau'] ?? $this->resource['repartition_niveaux'] ?? [],
                'par_classe'  => $this->resource['effectifs']['par_classe'] ?? $this->resource['effectifs_classes'] ?? [],
            ],
            'repartition_sections' => $this->resource['repartition_sections'] ?? ($this->resource['effectifs']['par_section'] ?? []),
            'repartition_niveaux'  => $this->resource['repartition_niveaux'] ?? ($this->resource['effectifs']['par_niveau'] ?? []),
            'effectifs_classes'    => $this->resource['effectifs_classes'] ?? ($this->resource['effectifs']['par_classe'] ?? []),
            'sacrements'           => $sacrements,
            'preparation_sacrements' => $preparationSacrements,
            'situation_financiere' => [
                'montant_attendu'   => (float) ($finances['montant_attendu'] ?? 0),
                'montant_encaisse'  => (float) ($finances['montant_encaisse'] ?? 0),
                'reste_a_payer'     => (float) ($finances['reste_a_payer'] ?? 0),
                'taux_recouvrement' => (float) ($finances['taux_recouvrement'] ?? 100),
            ],
            'alertes'              => [
                'preinscriptions_non_validees' => (int) ($alertesData['preinscriptions_non_validees'] ?? $summary['preinscriptions_en_attente']),
                'paiements_en_retard'          => (int) ($alertesData['paiements_en_retard'] ?? 0),
                'catechumenes_non_affectes'    => (int) ($alertesData['catechumenes_non_affectes'] ?? 0),
                'documents_manquants'          => (int) ($alertesData['documents_manquants'] ?? 0),
                'nouvelles_preinscriptions'    => $alertesData['nouvelles_preinscriptions'] ?? [
                    'type'    => 'preinscriptions',
                    'count'   => $summary['preinscriptions_en_attente'],
                    'message' => $summary['preinscriptions_en_attente'] > 0 ? "{$summary['preinscriptions_en_attente']} dossier(s) en attente." : 'Aucune préinscription en attente.',
                ],
                'appels_non_effectues'         => $alertesData['appels_non_effectues'] ?? [
                    'type'    => 'appels_non_effectues',
                    'count'   => 0,
                    'message' => 'Tous les appels des séances passées ont été effectués.',
                ],
            ],
            'activites_recentes'   => $this->resource['activites_recentes'] ?? [],
        ];
    }
}
