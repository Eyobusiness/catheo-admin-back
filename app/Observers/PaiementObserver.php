<?php

namespace App\Observers;

use App\Models\Paiement;
use App\Services\NotificationManagerService;

class PaiementObserver
{
    public function created(Paiement $paiement): void
    {
        $montant = number_format($paiement->montant_total, 0, ',', ' ') . ' FCFA';
        $nom = $paiement->catechumene ? $paiement->catechumene->nom_complet : 'un catéchumène';
        $recu = $paiement->numero_recu ? " (Reçu n° {$paiement->numero_recu})" : '';

        NotificationManagerService::createNotification([
            'paroisse_configuration_id' => $paiement->paroisse_configuration_id,
            'type'                      => 'activite',
            'action'                    => 'PAIEMENT',
            'titre'                     => 'Paiement encaissé',
            'message'                   => "Un paiement de {$montant} a été enregistré pour {$nom}{$recu}.",
            'source_type'               => 'Paiement',
            'source_id'                 => $paiement->id,
            'route_url'                 => '/dashboard/finances/paiements',
            'icon'                      => 'credit-card',
            'couleur'                   => 'success',
            'donnees_additionnelles'    => [
                'montant'     => $paiement->montant_total,
                'numero_recu' => $paiement->numero_recu,
                'mode'        => $paiement->mode_paiement,
            ],
        ]);
    }

    public function deleted(Paiement $paiement): void
    {
        $montant = number_format($paiement->montant_total, 0, ',', ' ') . ' FCFA';
        NotificationManagerService::createNotification([
            'paroisse_configuration_id' => $paiement->paroisse_configuration_id,
            'type'                      => 'activite',
            'action'                    => 'DELETE',
            'titre'                     => 'Paiement annulé',
            'message'                   => "Un paiement de {$montant} (Reçu {$paiement->numero_recu}) a été annulé.",
            'source_type'               => 'Paiement',
            'source_id'                 => $paiement->id,
            'route_url'                 => '/dashboard/finances/paiements',
            'icon'                      => 'trash-2',
            'couleur'                   => 'danger',
        ]);
    }
}
