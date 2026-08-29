<?php

namespace App\Observers;

use App\Models\Catechumene;
use App\Services\NotificationManagerService;

class CatechumeneObserver
{
    public function created(Catechumene $cat): void
    {
        NotificationManagerService::createNotification([
            'paroisse_configuration_id' => $cat->paroisse_configuration_id,
            'type'                      => 'activite',
            'action'                    => 'POST',
            'titre'                     => 'Nouveau catéchumène enregistré',
            'message'                   => "Le catéchumène {$cat->nom_complet} a été ajouté dans le système.",
            'source_type'               => 'Catechumene',
            'source_id'                 => $cat->id,
            'route_url'                 => '/dashboard/catechumenes',
            'icon'                      => 'user-check',
            'couleur'                   => 'success',
        ]);
    }
}
