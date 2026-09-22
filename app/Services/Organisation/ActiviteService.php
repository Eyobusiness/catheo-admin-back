<?php

namespace App\Services\Organisation;

use App\Models\Activite;
use App\Models\Membre;
use App\Models\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use InvalidArgumentException;

class ActiviteService
{
    /**
     * Liste des activités d'une organisation.
     */
    public function list(Organisation $organisation, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Activite::with('responsable')
            ->where('organisation_id', $organisation->id)
            ->latest('date_debut');

        if (!empty($filters['statut']) && $filters['statut'] !== 'tous') {
            $query->where('statut', $filters['statut']);
        }

        if (!empty($filters['type_activite'])) {
            $query->where('type_activite', $filters['type_activite']);
        }

        if (!empty($filters['date_debut'])) {
            $query->whereDate('date_debut', '>=', $filters['date_debut']);
        }

        if (!empty($filters['date_fin'])) {
            $query->whereDate('date_fin', '<=', $filters['date_fin']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('titre', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('lieu', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Création d'une activité.
     */
    public function create(Organisation $organisation, array $data): Activite
    {
        $this->verifyResponsableBelongsToOrganisation($organisation->id, $data['responsable_id'] ?? null);

        $data['organisation_id'] = $organisation->id;
        $data['statut']          = $data['statut'] ?? Activite::STATUT_BROUILLON;
        $data['taux_execution']  = $data['taux_execution'] ?? 0.00;

        return Activite::create($data);
    }

    /**
     * Mise à jour d'une activité.
     */
    public function update(Activite $activite, array $data): Activite
    {
        // Empêcher tout détournement de l'organisation
        unset($data['organisation_id']);

        if (array_key_exists('responsable_id', $data)) {
            $this->verifyResponsableBelongsToOrganisation($activite->organisation_id, $data['responsable_id']);
        }

        $activite->update($data);
        return $activite->fresh(['responsable']);
    }

    /**
     * Suppression logique d'une activité.
     */
    public function delete(Activite $activite): bool
    {
        return (bool) $activite->delete();
    }

    /**
     * Vérifie que le responsable est bien membre de la même organisation.
     */
    protected function verifyResponsableBelongsToOrganisation(int $organisationId, ?int $responsableId): void
    {
        if (empty($responsableId)) {
            return;
        }

        $isMember = Membre::where('id', $responsableId)
            ->where('organisation_id', $organisationId)
            ->exists();

        if (!$isMember) {
            throw new InvalidArgumentException("Le responsable désigné doit obligatoirement être un membre de la même organisation.");
        }
    }
}
