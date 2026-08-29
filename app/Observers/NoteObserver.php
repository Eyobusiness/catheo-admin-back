<?php

namespace App\Observers;

use App\Models\Note;
use App\Services\NotificationManagerService;

class NoteObserver
{
    public function created(Note $note): void
    {
        $evaluationNom = $note->evaluation ? $note->evaluation->nom : 'une évaluation';
        $nom = $note->catechumene ? $note->catechumene->nom_complet : 'un élève';
        $noteVal = $note->note_obtenue !== null ? " : {$note->note_obtenue}/20" : '';

        NotificationManagerService::createNotification([
            'paroisse_configuration_id' => $note->paroisse_configuration_id,
            'type'                      => 'activite',
            'action'                    => 'NOTE',
            'titre'                     => 'Note enregistrée',
            'message'                   => "Note enregistrée pour {$nom} dans {$evaluationNom}{$noteVal}.",
            'source_type'               => 'Note',
            'source_id'                 => $note->id,
            'route_url'                 => '/dashboard/evaluations',
            'icon'                      => 'book-open',
            'couleur'                   => 'info',
            'donnees_additionnelles'    => [
                'note_obtenue'  => $note->note_obtenue,
                'evaluation_id' => $note->evaluation_id,
            ],
        ]);
    }
}
