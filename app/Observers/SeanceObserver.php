<?php

namespace App\Observers;

use App\Models\Seance;
use App\Services\NotificationManagerService;

class SeanceObserver
{
    public function created(Seance $seance): void
    {
        $classeNom = $seance->classe ? $seance->classe->nom : 'Classe';
        NotificationManagerService::createNotification([
            'paroisse_configuration_id' => $seance->paroisse_configuration_id,
            'type'                      => 'activite',
            'action'                    => 'POST',
            'titre'                     => 'Nouvelle séance programmée',
            'message'                   => "Séance \"{$seance->titre}\" programmée le {$seance->date_seance} pour la {$classeNom}.",
            'source_type'               => 'Seance',
            'source_id'                 => $seance->id,
            'route_url'                 => '/dashboard/presences',
            'icon'                      => 'calendar',
            'couleur'                   => 'primary',
        ]);
    }
}
