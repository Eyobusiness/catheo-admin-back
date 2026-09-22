<?php

namespace App\Services\SuperAdmin;

use App\Models\Produit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class SuperAdminProduitService
{
    /**
     * Liste des produits avec filtres éventuels.
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection
    {
        $query = Produit::withCount(['formules', 'organisations']);

        if (!empty($filters['statut']) && $filters['statut'] !== 'tous') {
            $query->where('statut', $filters['statut']);
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return !empty($filters['all']) ? $query->get() : $query->paginate($perPage);
    }

    /**
     * Création d'un produit.
     */
    public function create(array $data): Produit
    {
        $code = strtoupper(trim($data['code']));

        if (Produit::where('code', $code)->exists()) {
            throw new InvalidArgumentException("Un produit avec le code [{$code}] existe déjà.");
        }

        $data['code'] = $code;
        return Produit::create($data);
    }

    /**
     * Mise à jour d'un produit.
     */
    public function update(Produit $produit, array $data): Produit
    {
        if (isset($data['code'])) {
            $code = strtoupper(trim($data['code']));
            if ($code !== $produit->code && Produit::where('code', $code)->where('id', '!=', $produit->id)->exists()) {
                throw new InvalidArgumentException("Un produit avec le code [{$code}] existe déjà.");
            }
            $data['code'] = $code;
        }

        $produit->update($data);
        return $produit->fresh();
    }

    /**
     * Bascule du statut actif / inactif.
     */
    public function toggleStatus(Produit $produit): Produit
    {
        $nouveauStatut = ($produit->statut === 'actif') ? 'inactif' : 'actif';
        $produit->update(['statut' => $nouveauStatut]);
        return $produit;
    }
}
