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
        return [
            'annee_active' => $this->resource['annee_active'] ?? null,
            'summary'      => [
                'catechumenes_actifs'        => (int) ($this->resource['summary']['catechumenes_actifs'] ?? 0),
                'sections'                   => (int) ($this->resource['summary']['sections'] ?? 0),
                'classes'                    => (int) ($this->resource['summary']['classes'] ?? 0),
                'animateurs'                 => (int) ($this->resource['summary']['animateurs'] ?? 0),
                'preinscriptions_en_attente' => (int) ($this->resource['summary']['preinscriptions_en_attente'] ?? 0),
            ],
            'effectifs'    => [
                'par_section' => $this->resource['effectifs']['par_section'] ?? [],
                'par_niveau'  => $this->resource['effectifs']['par_niveau'] ?? [],
                'par_classe'  => $this->resource['effectifs']['par_classe'] ?? [],
            ],
            'sacrements'   => [
                'bapteme'            => (int) ($this->resource['sacrements']['bapteme'] ?? 0),
                'premiere_communion' => (int) ($this->resource['sacrements']['premiere_communion'] ?? 0),
                'confirmation'       => (int) ($this->resource['sacrements']['confirmation'] ?? 0),
            ],
            'alertes'      => [
                'nouvelles_preinscriptions' => $this->resource['alertes']['nouvelles_preinscriptions'] ?? [
                    'type'    => 'preinscriptions',
                    'count'   => 0,
                    'message' => 'Aucune préinscription en attente.',
                ],
                'appels_non_effectues'      => $this->resource['alertes']['appels_non_effectues'] ?? [
                    'type'    => 'appels_non_effectues',
                    'count'   => 0,
                    'message' => 'Tous les appels des séances passées ont été effectués.',
                ],
            ],

            // Rétrocompatibilité frontend si nécessaire
            'kpis'                   => $this->resource['kpis'] ?? null,
            'repartition_sections'   => $this->resource['repartition_sections'] ?? null,
            'repartition_niveaux'    => $this->resource['repartition_niveaux'] ?? null,
            'effectifs_classes'      => $this->resource['effectifs_classes'] ?? null,
            'preparation_sacrements' => $this->resource['preparation_sacrements'] ?? null,
        ];
    }
}
