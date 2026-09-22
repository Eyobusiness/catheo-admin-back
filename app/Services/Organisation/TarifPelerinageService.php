<?php

namespace App\Services\Organisation;

use App\Models\CampagnePelerinage;
use App\Models\TarifPelerinage;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class TarifPelerinageService
{
    /**
     * Liste tous les tarifs d'une campagne.
     */
    public function list(CampagnePelerinage $campagne): Collection
    {
        return $campagne->tarifs()->orderBy('montant', 'asc')->get();
    }

    /**
     * Récupère un tarif appartenant strictement à la campagne spécifiée.
     */
    public function find(CampagnePelerinage $campagne, int|string $idOrUuid): TarifPelerinage
    {
        $tarif = $campagne->tarifs()
            ->where(function ($q) use ($idOrUuid) {
                if (is_numeric($idOrUuid)) {
                    $q->where('id', $idOrUuid);
                } else {
                    $q->where('uuid', $idOrUuid);
                }
            })
            ->first();

        if (!$tarif) {
            throw new NotFoundHttpException("Tarif introuvable ou n'appartenant pas à cette campagne de pèlerinage.");
        }

        return $tarif;
    }

    /**
     * Crée un nouveau tarif pour la campagne.
     */
    public function create(CampagnePelerinage $campagne, array $data): TarifPelerinage
    {
        $data['campagne_pelerinage_id'] = $campagne->id;
        $data['statut'] = $data['statut'] ?? TarifPelerinage::STATUT_ACTIF;

        if (empty($data['code'])) {
            $count = $campagne->tarifs()->count();
            $data['code'] = sprintf('TAR-%02d', $count + 1);
        }

        return TarifPelerinage::create($data);
    }

    /**
     * Met à jour un tarif existant.
     */
    public function update(TarifPelerinage $tarif, array $data): TarifPelerinage
    {
        unset($data['campagne_pelerinage_id']);

        $tarif->update($data);

        return $tarif->fresh();
    }

    /**
     * Supprime logiquement un tarif (si non utilisé par des inscriptions).
     */
    public function delete(TarifPelerinage $tarif): bool
    {
        if ($tarif->inscriptions()->exists()) {
            throw new UnprocessableEntityHttpException("Impossible de supprimer un tarif déjà utilisé par des inscriptions.");
        }

        return (bool) $tarif->delete();
    }
}
