<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BilanAnnuelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'annee'           => $this['annee'] ?? null,
            'synthese'        => $this['synthese'] ?? [],
            'effectifs'       => $this['effectifs'] ?? [],
            'evolution'       => $this['evolution'] ?? null,
            'assiduite'       => $this['assiduite'] ?? [],
            'progression'     => $this['progression'] ?? [],
            'sacrements'      => $this['sacrements'] ?? [],
            'inscriptions'    => $this['inscriptions'] ?? [],
            'mutations'       => $this['mutations'] ?? [],
            'animateurs'      => $this['animateurs'] ?? [],
            'alertes'         => $this['alertes'] ?? [],
            'synthese_finale' => $this['synthese_finale'] ?? [],
        ];
    }
}
