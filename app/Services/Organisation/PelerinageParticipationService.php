<?php

namespace App\Services\Organisation;

use App\Models\CampagnePelerinage;
use App\Models\InscriptionPelerinage;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class PelerinageParticipationService
{
    /**
     * Met à jour le statut de présence et le suivi kit/badge d'un participant.
     */
    public function updateParticipation(InscriptionPelerinage $inscription, array $data): InscriptionPelerinage
    {
        if (isset($data['statut_participation'])) {
            $inscription->statut_participation = $data['statut_participation'];
        }

        if (isset($data['badge_imprime'])) {
            $inscription->badge_imprime = (bool) $data['badge_imprime'];
        }

        if (isset($data['kit_remis'])) {
            $inscription->kit_remis = (bool) $data['kit_remis'];
            if ($inscription->kit_remis && empty($inscription->date_remise_kit)) {
                $inscription->date_remise_kit = now();
            } elseif (!$inscription->kit_remis) {
                $inscription->date_remise_kit = null;
            }
        }

        if (isset($data['observation'])) {
            $inscription->observation = $data['observation'];
        }

        $inscription->save();

        return $inscription->fresh();
    }

    /**
     * Pointage en masse des participants d'une campagne.
     */
    public function batchUpdate(CampagnePelerinage $campagne, array $items): array
    {
        return DB::transaction(function () use ($campagne, $items) {
            $updated = 0;
            $errors = 0;

            foreach ($items as $item) {
                $inscription = InscriptionPelerinage::where('campagne_pelerinage_id', $campagne->id)
                    ->where('id', $item['id'])
                    ->first();

                if (!$inscription) {
                    $errors++;
                    continue;
                }

                $this->updateParticipation($inscription, $item);
                $updated++;
            }

            return [
                'campagne_id' => $campagne->id,
                'total_traite' => count($items),
                'total_mis_a_jour' => $updated,
                'total_erreurs' => $errors,
            ];
        });
    }
}
