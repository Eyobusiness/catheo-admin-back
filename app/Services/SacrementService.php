<?php

namespace App\Services;

use App\Models\AnneeCatechese;
use App\Models\Catechumene;
use App\Models\CatechumenSacrement;
use App\Models\Classe;
use App\Models\Niveau;
use App\Models\Sacrement;
use App\Models\Section;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SacrementService
{
    /**
     * Liste des types de sacrements gérés par l'Église.
     */
    public function getSacrements(): Collection
    {
        return Sacrement::where('statut', 'actif')->orderBy('ordre')->get();
    }

    /**
     * Obtenir un type de sacrement par son ID ou UUID.
     */
    public function getSacrementById(string|int $id): ?Sacrement
    {
        return is_numeric($id)
            ? Sacrement::find($id)
            : Sacrement::where('uuid', $id)->orWhere('code', strtoupper($id))->first();
    }

    /**
     * Filtrage dynamique des catéchumènes et de leur parcours sacramentel.
     * ZERO hardcoding : sections, niveaux, classes et sacrements sont tous résolus par IDs.
     */
    public function getCatechumens(int $paroisseId, array $filters = []): LengthAwarePaginator
    {
        $query = Catechumene::where('paroisse_configuration_id', $paroisseId)
            ->with([
                'inscriptionsAnnuelles' => function ($q) {
                    $q->where('statut_inscription', '!=', 'annulee')
                      ->with(['section', 'niveau', 'classe', 'anneeCatechese'])
                      ->latest('id');
                },
                'parcoursSacrements.sacrement',
                'parcoursSacrements.validator',
            ]);

        // 1. Filtre par Année Pastorale (via l'inscription annuelle)
        if (!empty($filters['annee_catechese_id'])) {
            $anneeId = $this->resolveId(AnneeCatechese::class, $filters['annee_catechese_id'], $paroisseId);
            if ($anneeId) {
                $query->whereHas('inscriptionsAnnuelles', fn($q) => $q->where('annee_catechese_id', $anneeId));
            }
        }

        // 2. Filtre par Section (via l'inscription annuelle active)
        if (!empty($filters['section_id'])) {
            $sectionId = $this->resolveId(Section::class, $filters['section_id'], $paroisseId);
            if ($sectionId) {
                $query->whereHas('inscriptionsAnnuelles', fn($q) => $q->where('section_id', $sectionId));
            }
        }

        // 3. Filtre par Niveau (via l'inscription annuelle active)
        if (!empty($filters['niveau_id'])) {
            $niveauId = $this->resolveId(Niveau::class, $filters['niveau_id'], $paroisseId);
            if ($niveauId) {
                $query->whereHas('inscriptionsAnnuelles', fn($q) => $q->where('niveau_id', $niveauId));
            }
        }

        // 4. Filtre par Classe (via l'inscription annuelle active)
        if (!empty($filters['classe_id'])) {
            $classeId = $this->resolveId(Classe::class, $filters['classe_id'], $paroisseId);
            if ($classeId) {
                $query->whereHas('inscriptionsAnnuelles', fn($q) => $q->where('classe_id', $classeId));
            }
        }

        // 5. Filtre par Sacrement et/ou Statut de Sacrement
        if (!empty($filters['sacrement_id']) || !empty($filters['statut'])) {
            $sacrementId = !empty($filters['sacrement_id']) ? $this->resolveSacrementId($filters['sacrement_id']) : null;
            $statut = !empty($filters['statut']) ? strtolower($filters['statut']) : null;

            $query->whereHas('parcoursSacrements', function (Builder $sq) use ($sacrementId, $statut) {
                if ($sacrementId) {
                    $sq->where('sacrement_id', $sacrementId);
                }
                if ($statut && in_array($statut, ['preparation', 'valide', 'en_preparation'])) {
                    $normStatut = ($statut === 'en_preparation') ? 'preparation' : $statut;
                    $sq->where('statut', $normStatut);
                }
            });
        }

        // 6. Recherche textuelle (Nom, Prénoms, Matricule, Téléphone)
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenoms', 'like', "%{$search}%")
                  ->orWhere('matricule', 'like', "%{$search}%")
                  ->orWhere('telephone', 'like', "%{$search}%");
            });
        }

        $perPage = (int) ($filters['per_page'] ?? 15);
        return $query->latest('id')->paginate($perPage);
    }

    /**
     * Récupère le parcours sacramentel complet d'un catéchumène avec les 3 sacrements (Baptême, Communion, Confirmation).
     */
    public function getCatechumenParcours(int $paroisseId, Catechumene $catechumene): array
    {
        if ($catechumene->paroisse_configuration_id !== $paroisseId) {
            abort(403, 'Accès refusé : ce catéchumène n\'appartient pas à votre paroisse.');
        }

        $allSacrements = $this->getSacrements();
        $parcoursExisting = CatechumenSacrement::where('catechumene_id', $catechumene->id)
            ->where('paroisse_configuration_id', $paroisseId)
            ->with(['sacrement', 'validator', 'anneeCatechese'])
            ->get()
            ->keyBy('sacrement_id');

        $parcoursList = [];
        foreach ($allSacrements as $sacr) {
            /** @var CatechumenSacrement|null $parcours */
            $parcours = $parcoursExisting->get($sacr->id);

            // Vérification de repli sur les données historiques du catéchumène si pas encore de ligne pivot
            $dateHistorique = null;
            $lieuHistorique = null;
            $isValideHistorique = false;

            if ($sacr->code === 'BAPTEME' && $catechumene->est_baptise) {
                $isValideHistorique = true;
                $dateHistorique = $catechumene->date_bapteme?->toDateString();
                $lieuHistorique = $catechumene->paroisse_bapteme ?? $catechumene->lieu_bapteme;
            } elseif ($sacr->code === 'PREMIERE_COMMUNION' && $catechumene->date_premiere_communion) {
                $isValideHistorique = true;
                $dateHistorique = $catechumene->date_premiere_communion?->toDateString();
                $lieuHistorique = $catechumene->paroisse_premiere_communion;
            } elseif ($sacr->code === 'CONFIRMATION' && $catechumene->date_confirmation) {
                $isValideHistorique = true;
                $dateHistorique = $catechumene->date_confirmation?->toDateString();
                $lieuHistorique = $catechumene->paroisse_confirmation;
            }

            $statutFinal = $parcours ? $parcours->statut : ($isValideHistorique ? 'valide' : 'non_recu');

            $parcoursList[] = [
                'id'               => $parcours?->uuid,
                'sacrement_id'     => $sacr->uuid,
                'sacrement_code'   => $sacr->code,
                'sacrement_nom'    => $sacr->nom,
                'ordre'            => $sacr->ordre,
                'statut'           => $statutFinal, // non_recu, preparation, valide
                'date_sacrement'   => $parcours?->date_sacrement?->toDateString() ?? $dateHistorique,
                'lieu'             => $parcours?->lieu ?? $lieuHistorique,
                'paroisse_nom'     => $parcours?->paroisse_nom ?? $lieuHistorique,
                'celebrant'        => $parcours?->celebrant ?? ($sacr->code === 'CONFIRMATION' ? $catechumene->ministre_confirmation : null),
                'numero_registre'  => $parcours?->numero_registre ?? ($sacr->code === 'BAPTEME' ? $catechumene->num_carnet_bapteme : null),
                'num_carnet'       => $parcours?->num_carnet ?? ($sacr->code === 'BAPTEME' ? $catechumene->num_carnet_bapteme : null),
                'observations'     => $parcours?->observations,
                'annee_pastorale'  => $parcours?->anneeCatechese?->libelle,
                'validated_at'     => $parcours?->validated_at?->toIso8601String(),
                'validated_by'     => $parcours?->validator ? [
                    'id'   => $parcours->validator->uuid,
                    'name' => $parcours->validator->name,
                ] : null,
            ];
        }

        return $parcoursList;
    }

    /**
     * Enregistrer un sacrement en préparation ou validé pour un catéchumène.
     */
    public function storeParcoursSacrement(int $paroisseId, Catechumene $catechumene, array $data, ?User $user = null): CatechumenSacrement
    {
        if ($catechumene->paroisse_configuration_id !== $paroisseId) {
            abort(403, 'Accès refusé : ce catéchumène n\'appartient pas à votre paroisse.');
        }

        $sacrement = $this->getSacrementById($data['sacrement_id']);
        if (!$sacrement) {
            throw ValidationException::withMessages(['sacrement_id' => 'Le sacrement sélectionné est invalide.']);
        }

        // Vérifier l'absence de doublon actif
        $existing = CatechumenSacrement::where('catechumene_id', $catechumene->id)
            ->where('sacrement_id', $sacrement->id)
            ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'sacrement_id' => "Un enregistrement pour le sacrement '{$sacrement->nom}' existe déjà pour ce catéchumène."
            ]);
        }

        $anneeId = null;
        if (!empty($data['annee_catechese_id'])) {
            $anneeId = $this->resolveId(AnneeCatechese::class, $data['annee_catechese_id'], $paroisseId);
        } else {
            $anneeActive = AnneeCatechese::getAnneeCourante($paroisseId);
            $anneeId = $anneeActive?->id;
        }

        $statut = strtolower($data['statut'] ?? 'preparation');
        if (!in_array($statut, ['preparation', 'valide'])) {
            $statut = 'preparation';
        }

        return DB::transaction(function () use ($paroisseId, $catechumene, $sacrement, $anneeId, $statut, $data, $user) {
            $isValidated = ($statut === 'valide');

            $parcours = CatechumenSacrement::create([
                'paroisse_configuration_id' => $paroisseId,
                'catechumene_id'            => $catechumene->id,
                'sacrement_id'               => $sacrement->id,
                'annee_catechese_id'         => $anneeId,
                'statut'                     => $statut,
                'date_sacrement'             => $data['date_sacrement'] ?? ($isValidated ? now()->toDateString() : null),
                'lieu'                       => $data['lieu'] ?? null,
                'paroisse_nom'               => $data['paroisse_nom'] ?? ($data['lieu'] ?? null),
                'celebrant'                  => $data['celebrant'] ?? null,
                'numero_registre'            => $data['numero_registre'] ?? null,
                'num_carnet'                 => $data['num_carnet'] ?? null,
                'observations'               => $data['observations'] ?? null,
                'validated_at'               => $isValidated ? now() : null,
                'validated_by'               => $isValidated ? $user?->id : null,
            ]);

            // Synchronisation atomique avec le dossier du catéchumène si validé
            if ($isValidated) {
                $this->syncCatechumeneDossier($catechumene, $sacrement->code, $parcours);
            }

            return $parcours->load(['sacrement', 'validator', 'anneeCatechese']);
        });
    }

    /**
     * Mettre à jour ou Valider un parcours sacramentel.
     */
    public function updateParcoursSacrement(
        int $paroisseId,
        Catechumene $catechumene,
        string|int $sacrementIdentifier,
        array $data,
        ?User $user = null
    ): CatechumenSacrement {
        if ($catechumene->paroisse_configuration_id !== $paroisseId) {
            abort(403, 'Accès refusé : ce catéchumène n\'appartient pas à votre paroisse.');
        }

        // Trouver le record soit par UUID du parcours, soit par ID/Code de sacrement
        $parcours = CatechumenSacrement::where('catechumene_id', $catechumene->id)
            ->where(function ($q) use ($sacrementIdentifier) {
                $q->where('uuid', $sacrementIdentifier)
                  ->orWhere('id', is_numeric($sacrementIdentifier) ? $sacrementIdentifier : 0)
                  ->orWhereHas('sacrement', function ($sq) use ($sacrementIdentifier) {
                      $sq->where('uuid', $sacrementIdentifier)
                         ->orWhere('code', strtoupper($sacrementIdentifier))
                         ->orWhere('id', is_numeric($sacrementIdentifier) ? $sacrementIdentifier : 0);
                  });
            })
            ->first();

        // Si le catéchumène n'avait pas encore de ligne en DB, la créer
        if (!$parcours) {
            $sacrement = $this->getSacrementById($sacrementIdentifier);
            if (!$sacrement) {
                abort(404, 'Parcours sacramentel ou type de sacrement introuvable.');
            }
            $data['sacrement_id'] = $sacrement->uuid;
            return $this->storeParcoursSacrement($paroisseId, $catechumene, $data, $user);
        }

        return DB::transaction(function () use ($catechumene, $parcours, $data, $user) {
            $statut = isset($data['statut']) ? strtolower($data['statut']) : $parcours->statut;
            $wasValidated = ($parcours->statut === 'valide');
            $nowValidated = ($statut === 'valide');

            $parcours->statut = $statut;

            if (array_key_exists('date_sacrement', $data)) {
                $parcours->date_sacrement = $data['date_sacrement'];
            }
            if (array_key_exists('lieu', $data)) {
                $parcours->lieu = $data['lieu'];
                $parcours->paroisse_nom = $data['lieu'];
            }
            if (array_key_exists('celebrant', $data)) {
                $parcours->celebrant = $data['celebrant'];
            }
            if (array_key_exists('numero_registre', $data)) {
                $parcours->numero_registre = $data['numero_registre'];
            }
            if (array_key_exists('num_carnet', $data)) {
                $parcours->num_carnet = $data['num_carnet'];
            }
            if (array_key_exists('observations', $data)) {
                $parcours->observations = $data['observations'];
            }

            if ($nowValidated && !$wasValidated) {
                $parcours->validated_at = now();
                $parcours->validated_by = $user?->id;
                if (!$parcours->date_sacrement) {
                    $parcours->date_sacrement = now()->toDateString();
                }
            } elseif (!$nowValidated && $wasValidated) {
                $parcours->validated_at = null;
                $parcours->validated_by = null;
            }

            $parcours->save();

            // Synchronisation avec le dossier catéchumène
            $this->syncCatechumeneDossier($catechumene, $parcours->sacrement->code, $parcours, $nowValidated);

            return $parcours->load(['sacrement', 'validator', 'anneeCatechese']);
        });
    }

    /**
     * Supprimer un enregistrement de parcours sacramentel (Soft Delete).
     */
    public function deleteParcoursSacrement(int $paroisseId, Catechumene $catechumene, string|int $sacrementIdentifier): bool
    {
        if ($catechumene->paroisse_configuration_id !== $paroisseId) {
            abort(403, 'Accès refusé.');
        }

        $parcours = CatechumenSacrement::where('catechumene_id', $catechumene->id)
            ->where(function ($q) use ($sacrementIdentifier) {
                $q->where('uuid', $sacrementIdentifier)
                  ->orWhere('id', is_numeric($sacrementIdentifier) ? $sacrementIdentifier : 0)
                  ->orWhereHas('sacrement', function ($sq) use ($sacrementIdentifier) {
                      $sq->where('uuid', $sacrementIdentifier)
                         ->orWhere('code', strtoupper($sacrementIdentifier))
                         ->orWhere('id', is_numeric($sacrementIdentifier) ? $sacrementIdentifier : 0);
                  });
            })
            ->firstOrFail();

        return DB::transaction(function () use ($catechumene, $parcours) {
            $code = $parcours->sacrement->code;
            $parcours->delete();

            // Revert dossier catéchumène si nécessaire
            $this->syncCatechumeneDossier($catechumene, $code, null, false);
            return true;
        });
    }

    /**
     * Synchronisation atomique du dossier du catéchumène lors de la validation/invalidation d'un sacrement.
     */
    protected function syncCatechumeneDossier(Catechumene $cat, string $sacrementCode, ?CatechumenSacrement $parcours, bool $isValid = true): void
    {
        switch ($sacrementCode) {
            case 'BAPTEME':
                $cat->est_baptise = $isValid;
                if ($isValid && $parcours) {
                    $cat->date_bapteme = $parcours->date_sacrement;
                    $cat->paroisse_bapteme = $parcours->lieu ?? $parcours->paroisse_nom;
                    $cat->lieu_bapteme = $parcours->lieu;
                    $cat->num_carnet_bapteme = $parcours->num_carnet ?? $parcours->numero_registre;
                } elseif (!$isValid) {
                    $cat->est_baptise = false;
                }
                break;

            case 'PREMIERE_COMMUNION':
                if ($isValid && $parcours) {
                    $cat->date_premiere_communion = $parcours->date_sacrement;
                    $cat->paroisse_premiere_communion = $parcours->lieu ?? $parcours->paroisse_nom;
                } elseif (!$isValid) {
                    $cat->date_premiere_communion = null;
                    $cat->paroisse_premiere_communion = null;
                }
                break;

            case 'CONFIRMATION':
                if ($isValid && $parcours) {
                    $cat->date_confirmation = $parcours->date_sacrement;
                    $cat->paroisse_confirmation = $parcours->lieu ?? $parcours->paroisse_nom;
                    $cat->ministre_confirmation = $parcours->celebrant;
                } elseif (!$isValid) {
                    $cat->date_confirmation = null;
                    $cat->paroisse_confirmation = null;
                    $cat->ministre_confirmation = null;
                }
                break;
        }

        $cat->save();
    }

    /**
     * Résout un ID d'un modèle par UUID ou ID numérique pour la paroisse.
     */
    protected function resolveId(string $modelClass, string|int $identifier, int $paroisseId): ?int
    {
        if (is_numeric($identifier)) {
            return (int) $identifier;
        }

        $record = $modelClass::where('paroisse_configuration_id', $paroisseId)
            ->where('uuid', $identifier)
            ->first();

        return $record?->id;
    }

    /**
     * Résout l'ID d'un sacrement par UUID, ID ou Code.
     */
    protected function resolveSacrementId(string|int $identifier): ?int
    {
        if (is_numeric($identifier)) {
            return (int) $identifier;
        }

        $sacr = Sacrement::where('uuid', $identifier)
            ->orWhere('code', strtoupper($identifier))
            ->first();

        return $sacr?->id;
    }
}
