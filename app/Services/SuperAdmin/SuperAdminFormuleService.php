<?php

namespace App\Services\SuperAdmin;

use App\Models\Formule;
use App\Models\Produit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class SuperAdminFormuleService
{
    /**
     * Liste des formules avec filtres éventuels.
     */
    public function list(array $filters = [], int $perPage = 15): LengthAwarePaginator|Collection
    {
        $query = Formule::with('produit')->withCount('abonnements')->orderBy('ordre', 'asc');

        if (!empty($filters['produit_id'])) {
            $prodInput = $filters['produit_id'];
            $produit = is_numeric($prodInput) ? Produit::find($prodInput) : Produit::where('uuid', $prodInput)->orWhere('code', $prodInput)->first();
            if ($produit) {
                $query->where('produit_id', $produit->id);
            }
        }

        if (!empty($filters['statut']) && $filters['statut'] !== 'tous') {
            $query->where('statut', $filters['statut']);
        }

        if (isset($filters['est_gratuite']) && $filters['est_gratuite'] !== '') {
            $query->where('est_gratuite', filter_var($filters['est_gratuite'], FILTER_VALIDATE_BOOLEAN));
        }

        return !empty($filters['all']) ? $query->get() : $query->paginate($perPage);
    }

    /**
     * Création d'une formule tarifaire.
     */
    public function create(array $data): Formule
    {
        $code = strtoupper(trim($data['code']));
        $produitId = (int) $data['produit_id'];

        if (Formule::where('produit_id', $produitId)->where('code', $code)->exists()) {
            throw new InvalidArgumentException("Une formule avec le code [{$code}] existe déjà pour ce produit.");
        }

        $estGratuite = !empty($data['est_gratuite']);
        $montant = $estGratuite ? 0.00 : (float) ($data['montant'] ?? 0.00);

        $data['code']         = $code;
        $data['est_gratuite'] = $estGratuite;
        $data['montant']      = $montant;

        return Formule::create($data);
    }

    /**
     * Mise à jour d'une formule.
     */
    public function update(Formule $formule, array $data): Formule
    {
        if (isset($data['code'])) {
            $code = strtoupper(trim($data['code']));
            $prodId = $data['produit_id'] ?? $formule->produit_id;
            if ($code !== $formule->code && Formule::where('produit_id', $prodId)->where('code', $code)->where('id', '!=', $formule->id)->exists()) {
                throw new InvalidArgumentException("Une formule avec le code [{$code}] existe déjà pour ce produit.");
            }
            $data['code'] = $code;
        }

        if (isset($data['est_gratuite'])) {
            $estGratuite = filter_var($data['est_gratuite'], FILTER_VALIDATE_BOOLEAN);
            $data['est_gratuite'] = $estGratuite;
            if ($estGratuite) {
                $data['montant'] = 0.00;
            }
        }

        $formule->update($data);
        return $formule->fresh();
    }

    /**
     * Bascule du statut actif / inactif.
     */
    public function toggleStatus(Formule $formule): Formule
    {
        $nouveauStatut = ($formule->statut === 'actif') ? 'inactif' : 'actif';
        $formule->update(['statut' => $nouveauStatut]);
        return $formule;
    }

    /**
     * Suppression logique d'une formule.
     */
    public function delete(Formule $formule): bool
    {
        return $formule->delete();
    }
}
