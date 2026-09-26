<?php

namespace App\Services\SuperAdmin;

use App\Models\ActionAuditLog;
use App\Models\Abonnement;
use App\Models\Activite;
use App\Models\AnneeCatechese;
use App\Models\CampagnePelerinage;
use App\Models\CatecheseConfiguration;
use App\Models\Catechumene;
use App\Models\Classe;
use App\Models\Formule;
use App\Models\InscriptionAnnuelle;
use App\Models\InscriptionPelerinage;
use App\Models\Membre;
use App\Models\Niveau;
use App\Models\OperationOrganisation;
use App\Models\Organisation;
use App\Models\Paiement;
use App\Models\PaiementPelerinage;
use App\Models\Produit;
use App\Models\Section;
use App\Models\TarifPelerinage;
use App\Models\User;
use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class SoftDeleteAuditService
{
    /**
     * Registre des modèles supportés dans la Corbeille Super Admin.
     */
    public static function getRegistry(): array
    {
        return [
            'Paroisse' => [
                'key'          => 'Paroisse',
                'module'       => 'Paroisse',
                'model'        => CatecheseConfiguration::class,
                'label'        => fn($item) => $item->nom_paroisse ?? $item->nom ?? $item->uuid,
                'paroisse_col' => 'id',
                'org_col'      => null,
            ],
            'Organisation' => [
                'key'          => 'Organisation',
                'module'       => 'Organisation',
                'model'        => Organisation::class,
                'label'        => fn($item) => ($item->nom ?? $item->type_organisation) . " (" . ($item->type_organisation ?? '') . ")",
                'paroisse_col' => 'paroisse_configuration_id',
                'org_col'      => 'id',
            ],
            'Produit' => [
                'key'          => 'Produit',
                'module'       => 'Produit',
                'model'        => Produit::class,
                'label'        => fn($item) => ($item->nom ?? $item->code) . " [" . ($item->code ?? '') . "]",
                'paroisse_col' => null,
                'org_col'      => null,
            ],
            'Formule' => [
                'key'          => 'Formule',
                'module'       => 'Formule',
                'model'        => Formule::class,
                'label'        => fn($item) => ($item->nom ?? $item->code) . " (" . number_format((float) ($item->montant ?? 0), 0, ',', ' ') . " XOF)",
                'paroisse_col' => null,
                'org_col'      => null,
            ],
            'Abonnement' => [
                'key'          => 'Abonnement',
                'module'       => 'Abonnement',
                'model'        => Abonnement::class,
                'label'        => fn($item) => "Abonnement " . ($item->reference ?? $item->uuid),
                'paroisse_col' => 'paroisse_configuration_id',
                'org_col'      => null,
            ],
            'User' => [
                'key'          => 'Utilisateur',
                'module'       => 'Utilisateur',
                'model'        => User::class,
                'label'        => fn($item) => ($item->name ?? $item->email) . " (" . ($item->email ?? '') . ")",
                'paroisse_col' => 'paroisse_configuration_id',
                'org_col'      => 'organisation_id',
            ],
            'Catechumene' => [
                'key'          => 'Catéchumène',
                'module'       => 'Catéchumène',
                'model'        => Catechumene::class,
                'label'        => fn($item) => trim(($item->nom ?? '') . ' ' . ($item->prenoms ?? '') . ($item->matricule ? ' - ' . $item->matricule : '')),
                'paroisse_col' => 'paroisse_configuration_id',
                'org_col'      => null,
            ],
            'InscriptionAnnuelle' => [
                'key'          => 'InscriptionAnnuelle',
                'module'       => 'Inscription Catéchèse',
                'model'        => InscriptionAnnuelle::class,
                'label'        => fn($item) => "Inscription " . ($item->code_inscription ?? $item->uuid),
                'paroisse_col' => 'paroisse_configuration_id',
                'org_col'      => null,
            ],
            'Paiement' => [
                'key'          => 'Paiement',
                'module'       => 'Paiement Catéchèse',
                'model'        => Paiement::class,
                'label'        => fn($item) => "Paiement " . ($item->reference ?? $item->uuid) . " (" . number_format((float) ($item->montant ?? 0), 0, ',', ' ') . " XOF)",
                'paroisse_col' => 'paroisse_configuration_id',
                'org_col'      => null,
            ],
            'AnneeCatechese' => [
                'key'          => 'AnneeCatechese',
                'module'       => 'Année Pastorale',
                'model'        => AnneeCatechese::class,
                'label'        => fn($item) => "Année " . ($item->libelle ?? $item->nom ?? $item->uuid),
                'paroisse_col' => 'paroisse_configuration_id',
                'org_col'      => null,
            ],
            'Section' => [
                'key'          => 'Section',
                'module'       => 'Section',
                'model'        => Section::class,
                'label'        => fn($item) => "Section " . ($item->nom ?? $item->code ?? $item->uuid),
                'paroisse_col' => 'paroisse_configuration_id',
                'org_col'      => null,
            ],
            'Niveau' => [
                'key'          => 'Niveau',
                'module'       => 'Niveau',
                'model'        => Niveau::class,
                'label'        => fn($item) => "Niveau " . ($item->nom ?? $item->uuid),
                'paroisse_col' => 'paroisse_configuration_id',
                'org_col'      => null,
            ],
            'Classe' => [
                'key'          => 'Classe',
                'module'       => 'Classe',
                'model'        => Classe::class,
                'label'        => fn($item) => "Classe " . ($item->nom ?? $item->uuid),
                'paroisse_col' => 'paroisse_configuration_id',
                'org_col'      => null,
            ],
            'Membre' => [
                'key'          => 'Membre',
                'module'       => 'Membre Organisation',
                'model'        => Membre::class,
                'label'        => fn($item) => trim(($item->nom ?? '') . ' ' . ($item->prenoms ?? '')),
                'paroisse_col' => null,
                'org_col'      => 'organisation_id',
            ],
            'Activite' => [
                'key'          => 'Activite',
                'module'       => 'Activité Organisation',
                'model'        => Activite::class,
                'label'        => fn($item) => $item->titre ?? $item->nom ?? $item->uuid,
                'paroisse_col' => null,
                'org_col'      => 'organisation_id',
            ],
            'CampagnePelerinage' => [
                'key'          => 'CampagnePelerinage',
                'module'       => 'Campagne Pèlerinage',
                'model'        => CampagnePelerinage::class,
                'label'        => fn($item) => $item->titre ?? $item->libelle ?? $item->uuid,
                'paroisse_col' => null,
                'org_col'      => 'organisation_id',
            ],
            'InscriptionPelerinage' => [
                'key'          => 'InscriptionPelerinage',
                'module'       => 'Participant Pèlerinage',
                'model'        => InscriptionPelerinage::class,
                'label'        => fn($item) => "Participant " . trim(($item->nom ?? '') . ' ' . ($item->prenoms ?? '')),
                'paroisse_col' => null,
                'org_col'      => 'organisation_id',
            ],
            'TarifPelerinage' => [
                'key'          => 'TarifPelerinage',
                'module'       => 'Tarif Pèlerinage',
                'model'        => TarifPelerinage::class,
                'label'        => fn($item) => ($item->libelle ?? 'Tarif') . " (" . number_format((float) ($item->montant ?? 0), 0, ',', ' ') . " XOF)",
                'paroisse_col' => null,
                'org_col'      => null,
            ],
            'PaiementPelerinage' => [
                'key'          => 'PaiementPelerinage',
                'module'       => 'Paiement Pèlerinage',
                'model'        => PaiementPelerinage::class,
                'label'        => fn($item) => "Paiement Pèlerinage " . ($item->reference ?? $item->uuid),
                'paroisse_col' => null,
                'org_col'      => null,
            ],
            'OperationOrganisation' => [
                'key'          => 'OperationOrganisation',
                'module'       => 'Caisse Organisation',
                'model'        => OperationOrganisation::class,
                'label'        => fn($item) => "Opération " . ($item->reference ?? $item->uuid) . " (" . number_format((float) ($item->montant ?? 0), 0, ',', ' ') . " XOF)",
                'paroisse_col' => null,
                'org_col'      => 'organisation_id',
            ],
        ];
    }

    /**
     * Scanner tous les modèles en Soft Delete avec filtres et pagination.
     */
    public function list(array $filters = [], int $perPage = 25, int $page = 1): LengthAwarePaginator
    {
        $registry = self::getRegistry();
        $targetModule = $filters['module'] ?? null;

        // Filtrage éventuel d'un module unique
        if (!empty($targetModule) && $targetModule !== 'tous') {
            $filteredRegistry = [];
            foreach ($registry as $key => $meta) {
                if (
                    strcasecmp($key, $targetModule) === 0 ||
                    strcasecmp($meta['module'], $targetModule) === 0 ||
                    str_contains(strtolower($meta['module']), strtolower($targetModule))
                ) {
                    $filteredRegistry[$key] = $meta;
                }
            }
            if (!empty($filteredRegistry)) {
                $registry = $filteredRegistry;
            }
        }

        // Résolution de paroisse_id et organisation_id si UUID passés
        $paroisseId = null;
        if (!empty($filters['paroisse_id'])) {
            $pVal = $filters['paroisse_id'];
            $paroisseId = is_numeric($pVal)
                ? (int) $pVal
                : CatecheseConfiguration::where('uuid', $pVal)->value('id');
        }

        $organisationId = null;
        if (!empty($filters['organisation_id'])) {
            $oVal = $filters['organisation_id'];
            $organisationId = is_numeric($oVal)
                ? (int) $oVal
                : Organisation::where('uuid', $oVal)->value('id');
        }

        $allTrashed = new Collection();

        foreach ($registry as $key => $meta) {
            /** @var \Illuminate\Database\Eloquent\Model $modelClass */
            $modelClass = $meta['model'];
            $query = $modelClass::onlyTrashed();

            // Filtre paroisse
            if ($paroisseId && !empty($meta['paroisse_col'])) {
                $col = $meta['paroisse_col'];
                $query->where($col, $paroisseId);
            }

            // Filtre organisation
            if ($organisationId && !empty($meta['org_col'])) {
                $col = $meta['org_col'];
                $query->where($col, $organisationId);
            }

            // Filtre dates
            if (!empty($filters['date_debut'])) {
                $query->whereDate('deleted_at', '>=', $filters['date_debut']);
            }
            if (!empty($filters['date_fin'])) {
                $query->whereDate('deleted_at', '<=', $filters['date_fin']);
            }

            $items = $query->latest('deleted_at')->limit(100)->get();

            foreach ($items as $item) {
                $label = is_callable($meta['label']) ? $meta['label']($item) : ($item->nom ?? $item->uuid);

                // Résolution éventuelle de la personne ayant supprimé
                $supprimePar = null;
                if (!empty($item->deleted_by)) {
                    $u = User::where('uuid', $item->deleted_by)->orWhere('id', $item->deleted_by)->first();
                    $supprimePar = $u ? ($u->name . ' (' . $u->email . ')') : $item->deleted_by;
                }

                $allTrashed->push([
                    'id'               => $item->uuid,
                    'uuid'             => $item->uuid,
                    'module'           => $meta['module'],
                    'module_key'       => $meta['key'],
                    'model'            => class_basename($modelClass),
                    'element'          => $label,
                    'date_suppression' => $item->deleted_at?->toIso8601String(),
                    'deleted_at'       => $item->deleted_at?->toIso8601String(),
                    'supprime_par'     => $supprimePar,
                    'item'             => $item,
                ]);
            }
        }

        // Tri global décroissant par date de suppression
        $sorted = $allTrashed->sortByDesc('deleted_at')->values();

        // Filtre search sur l'élément ou le module
        if (!empty($filters['search'])) {
            $search = strtolower(trim($filters['search']));
            $sorted = $sorted->filter(function ($row) use ($search) {
                return str_contains(strtolower($row['element']), $search) ||
                       str_contains(strtolower($row['module']), $search) ||
                       str_contains(strtolower((string) $row['supprime_par']), $search) ||
                       str_contains(strtolower($row['uuid']), $search);
            })->values();
        }

        // Pagination en mémoire de la collection consolidée
        $total = $sorted->count();
        $offset = ($page - 1) * $perPage;
        $itemsForCurrentPage = $sorted->slice($offset, $perPage)->values();

        return new LengthAwarePaginator(
            $itemsForCurrentPage,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );
    }

    /**
     * Trouver un élément de la corbeille par son UUID.
     */
    public function find(string $uuid): ?array
    {
        $registry = self::getRegistry();

        foreach ($registry as $key => $meta) {
            /** @var \Illuminate\Database\Eloquent\Model $modelClass */
            $modelClass = $meta['model'];
            $item = $modelClass::onlyTrashed()->where('uuid', $uuid)->first();

            if ($item) {
                $label = is_callable($meta['label']) ? $meta['label']($item) : ($item->nom ?? $item->uuid);
                $supprimePar = null;
                if (!empty($item->deleted_by)) {
                    $u = User::where('uuid', $item->deleted_by)->orWhere('id', $item->deleted_by)->first();
                    $supprimePar = $u ? ($u->name . ' (' . $u->email . ')') : $item->deleted_by;
                }

                $dependances = $this->getDependenciesPreview($item);

                // Historique d'audit des actions sur cet élément (anciennes vs nouvelles valeurs)
                $auditTrail = ActionAuditLog::where(function ($q) use ($item) {
                    $q->where('entite_id', $item->uuid)
                      ->orWhere('entite_id', (string) $item->id);
                })
                ->latest('id')
                ->limit(5)
                ->get()
                ->map(fn($log) => [
                    'action'            => $log->action,
                    'description'       => $log->description,
                    'user_nom'          => $log->user_nom,
                    'anciennes_valeurs' => $log->anciennes_valeurs,
                    'nouvelles_valeurs' => $log->nouvelles_valeurs,
                    'created_at'        => $log->created_at?->toIso8601String(),
                ]);

                return [
                    'id'                  => $item->uuid,
                    'uuid'                => $item->uuid,
                    'module'              => $meta['module'],
                    'module_key'          => $meta['key'],
                    'model'               => class_basename($modelClass),
                    'element'             => $label,
                    'nom'                 => $item->nom ?? $label,
                    'date_suppression'    => $item->deleted_at?->toIso8601String(),
                    'deleted_at'          => $item->deleted_at?->toIso8601String(),
                    'supprime_par'        => $supprimePar,
                    'dependances'         => $dependances,
                    'bouton_restaurer'    => true,
                    'peut_restaurer'      => true,
                    'apercu_restauration' => [
                        'nom'              => $item->nom ?? $label,
                        'module'           => $meta['module'],
                        'date'             => $item->deleted_at?->toIso8601String(),
                        'supprime_par'     => $supprimePar,
                        'dependances'      => $dependances,
                        'bouton_restaurer' => true,
                    ],
                    'historique_audit'    => $auditTrail,
                    'model_instance'      => $item,
                    'attributes'          => $item->toArray(),
                ];
            }
        }

        return null;
    }

    /**
     * Analyse des dépendances et statistiques associées à un élément supprimé.
     */
    protected function getDependenciesPreview(Model $item): array
    {
        $deps = [];

        if ($item instanceof CatecheseConfiguration) {
            $deps['organisations'] = Organisation::where('paroisse_configuration_id', $item->id)->count();
            $deps['catechumenes']  = Catechumene::where('paroisse_configuration_id', $item->id)->count();
            $deps['utilisateurs']  = User::where('paroisse_configuration_id', $item->id)->count();
            $deps['abonnements']   = Abonnement::where('paroisse_configuration_id', $item->id)->count();
        } elseif ($item instanceof Organisation) {
            if ($item->paroisse_configuration_id) {
                $p = CatecheseConfiguration::withTrashed()->find($item->paroisse_configuration_id);
                $deps['paroisse_parente'] = [
                    'nom'        => $p?->nom_paroisse ?? 'Inconnue',
                    'est_active' => $p ? ($p->deleted_at === null) : false,
                ];
            } else {
                $deps['mode'] = 'independant';
            }
            $deps['membres']      = Membre::where('organisation_id', $item->id)->count();
            $deps['activites']    = Activite::where('organisation_id', $item->id)->count();
            $deps['pelerinages']  = CampagnePelerinage::where('organisation_id', $item->id)->count();
            $deps['utilisateurs'] = User::where('organisation_id', $item->id)->count();
        } elseif ($item instanceof Produit) {
            $deps['formules']      = Formule::where('produit_id', $item->id)->count();
            $deps['organisations'] = Organisation::where('produit_id', $item->id)->count();
        } elseif ($item instanceof User) {
            if ($item->organisation_id) {
                $o = Organisation::withTrashed()->find($item->organisation_id);
                $deps['organisation'] = $o?->nom;
            }
            if ($item->paroisse_configuration_id) {
                $p = CatecheseConfiguration::withTrashed()->find($item->paroisse_configuration_id);
                $deps['paroisse'] = $p?->nom_paroisse;
            }
        } elseif (property_exists($item, 'organisation_id') || Schema::hasColumn($item->getTable(), 'organisation_id')) {
            if (!empty($item->organisation_id)) {
                $o = Organisation::withTrashed()->find($item->organisation_id);
                $deps['organisation_parente'] = [
                    'nom'        => $o?->nom ?? 'Inconnue',
                    'est_active' => $o ? ($o->deleted_at === null) : false,
                ];
            }
        }

        return $deps;
    }

    /**
     * Restaurer un élément depuis la corbeille.
     */
    public function restore(string $uuid): array
    {
        $found = $this->find($uuid);

        if (!$found) {
            throw new Exception("Élément introuvable dans la corbeille [{$uuid}].", 404);
        }

        /** @var \Illuminate\Database\Eloquent\Model $item */
        $item = $found['model_instance'];
        $item->restore();

        return [
            'id'      => $item->uuid,
            'uuid'    => $item->uuid,
            'module'  => $found['module'],
            'element' => $found['element'],
            'message' => "L'élément [{$found['element']}] du module [{$found['module']}] a été restauré avec succès.",
        ];
    }

    /**
     * Supprimer définitivement un élément (Force Delete) avec respect rigoureux des contraintes d'intégrité.
     */
    public function forceDelete(string $uuid): array
    {
        $found = $this->find($uuid);

        if (!$found) {
            throw new Exception("Élément introuvable dans la corbeille [{$uuid}].", 404);
        }

        /** @var \Illuminate\Database\Eloquent\Model $item */
        $item = $found['model_instance'];

        // ─────────────────────────────────────────────────────────────
        // VÉRIFICATION STRICTE DES CONTRAINTES D'INTÉGRITÉ AVANT PURGE
        // ─────────────────────────────────────────────────────────────

        if ($item instanceof CatecheseConfiguration) {
            // Paroisse : vérifier les enfants vivants
            $hasOrgs = Organisation::where('paroisse_configuration_id', $item->id)->exists();
            $hasUsers = User::where('paroisse_configuration_id', $item->id)->exists();
            $hasCats = Catechumene::where('paroisse_configuration_id', $item->id)->exists();
            $hasAbos = Abonnement::where('paroisse_configuration_id', $item->id)->exists();

            if ($hasOrgs || $hasUsers || $hasCats || $hasAbos) {
                throw new Exception(
                    "Suppression définitive impossible : cette paroisse est encore référencée par des données actives (organisations, utilisateurs, catéchumènes ou abonnements).",
                    422
                );
            }
        }

        if ($item instanceof Produit) {
            // Produit : vérifier les formules et organisations vivantes
            $hasOrgs = Organisation::where('produit_id', $item->id)->exists();
            $hasFormules = Formule::where('produit_id', $item->id)->exists();

            if ($hasOrgs || $hasFormules) {
                throw new Exception(
                    "Suppression définitive impossible : ce produit possède encore des formules ou des organisations actives rattachées.",
                    422
                );
            }
        }

        if ($item instanceof Organisation) {
            // Organisation : vérifier les membres, activités ou utilisateurs
            $hasUsers = User::where('organisation_id', $item->id)->exists();
            $hasMembres = Membre::where('organisation_id', $item->id)->exists();

            if ($hasUsers || $hasMembres) {
                throw new Exception(
                    "Suppression définitive impossible : cette organisation possède encore des utilisateurs ou membres rattachés.",
                    422
                );
            }
        }

        if ($item instanceof User) {
            // Utilisateur : vérifier s'il est responsable actif d'une organisation
            $isResp = Organisation::where('responsable_email', $item->email)->exists();
            if ($isResp) {
                throw new Exception(
                    "Suppression définitive impossible : cet utilisateur est actuellement enregistré comme responsable officiel d'une organisation.",
                    422
                );
            }
        }

        $elementLabel = $found['element'];
        $moduleLabel = $found['module'];

        $item->forceDelete();

        return [
            'id'      => $uuid,
            'uuid'    => $uuid,
            'module'  => $moduleLabel,
            'element' => $elementLabel,
            'message' => "L'élément [{$elementLabel}] du module [{$moduleLabel}] a été supprimé définitivement du système.",
        ];
    }
}
