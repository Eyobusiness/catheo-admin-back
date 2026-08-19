<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'canal' => $this->canal,
            'destinataire' => $this->destinataire,
            'sujet' => $this->sujet,
            'message' => $this->message,
            'statut_envoi' => $this->statut_envoi,
            'erreur_message' => $this->erreur_message,
            'date_envoi' => $this->date_envoi?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
