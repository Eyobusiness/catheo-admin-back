<?php

namespace App\Observers;

use App\Models\Preinscription;
use App\Services\NotificationManagerService;

class PreinscriptionObserver
{
    public function created(Preinscription $preinscription): void
    {
        $nomComplet = trim("{$preinscription->prenoms} {$preinscription->nom}");
        NotificationManagerService::createNotification([
            'paroisse_configuration_id' => $preinscription->paroisse_configuration_id,
            'type'                      => 'alerte',
            'action'                    => 'POST',
            'titre'                     => 'Nouvelle préinscription reçue',
            'message'                   => "Dossier de préinscription déposé pour {$nomComplet}.",
            'source_type'               => 'Preinscription',
            'source_id'                 => $preinscription->id,
            'route_url'                 => '/dashboard/preinscriptions',
            'icon'                      => 'user-plus',
            'couleur'                   => 'info',
        ]);
    }
}
