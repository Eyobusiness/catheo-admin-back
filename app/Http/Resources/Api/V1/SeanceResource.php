<?php

namespace App\Http\Resources\Api\V1;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SeanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $presences = $this->relationLoaded('presences') ? $this->presences : null;
        $totalPresences = $presences ? $presences->count() : 0;
        $totalPresents = $presences ? $presences->whereIn('statut_presence', ['present', 'retard'])->count() : 0;
        $totalAbsents = $totalPresences - $totalPresents;

        $heureDebut = $this->heure_debut ? (strlen($this->heure_debut) > 5 ? substr($this->heure_debut, 0, 5) : $this->heure_debut) : null;
        $heureFin = $this->heure_fin ? (strlen($this->heure_fin) > 5 ? substr($this->heure_fin, 0, 5) : $this->heure_fin) : null;

        $dureeMinutes = 90;
        if ($heureDebut && $heureFin) {
            try {
                $debut = Carbon::createFromFormat('H:i', $heureDebut);
                $fin = Carbon::createFromFormat('H:i', $heureFin);
                $dureeMinutes = $debut->diffInMinutes($fin);
            } catch (\Throwable $e) {
                $dureeMinutes = 90;
            }
        }

        return [
            'id'                 => $this->uuid,
            'annee_catechese_id' => $this->anneeCatechese?->uuid,
            'classe_id'          => $this->classe?->uuid,
            'titre'              => $this->titre,
            'titre_lecon'        => $this->titre,
            'date_seance'        => $this->date_seance ? (is_string($this->date_seance) ? substr($this->date_seance, 0, 10) : $this->date_seance->format('Y-m-d')) : null,
            'heure_debut'        => $heureDebut,
            'heure_fin'          => $heureFin,
            'duree_minutes'      => $dureeMinutes,
            'description'        => $this->description,
            'statut'             => $this->statut ?? 'planifiee',
            'annee_catechese'    => new AnneeCatecheseResource($this->whenLoaded('anneeCatechese')),
            'classe'             => new ClasseResource($this->whenLoaded('classe')),
            'presences'          => PresenceResource::collection($this->whenLoaded('presences')),
            'total_presences'       => $totalPresences,
            'total_presents'        => $totalPresents,
            'total_absents'         => $totalAbsents,
            'created_at'            => $this->created_at?->toIso8601String(),
            'updated_at'            => $this->updated_at?->toIso8601String(),
        ];
    }
}

