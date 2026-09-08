<?php

namespace App\Services;

use App\Models\AnneeCatechese;
use App\Models\Catechumene;
use App\Models\CatechumenSacrement;
use App\Models\Classe;
use App\Models\Niveau;
use App\Models\Sacrement;
use App\Models\SacrementException;
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
                'exceptionsSacrements.sacrement',
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

    /**
     * Candidats au Baptême : 3ème Année + NON BAPTISÉ
     */
    public function getCandidatsBapteme(int $paroisseId, array $filters = []): LengthAwarePaginator
    {
        $query = $this->buildCandidatsBaseQuery($paroisseId, $filters)
            ->where(function ($rootQ) {
                $rootQ->where(function ($q) {
                    $q->where(function ($bq) {
                        $bq->whereNull('est_baptise')
                          ->orWhere('est_baptise', false)
                          ->orWhere('est_baptise', 0);
                    })
                    ->where(function ($bq) {
                        $bq->whereNull('date_bapteme')
                          ->orWhere('date_bapteme', '');
                    })
                    ->where(function ($bq) {
                        $bq->whereNull('paroisse_bapteme')
                          ->orWhere('paroisse_bapteme', '');
                    });
                    $this->applyTroisiemeAnneeFilter($q);
                })
                ->orWhereHas('exceptionsSacrements', function ($eq) {
                    $eq->where('statut', 'actif')
                       ->whereHas('sacrement', fn($sq) => $sq->where('code', 'BAPTEME')->orWhere('id', 1));
                });
            });

        $this->applyCommonFilters($query, $paroisseId, $filters);

        $perPage = (int) ($filters['per_page'] ?? 100);
        return $query->latest('id')->paginate($perPage);
    }

    /**
     * Candidats à la Première Communion : 3ème Année + BAPTISÉ (ou Dérogation pastorale)
     */
    public function getCandidatsPremiereCommunion(int $paroisseId, array $filters = []): LengthAwarePaginator
    {
        $query = $this->buildCandidatsBaseQuery($paroisseId, $filters)
            ->where(function ($rootQ) {
                $rootQ->where(function ($q) {
                    $q->where(function ($bq) {
                        $bq->where('est_baptise', true)
                          ->orWhere('est_baptise', 1)
                          ->orWhereNotNull('date_bapteme')
                          ->orWhere(function ($sq) {
                              $sq->whereNotNull('paroisse_bapteme')->where('paroisse_bapteme', '!=', '');
                          });
                    });
                    $this->applyTroisiemeAnneeFilter($q);
                })
                ->orWhereHas('exceptionsSacrements', function ($eq) {
                    $eq->where('statut', 'actif')
                       ->whereHas('sacrement', fn($sq) => $sq->where('code', 'PREMIERE_COMMUNION')->orWhere('id', 2));
                });
            });

        $this->applyCommonFilters($query, $paroisseId, $filters);

        $perPage = (int) ($filters['per_page'] ?? 100);
        return $query->latest('id')->paginate($perPage);
    }

    /**
     * Candidats à la Confirmation :
     * - Section Adulte : 4ème ou 5ème Année + BAPTISÉ (ou Dérogation)
     * - Autre section : 5ème Année + BAPTISÉ (ou Dérogation)
     */
    public function getCandidatsConfirmation(int $paroisseId, array $filters = []): LengthAwarePaginator
    {
        $query = $this->buildCandidatsBaseQuery($paroisseId, $filters)
            ->where(function ($rootQ) {
                $rootQ->where(function ($q) {
                    $q->where(function ($bq) {
                        $bq->where('est_baptise', true)
                          ->orWhere('est_baptise', 1)
                          ->orWhereNotNull('date_bapteme')
                          ->orWhere(function ($sq) {
                              $sq->whereNotNull('paroisse_bapteme')->where('paroisse_bapteme', '!=', '');
                          });
                    });
                    $this->applyConfirmationNiveauFilter($q);
                })
                ->orWhereHas('exceptionsSacrements', function ($eq) {
                    $eq->where('statut', 'actif')
                       ->whereHas('sacrement', fn($sq) => $sq->where('code', 'CONFIRMATION')->orWhere('id', 3));
                });
            });

        $this->applyCommonFilters($query, $paroisseId, $filters);

        $perPage = (int) ($filters['per_page'] ?? 100);
        return $query->latest('id')->paginate($perPage);
    }

    protected function buildCandidatsBaseQuery(int $paroisseId, array $filters = []): Builder
    {
        return Catechumene::where('paroisse_configuration_id', $paroisseId)
            ->whereHas('inscriptionsAnnuelles', function (Builder $q) {
                $q->where('statut_inscription', '!=', 'annulee');
            })
            ->with([
                'inscriptionsAnnuelles' => function ($q) {
                    $q->where('statut_inscription', '!=', 'annulee')
                      ->with(['section', 'niveau', 'classe', 'anneeCatechese'])
                      ->latest('id');
                },
                'parcoursSacrements.sacrement',
                'exceptionsSacrements.sacrement',
            ]);
    }

    protected function applyTroisiemeAnneeFilter(Builder $query): void
    {
        $query->whereHas('inscriptionsAnnuelles', function (Builder $iq) {
            $iq->where('statut_inscription', '!=', 'annulee')
               ->where(function ($sq) {
                   $sq->whereHas('niveau', function ($nq) {
                       $nq->where('ordre_affichage', 3)
                          ->orWhere('nom', 'like', '%3ème%')
                          ->orWhere('nom', 'like', '%3eme%')
                          ->orWhere('nom', 'like', '%3e %')
                          ->orWhere('nom', 'like', '3e %')
                          ->orWhere('nom', 'like', '%trois%');
                   })->orWhere(function ($cq) {
                       $cq->whereDoesntHave('niveau', function ($nq) {
                           $nq->whereIn('ordre_affichage', [1, 2, 4, 5]);
                       })->whereHas('classe', function ($clq) {
                           $clq->where('nom', 'like', '%3ème%')
                               ->orWhere('nom', 'like', '%3eme%')
                               ->orWhere('nom', 'like', '%3e %')
                               ->orWhere('nom', 'like', '3e %')
                               ->orWhere('nom', 'like', '%trois%');
                       });
                   });
               });
        });
    }

    protected function applyConfirmationNiveauFilter(Builder $query): void
    {
        $query->whereHas('inscriptionsAnnuelles', function (Builder $iq) {
            $iq->where('statut_inscription', '!=', 'annulee')
               ->where(function ($sq) {
                   // Section Adulte: 4e ou 5e année
                   $sq->where(function ($aq) {
                       $aq->where(function ($secQ) {
                           $secQ->whereHas('section', function ($sq2) {
                               $sq2->where('code', 'SEC-ADULTE')
                                   ->orWhere('code', 'like', '%ADULTE%')
                                   ->orWhere('nom', 'like', '%adulte%');
                           })->orWhereHas('classe', function ($cq2) {
                               $cq2->where('nom', 'like', '%adulte%');
                           });
                       })->where(function ($nivQ) {
                           $nivQ->whereHas('niveau', function ($nq) {
                               $nq->whereIn('ordre_affichage', [4, 5])
                                  ->orWhere('nom', 'like', '%4%')
                                  ->orWhere('nom', 'like', '%5%');
                           })->orWhere(function ($clQ) {
                               $clQ->whereDoesntHave('niveau', function ($nq) {
                                   $nq->whereIn('ordre_affichage', [1, 2, 3]);
                               })->whereHas('classe', function ($cq) {
                                   $cq->where('nom', 'like', '%4%')
                                      ->orWhere('nom', 'like', '%5%');
                               });
                           });
                       });
                   })
                   // Autre section: 5e année
                   ->orWhere(function ($oq) {
                       $oq->whereHas('niveau', function ($nq) {
                           $nq->where('ordre_affichage', 5)
                              ->orWhere('nom', 'like', '%5%')
                              ->orWhere('nom', 'like', '%cinq%')
                              ->orWhere('nom', 'like', '%confirmat%');
                       })->orWhere(function ($clQ) {
                           $clQ->whereDoesntHave('niveau', function ($nq) {
                               $nq->whereIn('ordre_affichage', [1, 2, 3, 4]);
                           })->whereHas('classe', function ($cq) {
                               $cq->where('nom', 'like', '%5%')
                                  ->orWhere('nom', 'like', '%cinq%')
                                  ->orWhere('nom', 'like', '%confirmat%');
                           });
                       });
                   });
               });
        });
    }

    protected function applyCommonFilters(Builder $query, int $paroisseId, array $filters = []): void
    {
        if (!empty($filters['annee_catechese_id'])) {
            $anneeId = $this->resolveId(AnneeCatechese::class, $filters['annee_catechese_id'], $paroisseId);
            if ($anneeId) {
                $query->whereHas('inscriptionsAnnuelles', fn($q) => $q->where('annee_catechese_id', $anneeId));
            }
        }

        if (!empty($filters['section_id'])) {
            $sectionId = $this->resolveId(Section::class, $filters['section_id'], $paroisseId);
            if ($sectionId) {
                $query->whereHas('inscriptionsAnnuelles', fn($q) => $q->where('section_id', $sectionId));
            }
        }

        if (!empty($filters['niveau_id'])) {
            $niveauId = $this->resolveId(Niveau::class, $filters['niveau_id'], $paroisseId);
            if ($niveauId) {
                $query->whereHas('inscriptionsAnnuelles', fn($q) => $q->where('niveau_id', $niveauId));
            }
        }

        if (!empty($filters['classe_id'])) {
            $classeId = $this->resolveId(Classe::class, $filters['classe_id'], $paroisseId);
            if ($classeId) {
                $query->whereHas('inscriptionsAnnuelles', fn($q) => $q->where('classe_id', $classeId));
            }
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenoms', 'like', "%{$search}%")
                  ->orWhere('matricule', 'like', "%{$search}%")
                  ->orWhere('telephone', 'like', "%{$search}%");
            });
        }
    }

    /**
     * Obtenir la liste des exceptions pastorales / dérogations de la paroisse.
     */
    public function getExceptions(int $paroisseId, array $filters = []): Collection
    {
        $query = SacrementException::where('paroisse_configuration_id', $paroisseId)
            ->with([
                'catechumene.inscriptionsAnnuelles' => function ($q) {
                    $q->where('statut_inscription', '!=', 'annulee')
                      ->with(['section', 'niveau', 'classe', 'anneeCatechese'])
                      ->latest('id');
                },
                'sacrement',
                'anneeCatechese',
                'creator'
            ]);

        if (!empty($filters['sacrement_id'])) {
            $sacrId = $this->resolveSacrementId($filters['sacrement_id']);
            if ($sacrId) {
                $query->where('sacrement_id', $sacrId);
            }
        }

        if (!empty($filters['statut'])) {
            $query->where('statut', $filters['statut']);
        }

        if (!empty($filters['annee_catechese_id'])) {
            $anneeId = $this->resolveId(AnneeCatechese::class, $filters['annee_catechese_id'], $paroisseId);
            if ($anneeId) {
                $query->where('annee_catechese_id', $anneeId);
            }
        }

        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('motif', 'like', "%{$search}%")
                  ->orWhere('autorise_par', 'like', "%{$search}%")
                  ->orWhere('observation', 'like', "%{$search}%")
                  ->orWhereHas('catechumene', function ($cq) use ($search) {
                      $cq->where('nom', 'like', "%{$search}%")
                         ->orWhere('prenoms', 'like', "%{$search}%")
                         ->orWhere('matricule', 'like', "%{$search}%");
                  });
            });
        }

        return $query->latest('id')->get();
    }

    /**
     * Enregistrer une exception pastorale (dérogation) pour un catéchumène.
     */
    public function storeException(int $paroisseId, array $data, ?User $user): SacrementException
    {
        $catIdentifier = $data['catechumene_id'] ?? $data['catechumeneId'] ?? null;
        if (!$catIdentifier) {
            throw ValidationException::withMessages(['catechumene_id' => 'Le catéchumène est obligatoire.']);
        }

        $catechumene = is_numeric($catIdentifier)
            ? Catechumene::where('paroisse_configuration_id', $paroisseId)->where('id', $catIdentifier)->first()
            : Catechumene::where('paroisse_configuration_id', $paroisseId)->where('uuid', $catIdentifier)->first();

        if (!$catechumene) {
            throw ValidationException::withMessages(['catechumene_id' => 'Catéchumène introuvable pour cette paroisse.']);
        }

        $sacrementId = null;
        if (!empty($data['sacrement_id'])) {
            $sacrementId = $this->resolveSacrementId($data['sacrement_id']);
        } elseif (!empty($data['sacrement_type']) || !empty($data['sacrementType'])) {
            $st = $data['sacrement_type'] ?? $data['sacrementType'];
            $sacrementId = $this->resolveSacrementId($st);
            if (!$sacrementId) {
                $cleanSt = strtolower(trim($st));
                if (str_contains($cleanSt, 'bapt')) {
                    $sacrementId = 1;
                } elseif (str_contains($cleanSt, 'commun')) {
                    $sacrementId = 2;
                } elseif (str_contains($cleanSt, 'confirm')) {
                    $sacrementId = 3;
                }
            }
        }

        if (!$sacrementId) {
            $sacrementId = 1;
        }

        $anneeId = null;
        if (!empty($data['annee_catechese_id']) || !empty($data['anneeCatecheseId'])) {
            $rawAnnee = $data['annee_catechese_id'] ?? $data['anneeCatecheseId'];
            $anneeId = $this->resolveId(AnneeCatechese::class, $rawAnnee, $paroisseId);
        }
        if (!$anneeId) {
            // 1. Année active par statut (active, ouverte, en_cours)
            $activeAnnee = AnneeCatechese::where('paroisse_configuration_id', $paroisseId)
                ->whereIn('statut', ['active', 'ouverte', 'en_cours'])
                ->latest('id')
                ->first();

            // 2. Année issue de la dernière inscription du catéchumène
            if (!$activeAnnee) {
                $lastInsc = $catechumene->inscriptionsAnnuelles()
                    ->where('statut_inscription', '!=', 'annulee')
                    ->latest('id')
                    ->first();
                $anneeId = $lastInsc?->annee_catechese_id;
            } else {
                $anneeId = $activeAnnee->id;
            }

            // 3. Repli : toute dernière année pastorale enregistrée
            if (!$anneeId) {
                $anneeId = AnneeCatechese::where('paroisse_configuration_id', $paroisseId)
                    ->latest('id')
                    ->first()?->id;
            }
        }

        $dateDerogation = $data['date_derogation'] ?? $data['dateAjout'] ?? now()->toDateString();
        $motif = $data['motif'] ?? 'Décision du Curé';
        $autorisePar = $data['autorise_par'] ?? $data['autorisePar'] ?? 'Père Curé';
        $observation = $data['observation'] ?? null;
        $statut = $data['statut'] ?? 'actif';

        $exception = SacrementException::create([
            'paroisse_configuration_id' => $paroisseId,
            'catechumene_id'            => $catechumene->id,
            'sacrement_id'               => $sacrementId,
            'annee_catechese_id'         => $anneeId,
            'motif'                      => $motif,
            'autorise_par'               => $autorisePar,
            'observation'                => $observation,
            'date_derogation'            => $dateDerogation,
            'statut'                     => $statut,
            'created_by'                 => $user?->id,
        ]);

        return $exception->load([
            'catechumene.inscriptionsAnnuelles' => function ($q) {
                $q->where('statut_inscription', '!=', 'annulee')
                  ->with(['section', 'niveau', 'classe', 'anneeCatechese'])
                  ->latest('id');
            },
            'sacrement',
            'anneeCatechese',
            'creator'
        ]);
    }

    /**
     * Mettre à jour une exception pastorale.
     */
    public function updateException(int $paroisseId, string|int $exceptionId, array $data, ?User $user): SacrementException
    {
        $exception = SacrementException::where('paroisse_configuration_id', $paroisseId)
            ->where(function ($q) use ($exceptionId) {
                $q->where('uuid', $exceptionId)
                  ->orWhere('id', is_numeric($exceptionId) ? $exceptionId : 0);
            })
            ->firstOrFail();

        if (isset($data['motif'])) {
            $exception->motif = $data['motif'];
        }
        if (isset($data['autorise_par']) || isset($data['autorisePar'])) {
            $exception->autorise_par = $data['autorise_par'] ?? $data['autorisePar'];
        }
        if (array_key_exists('observation', $data)) {
            $exception->observation = $data['observation'];
        }
        if (isset($data['date_derogation']) || isset($data['dateAjout'])) {
            $exception->date_derogation = $data['date_derogation'] ?? $data['dateAjout'];
        }
        if (isset($data['statut'])) {
            $exception->statut = $data['statut'];
        }
        if (!empty($data['annee_catechese_id']) || !empty($data['anneeCatecheseId'])) {
            $rawAnnee = $data['annee_catechese_id'] ?? $data['anneeCatecheseId'];
            $anneeId = $this->resolveId(AnneeCatechese::class, $rawAnnee, $paroisseId);
            if ($anneeId) {
                $exception->annee_catechese_id = $anneeId;
            }
        }
        if (!empty($data['sacrement_id']) || !empty($data['sacrement_type']) || !empty($data['sacrementType'])) {
            $sacrId = !empty($data['sacrement_id'])
                ? $this->resolveSacrementId($data['sacrement_id'])
                : $this->resolveSacrementId($data['sacrement_type'] ?? $data['sacrementType']);
            if ($sacrId) {
                $exception->sacrement_id = $sacrId;
            }
        }
        $exception->updated_by = $user?->id;
        $exception->save();

        return $exception->load([
            'catechumene.inscriptionsAnnuelles' => function ($q) {
                $q->where('statut_inscription', '!=', 'annulee')
                  ->with(['section', 'niveau', 'classe', 'anneeCatechese'])
                  ->latest('id');
            },
            'sacrement',
            'anneeCatechese',
            'creator'
        ]);
    }

    /**
     * Supprimer (soft-delete) une exception pastorale.
     */
    public function deleteException(int $paroisseId, string|int $exceptionId, ?User $user = null): bool
    {
        $exception = SacrementException::where('paroisse_configuration_id', $paroisseId)
            ->where(function ($q) use ($exceptionId) {
                $q->where('uuid', $exceptionId)
                  ->orWhere('id', is_numeric($exceptionId) ? $exceptionId : 0);
            })
            ->firstOrFail();

        if ($user) {
            $exception->deleted_by = $user->id;
            $exception->save();
        }

        return (bool) $exception->delete();
    }
}