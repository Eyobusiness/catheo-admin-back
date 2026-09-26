<?php

namespace App\Services\Organisation;

use App\Models\AnneeCatechese;
use App\Models\CampagnePelerinage;
use App\Models\Catechumene;
use App\Models\InscriptionAnnuelle;
use App\Models\InscriptionPelerinage;
use App\Models\TarifPelerinage;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class InscriptionPelerinageService
{
    public function __construct(
        protected CatheoPopulationService $catheoPopulationService
    ) {}

    /**
     * Génère une référence unique pour l'inscription.
     * Convention : INS-{CAMPAGNE_CODE}-{SEQUENCE sur 4 chiffres}
     */
    public function generateReference(CampagnePelerinage $campagne): string
    {
        $prefix = "INS-{$campagne->code}-";

        $count = InscriptionPelerinage::withTrashed()
            ->where('campagne_pelerinage_id', $campagne->id)
            ->count();

        return sprintf('%s%04d', $prefix, $count + 1);
    }

    /**
     * Liste paginée des inscriptions pour une campagne donnée avec filtres.
     */
    public function list(CampagnePelerinage $campagne, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = InscriptionPelerinage::with(['tarif', 'paiements', 'catechumene'])
            ->where('campagne_pelerinage_id', $campagne->id)
            ->latest('id');

        if (!empty($filters['statut_inscription'])) {
            $query->where('statut_inscription', $filters['statut_inscription']);
        }

        if (!empty($filters['statut_participation'])) {
            $query->where('statut_participation', $filters['statut_participation']);
        }

        if (!empty($filters['type_participant'])) {
            $query->where('type_participant', strtoupper($filters['type_participant']));
        }

        if (!empty($filters['tarif_id'])) {
            $query->where('tarif_pelerinage_id', $filters['tarif_id']);
        }

        if (!empty($filters['taille'])) {
            $query->where('taille', strtoupper(trim($filters['taille'])));
        }

        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenoms', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhere('telephone', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Récupère une inscription d'une campagne spécifique.
     */
    public function find(CampagnePelerinage $campagne, int|string $idOrUuid): InscriptionPelerinage
    {
        $inscription = InscriptionPelerinage::with(['tarif', 'paiements', 'catechumene'])
            ->where('campagne_pelerinage_id', $campagne->id)
            ->where(function ($q) use ($idOrUuid) {
                if (is_numeric($idOrUuid)) {
                    $q->where('id', $idOrUuid);
                } else {
                    $q->where('uuid', $idOrUuid);
                }
            })
            ->first();

        if (!$inscription) {
            throw new NotFoundHttpException("Inscription introuvable pour cette campagne de pèlerinage.");
        }

        return $inscription;
    }

    /**
     * Enregistre une nouvelle inscription (externe ou catéchumène individuel).
     */
    public function create(CampagnePelerinage $campagne, array $data): InscriptionPelerinage
    {
        return DB::transaction(function () use ($campagne, $data) {
            // Verrouillage de la ligne campagne pour éviter les dépassements concurrents de capacité
            $lockedCampagne = CampagnePelerinage::where('id', $campagne->id)->lockForUpdate()->firstOrFail();

            // 1. Vérification du statut de la campagne
            if (in_array($lockedCampagne->statut, [CampagnePelerinage::STATUT_CLOTUREE, CampagnePelerinage::STATUT_ANNULEE])) {
                throw new UnprocessableEntityHttpException("Impossible d'inscrire des participants sur une campagne {$lockedCampagne->statut}.");
            }

            // 2. Contrôle strict de la capacité
            if ($lockedCampagne->estComplete()) {
                throw new UnprocessableEntityHttpException("La capacité maximale de cette campagne ({$lockedCampagne->capacite} places) est déjà atteinte.");
            }

            // 3. Validation et récupération du tarif
            $tarif = $lockedCampagne->tarifs()->where('id', $data['tarif_pelerinage_id'])->first();
            if (!$tarif) {
                throw new UnprocessableEntityHttpException("Le tarif sélectionné n'appartient pas à cette campagne.");
            }

            $typeParticipant = $data['type_participant'] ?? InscriptionPelerinage::TYPE_CATECHUMENE;

            // 4. Si catéchumène spécifié, snapshot des coordonnées et contrôle anti-doublon
            $catechumeneId = $data['catechumene_id'] ?? null;
            if ($catechumeneId) {
                $organisation = $lockedCampagne->organisation;
                $catechumene = Catechumene::where(is_numeric($catechumeneId) ? 'id' : 'uuid', $catechumeneId)
                    ->where('paroisse_configuration_id', $organisation->paroisse_configuration_id)
                    ->first();

                if (!$catechumene) {
                    throw new UnprocessableEntityHttpException("Le catéchumène spécifié n'appartient pas à la paroisse de l'organisation.");
                }

                // Vérification de doublon actif pour catéchumène
                $dejaInscrit = InscriptionPelerinage::where('campagne_pelerinage_id', $lockedCampagne->id)
                    ->where('catechumene_id', $catechumeneId)
                    ->where('statut_inscription', '!=', InscriptionPelerinage::STATUT_ANNULEE)
                    ->exists();

                if ($dejaInscrit) {
                    throw new UnprocessableEntityHttpException("Ce catéchumène est déjà inscrit à cette campagne de pèlerinage.");
                }

                $data['nom'] = $data['nom'] ?? $catechumene->nom;
                $data['prenoms'] = $data['prenoms'] ?? $catechumene->prenoms;
                $data['sexe'] = $data['sexe'] ?? $catechumene->sexe ?? 'M';
                $data['date_naissance'] = $data['date_naissance'] ?? $catechumene->date_naissance;
                $data['telephone'] = $data['telephone'] ?? $catechumene->telephone;
                $data['adresse'] = $data['adresse'] ?? $catechumene->adresse;
                $typeParticipant = InscriptionPelerinage::TYPE_CATECHUMENE;
            } else {
                // Participant externe : vérification anti-doublon (nom + prénom + téléphone sur la même campagne)
                if (!empty($data['telephone']) && !empty($data['nom'])) {
                    $dejaInscritExterne = InscriptionPelerinage::where('campagne_pelerinage_id', $lockedCampagne->id)
                        ->where('nom', $data['nom'])
                        ->where('prenoms', $data['prenoms'] ?? '')
                        ->where('telephone', $data['telephone'])
                        ->where('statut_inscription', '!=', InscriptionPelerinage::STATUT_ANNULEE)
                        ->exists();

                    if ($dejaInscritExterne) {
                        throw new UnprocessableEntityHttpException("Un participant externe avec le même nom, prénom et numéro de téléphone est déjà inscrit à cette campagne.");
                    }
                }
            }

            // 5. Initialisation des montants et des statuts
            $montant = (float) $tarif->montant;

            $data['campagne_pelerinage_id'] = $lockedCampagne->id;
            $data['type_participant']       = $typeParticipant;
            $data['reference']              = $this->generateReference($lockedCampagne);
            $data['montant']                = $montant;
            $data['montant_paye']           = 0.00;
            $data['reste_a_payer']          = $montant;
            $data['statut_inscription']     = InscriptionPelerinage::STATUT_EN_ATTENTE;
            $data['statut_participation']   = InscriptionPelerinage::PARTICIPATION_PREVUE;

            if (!empty($data['taille'])) {
                $data['taille'] = strtoupper(trim($data['taille']));
            }

            return InscriptionPelerinage::create($data);
        });
    }

    /**
     * Met à jour les informations d'une inscription.
     */
    public function update(InscriptionPelerinage $inscription, array $data): InscriptionPelerinage
    {
        if (isset($data['tarif_pelerinage_id']) && $data['tarif_pelerinage_id'] != $inscription->tarif_pelerinage_id) {
            $nouveauTarif = $inscription->campagne->tarifs()->where('id', $data['tarif_pelerinage_id'])->first();
            if (!$nouveauTarif) {
                throw new UnprocessableEntityHttpException("Le tarif sélectionné n'appartient pas à cette campagne.");
            }

            // Mettre à jour le montant et recalculer
            $inscription->tarif_pelerinage_id = $nouveauTarif->id;
            $inscription->montant = (float) $nouveauTarif->montant;
            $inscription->recalculerMontants();
        }

        if (array_key_exists('taille', $data)) {
            $data['taille'] = !empty($data['taille']) ? strtoupper(trim($data['taille'])) : null;
        }

        unset($data['campagne_pelerinage_id'], $data['reference'], $data['tarif_pelerinage_id'], $data['montant'], $data['montant_paye'], $data['reste_a_payer']);

        $inscription->update($data);

        return $inscription->fresh(['tarif', 'paiements']);
    }

    /**
     * Annule une inscription (libère la place dans la capacité).
     */
    public function annuler(InscriptionPelerinage $inscription, ?string $motif = null): InscriptionPelerinage
    {
        $updateData = ['statut_inscription' => InscriptionPelerinage::STATUT_ANNULEE];
        if ($motif) {
            $updateData['observation'] = trim(($inscription->observation ?? '') . " [Annulation: {$motif}]");
        }

        $inscription->update($updateData);

        return $inscription->fresh();
    }

    /**
     * Suppression logique d'une inscription.
     */
    public function delete(InscriptionPelerinage $inscription): bool
    {
        if ($inscription->paiements()->where('statut', 'valide')->exists()) {
            throw new UnprocessableEntityHttpException("Impossible de supprimer une inscription ayant des paiements enregistrés. Veuillez l'annuler.");
        }

        return (bool) $inscription->delete();
    }

    /**
     * Opération de génération en masse des inscriptions CATHEO pour une campagne.
     * Transactionnel, respecte les sections canoniques, l'année active et la capacité.
     */
    public function genererInscriptionsCatheo(CampagnePelerinage $campagne, array $options = []): array
    {
        if (in_array($campagne->statut, [CampagnePelerinage::STATUT_CLOTUREE, CampagnePelerinage::STATUT_ANNULEE])) {
            throw new UnprocessableEntityHttpException("Impossible de générer des inscriptions sur une campagne {$campagne->statut}.");
        }

        $organisation = $campagne->organisation;
        $paroisseId = (int) $organisation->paroisse_configuration_id;

        // 1. Détermination des sections autorisées
        $targetCodes = $this->catheoPopulationService->getTargetSectionCodes($organisation->type_organisation);
        if (empty($targetCodes)) {
            throw new UnprocessableEntityHttpException("Aucune section catéchétique n'est associée au type d'organisation [{$organisation->type_organisation}].");
        }

        // 2. Récupération de l'année pastorale active
        $anneeCourante = AnneeCatechese::getAnneeCourante($paroisseId);
        if (!$anneeCourante) {
            throw new UnprocessableEntityHttpException("Aucune année catéchétique active n'a été trouvée pour cette paroisse.");
        }

        // 3. Choix du tarif à appliquer
        $tarifId = $options['tarif_pelerinage_id'] ?? null;
        $tarif = $tarifId
            ? $campagne->tarifs()->where('id', $tarifId)->first()
            : $campagne->tarifs()->where('statut', TarifPelerinage::STATUT_ACTIF)->first();

        if (!$tarif) {
            throw new UnprocessableEntityHttpException("Aucun tarif actif n'est défini pour cette campagne. Veuillez d'abord créer un tarif.");
        }

        // 4. Extraction des catéchumènes concernés
        $query = InscriptionAnnuelle::with('catechumene')
            ->where('paroisse_configuration_id', $paroisseId)
            ->where('annee_catechese_id', $anneeCourante->id)
            ->whereHas('section', function ($q) use ($targetCodes) {
                $q->where(function ($sub) use ($targetCodes) { $sub->whereIn('code', $targetCodes)->orWhere('code', 'like', 'SEC-ENF%')->orWhere('nom', 'like', '%primaire%')->orWhere('nom', 'like', '%collège%'); });
            });

        if (!empty($options['niveau_id'])) {
            $query->where('niveau_id', $options['niveau_id']);
        }
        if (!empty($options['classe_id'])) {
            $query->where('classe_id', $options['classe_id']);
        }

        $inscriptionsAnnuelles = $query->get();

        return DB::transaction(function () use ($campagne, $inscriptionsAnnuelles, $tarif) {
            $totalTrouve = $inscriptionsAnnuelles->count();
            $totalCrees = 0;
            $totalDejaInscrits = 0;
            $totalIgnores = 0;
            $totalErreurs = 0;

            // Inscriptions existantes non annulées pour cette campagne
            $existingCatechumeneIds = InscriptionPelerinage::where('campagne_pelerinage_id', $campagne->id)
                ->where('statut_inscription', '!=', InscriptionPelerinage::STATUT_ANNULEE)
                ->whereNotNull('catechumene_id')
                ->pluck('catechumene_id')
                ->all();

            $existingLookup = array_flip($existingCatechumeneIds);

            foreach ($inscriptionsAnnuelles as $insAnnuelle) {
                $catechumene = $insAnnuelle->catechumene;

                if (!$catechumene) {
                    $totalErreurs++;
                    continue;
                }

                if (isset($existingLookup[$catechumene->id])) {
                    $totalDejaInscrits++;
                    continue;
                }

                // Contrôle de capacité
                if ($campagne->capacite && ($campagne->placesOccupees() + $totalCrees) >= $campagne->capacite) {
                    $totalIgnores++;
                    continue;
                }

                InscriptionPelerinage::create([
                    'campagne_pelerinage_id'    => $campagne->id,
                    'tarif_pelerinage_id'       => $tarif->id,
                    'catechumene_id'            => $catechumene->id,
                    'type_participant'         => InscriptionPelerinage::TYPE_CATECHUMENE,
                    'reference'                 => $this->generateReference($campagne),
                    'nom'                       => $catechumene->nom,
                    'prenoms'                   => $catechumene->prenoms,
                    'sexe'                      => $catechumene->sexe ?? 'M',
                    'date_naissance'            => $catechumene->date_naissance,
                    'telephone'                 => $catechumene->telephone,
                    'email'                     => $catechumene->email ?? null,
                    'adresse'                   => $catechumene->adresse,
                    'montant'                   => (float) $tarif->montant,
                    'montant_paye'              => 0.00,
                    'reste_a_payer'             => (float) $tarif->montant,
                    'statut_inscription'        => InscriptionPelerinage::STATUT_EN_ATTENTE,
                    'statut_participation'      => InscriptionPelerinage::PARTICIPATION_PREVUE,
                ]);

                $totalCrees++;
            }

            return [
                'campagne_id'         => $campagne->id,
                'campagne_nom'        => $campagne->nom,
                'tarif_applique'      => [
                    'id'      => $tarif->id,
                    'libelle' => $tarif->libelle,
                    'montant' => (float) $tarif->montant,
                ],
                'nombre_trouve'       => $totalTrouve,
                'nombre_cree'         => $totalCrees,
                'nombre_deja_existant'=> $totalDejaInscrits,
                'nombre_ignore'       => $totalIgnores,
                'nombre_erreur'       => $totalErreurs,
            ];
        });
    }

    /**
     * Recherche les catéchumènes de la paroisse éligibles pour cette campagne
     * avec indication si déjà inscrits ou non.
     */
    public function searchParticipantsCatheo(CampagnePelerinage $campagne, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $organisation = $campagne->organisation;
        $paginator = $this->catheoPopulationService->getPopulation($organisation, $filters, $perPage);

        // Enrichir chaque élément avec l'information d'inscription à cette campagne
        $catechumeneIds = $paginator->getCollection()->pluck('catechumene_id')->all();

        $inscritsLookup = InscriptionPelerinage::where('campagne_pelerinage_id', $campagne->id)
            ->whereIn('catechumene_id', $catechumeneIds)
            ->where('statut_inscription', '!=', InscriptionPelerinage::STATUT_ANNULEE)
            ->pluck('statut_inscription', 'catechumene_id')
            ->all();

        $paginator->getCollection()->transform(function ($item) use ($inscritsLookup) {
            $catId = $item->catechumene_id;
            $item->deja_inscrit = isset($inscritsLookup[$catId]);
            $item->statut_pelerinage = $inscritsLookup[$catId] ?? null;
            return $item;
        });

        return $paginator;
    }
}
