<?php

namespace App\Services\Organisation;

use App\Models\Membre;
use App\Models\Organisation;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MembreService
{
    /**
     * Liste des membres d'une organisation avec filtres et recherche.
     */
    public function list(Organisation $organisation, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Membre::where('organisation_id', $organisation->id)
            ->latest('id');

        if (!empty($filters['statut']) && $filters['statut'] !== 'tous') {
            $query->where('statut', $filters['statut']);
        }

        if (!empty($filters['sexe'])) {
            $query->where('sexe', strtoupper(trim($filters['sexe'])));
        }

        if (!empty($filters['fonction'])) {
            $query->where('fonction', 'like', "%{$filters['fonction']}%");
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenoms', 'like', "%{$search}%")
                  ->orWhere('telephone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('quartier', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Création d'un membre dans l'organisation certifiée.
     */
    public function create(Organisation $organisation, array $data): Membre
    {
        $data['organisation_id'] = $organisation->id;
        $data['statut']          = $data['statut'] ?? Membre::STATUT_ACTIF;

        return Membre::create($data);
    }

    /**
     * Mise à jour d'un membre.
     */
    public function update(Membre $membre, array $data): Membre
    {
        // Empêcher toute tentative de changer d'organisation_id
        unset($data['organisation_id']);

        $membre->update($data);
        return $membre->fresh();
    }

    /**
     * Suppression logique d'un membre.
     */
    public function delete(Membre $membre): bool
    {
        return (bool) $membre->delete();
    }
}
