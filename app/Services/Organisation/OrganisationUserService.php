<?php

namespace App\Services\Organisation;

use App\Models\Organisation;
use App\Models\Profil;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;

class OrganisationUserService
{
    /**
     * Liste des utilisateurs d'une organisation.
     */
    public function list(Organisation $organisation, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = User::with('profil')
            ->where('organisation_id', $organisation->id)
            ->latest('id');

        if (!empty($filters['statut']) && $filters['statut'] !== 'tous') {
            $query->where('statut', $filters['statut']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('telephone', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Création d'un utilisateur au sein d'une organisation certifiée.
     */
    public function create(Organisation $organisation, array $data): User
    {
        // Forcer le rattachement strict et infalsifiable
        $data['organisation_id']           = $organisation->id;
        $data['paroisse_configuration_id'] = $organisation->paroisse_configuration_id;
        $data['statut']                    = $data['statut'] ?? 'actif';
        $data['user_type']                 = $data['user_type'] ?? 'utilisateur';

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            $data['password'] = Hash::make('Organisation123!');
        }

        // Si profil_id fourni, s'assurer qu'il existe
        if (!empty($data['profil_id'])) {
            $profil = Profil::find($data['profil_id']);
            if (!$profil) {
                throw new InvalidArgumentException("Le profil sélectionné est introuvable.");
            }
        }

        return User::create($data);
    }

    /**
     * Mise à jour d'un utilisateur d'organisation.
     */
    public function update(User $user, array $data): User
    {
        // Protection anti-usurpation multi-tenant
        unset($data['organisation_id'], $data['paroisse_configuration_id']);

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);
        return $user->fresh('profil');
    }

    /**
     * Activer / désactiver le compte utilisateur.
     */
    public function toggleStatus(User $user): User
    {
        $nouveauStatut = ($user->statut === 'actif') ? 'inactif' : 'actif';
        $user->update(['statut' => $nouveauStatut]);

        return $user->fresh('profil');
    }
}
