<?php

namespace App\Observers;

use App\Models\Classe;
use App\Services\NotificationManagerService;

class ClasseObserver
{
    public function created(Classe $classe): void
    {
        NotificationManagerService::createNotification([
            'paroisse_configuration_id' => $classe->paroisse_configuration_id,
            'type'                      => 'activite',
            'action'                    => 'POST',
            'titre'                     => 'Nouvelle classe créée',
            'message'                   => "La classe \"{$classe->nom}\" a été créée.",
            'source_type'               => 'Classe',
            'source_id'                 => $classe->id,
            'route_url'                 => '/dashboard/classes',
            'icon'                      => 'grid',
            'couleur'                   => 'primary',
        ]);
    }

    public function deleted(Classe $classe): void
    {
        NotificationManagerService::createNotification([
            'paroisse_configuration_id' => $classe->paroisse_configuration_id,
            'type'                      => 'activite',
            'action'                    => 'DELETE',
            'titre'                     => 'Classe supprimée',
            'message'                   => "La classe \"{$classe->nom}\" a été supprimée.",
            'source_type'               => 'Classe',
            'source_id'                 => $classe->id,
            'route_url'                 => '/dashboard/classes',
            'icon'                      => 'trash-2',
            'couleur'                   => 'danger',
        ]);
    }
}
