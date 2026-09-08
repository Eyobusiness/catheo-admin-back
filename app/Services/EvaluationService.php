<?php

namespace App\Services;

use App\DTO\Evaluation\BatchNoteItemDTO;
use App\DTO\Evaluation\CreateEvaluationDTO;
use App\DTO\Evaluation\MoyenneItemDTO;
use App\DTO\Evaluation\SaveNotesBatchDTO;
use App\DTO\Evaluation\UpdateEvaluationDTO;
use App\Models\AffectationAnimateur;
use App\Models\Animateur;
use App\Models\AnneeCatechese;
use App\Models\CatecheseConfiguration;
use App\Models\Catechumene;
use App\Models\Classe;
use App\Models\Evaluation;
use App\Models\InscriptionAnnuelle;
use App\Models\ModuleTrimestriel;
use App\Models\Niveau;
use App\Models\Note;
use App\Models\Section;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EvaluationService
{
    /**
     * Liste des évaluations filtrée selon le contexte pastoral (Session, Niveau, Classe, Année).
     *
     * @param User|Animateur $user
     * @param array $filters
     * @return Collection
     */
    public function getEvaluations(mixed $user, array $filters = []): Collection
    {
        $paroisseId = $user->paroisse_configuration_id ?? CatecheseConfiguration::first()?->id;

        $query = Evaluation::with([
            'anneeCatechese',
            'moduleTrimestriel',
            'classe.niveau.section',
            'notes.catechumene',
        ])->where('paroisse_configuration_id', $paroisseId);

        // 1. Restriction de sécurité pour les Animateurs (Enseignants)
        if ($user instanceof Animateur) {
            $assignedClasseIds = $this->getAnimateurAssignedClasseIds($user->id);
            
            // Si l'animateur spécifie une classe, vérifier qu'elle lui est autorisée
            if (!empty($filters['classe_id']) || !empty($filters['classe'])) {
                $requestedClasseParam = $filters['classe_id'] ?? $filters['classe'];
                $requestedClasseId = $this->resolveClasseId($requestedClasseParam, $paroisseId);
                
                if ($requestedClasseId && !in_array($requestedClasseId, $assignedClasseIds)) {
                    abort(response()->json([
                        'status'  => 'error',
                        'message' => 'Accès refusé. Vous n\'êtes pas affecté à cette classe.',
                    ], 403));
                }
            }

            // Restreindre la requête globale à ses classes assignées
            $query->whereIn('classe_id', $assignedClasseIds);
        }

        // 2. Filtre Année Pastorale
        $anneeParam = $filters['annee_catechese_id'] ?? $filters['anneePastorale'] ?? $filters['annee_pastorale'] ?? null;
        if ($anneeParam) {
            $anneeId = $this->resolveAnneeId($anneeParam, $paroisseId);
            if ($anneeId) {
                $query->where('annee_catechese_id', $anneeId);
            }
        }

        // 3. Filtre Session (Section : Enfants, Jeunes, Adultes)
        $sectionParam = $filters['section_id'] ?? $filters['session_id'] ?? $filters['section'] ?? $filters['session'] ?? null;
        if ($sectionParam) {
            $sectionId = $this->resolveSectionId($sectionParam, $paroisseId);
            if ($sectionId) {
                $query->whereHas('classe.niveau', function (Builder $q) use ($sectionId) {
                    $q->where('section_id', $sectionId);
                });
            }
        }

        // 4. Filtre Niveau
        $niveauParam = $filters['niveau_id'] ?? $filters['niveau'] ?? null;
        if ($niveauParam) {
            $niveauId = $this->resolveNiveauId($niveauParam, $paroisseId);
            if ($niveauId) {
                $query->whereHas('classe', function (Builder $q) use ($niveauId) {
                    $q->where('niveau_id', $niveauId);
                });
            }
        }

        // 5. Filtre Classe
        $classeParam = $filters['classe_id'] ?? $filters['classe'] ?? null;
        if ($classeParam) {
            $classeId = $this->resolveClasseId($classeParam, $paroisseId);
            if ($classeId) {
                $query->where('classe_id', $classeId);
            }
        }

        // 6. Filtre Type d'évaluation
        $type = $filters['type_eval'] ?? $filters['type'] ?? null;
        if ($type && strtolower($type) !== 'tous') {
            $query->where('type_eval', strtolower($type));
        }

        // 7. Filtre Statut
        $statut = $filters['statut'] ?? $filters['status'] ?? null;
        if ($statut && strtolower($statut) !== 'tous') {
            $st = strtolower($statut) === 'inactif' ? 'inactif' : 'actif';
            $query->where('statut', $st);
        }

        // 8. Recherche textuelle
        if (!empty($filters['search'])) {
            $search = trim($filters['search']);
            $query->where(function (Builder $q) use ($search) {
                $q->where('titre', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('date_evaluation', 'desc')->get();
    }

    /**
     * Création d'une évaluation avec contrôle strict des contextes et permissions.
     */
    public function createEvaluation(mixed $user, CreateEvaluationDTO $dto): Evaluation
    {
        $paroisseId = $user->paroisse_configuration_id ?? CatecheseConfiguration::first()?->id;

        // Contrôle d'accès paroisse
        if ($dto->paroisseConfigurationId !== $paroisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé pour cette paroisse.'], 403));
        }

        // Contrôle d'accès classe pour l'enseignant
        if ($user instanceof Animateur) {
            if (!$dto->classeId || !$this->isAnimateurAssignedToClasse($user->id, $dto->classeId)) {
                abort(response()->json([
                    'status'  => 'error',
                    'message' => 'Accès refusé. Vous n\'êtes pas autorisé à créer une évaluation dans cette classe.',
                ], 403));
            }
        }

        $evaluation = Evaluation::create($dto->toArray());
        $evaluation->load(['anneeCatechese', 'moduleTrimestriel', 'classe.niveau.section', 'notes.catechumene']);

        return $evaluation;
    }

    /**
     * Mise à jour d'une évaluation.
     */
    public function updateEvaluation(mixed $user, Evaluation $evaluation, UpdateEvaluationDTO $dto): Evaluation
    {
        $this->authorizeAccess($user, $evaluation);

        // Si la classe est modifiée, vérifier l'autorisation de la nouvelle classe
        if ($dto->classeId && $dto->classeId !== $evaluation->classe_id && $user instanceof Animateur) {
            if (!$this->isAnimateurAssignedToClasse($user->id, $dto->classeId)) {
                abort(response()->json([
                    'status'  => 'error',
                    'message' => 'Accès refusé pour cette nouvelle classe.',
                ], 403));
            }
        }

        $updateData = $dto->toFilteredArray();
        if (!empty($updateData)) {
            $evaluation->update($updateData);
            $evaluation->refresh();
        }

        $evaluation->load(['anneeCatechese', 'moduleTrimestriel', 'classe.niveau.section', 'notes.catechumene']);
        return $evaluation;
    }

    /**
     * Basculement du statut (actif/inactif).
     */
    public function toggleStatus(mixed $user, Evaluation $evaluation, ?string $nouveauStatut = null): Evaluation
    {
        $this->authorizeAccess($user, $evaluation);

        if ($nouveauStatut) {
            $st = strtolower($nouveauStatut) === 'inactif' ? 'inactif' : 'actif';
        } else {
            $st = ($evaluation->statut === 'actif') ? 'inactif' : 'actif';
        }

        $evaluation->update(['statut' => $st]);
        $evaluation->refresh();
        $evaluation->load(['anneeCatechese', 'moduleTrimestriel', 'classe.niveau.section', 'notes.catechumene']);

        return $evaluation;
    }

    /**
     * Suppression sécurisée d'une évaluation avec cascade transactionnelle sur les notes.
     */
    public function deleteEvaluation(mixed $user, Evaluation $evaluation): void
    {
        $this->authorizeAccess($user, $evaluation);

        DB::transaction(function () use ($evaluation) {
            $evaluation->notes()->delete();
            $evaluation->delete();
        });
    }

    /**
     * Grille complète des catéchumènes de la classe pour la saisie des notes.
     */
    public function getNotesGrid(mixed $user, Evaluation $evaluation, ?string $search = null): array
    {
        $this->authorizeAccess($user, $evaluation);

        $paroisseId = $evaluation->paroisse_configuration_id;

        $inscriptionsQuery = InscriptionAnnuelle::with('catechumene')
            ->where('paroisse_configuration_id', $paroisseId)
            ->where('classe_id', $evaluation->classe_id)
            ->where('statut_inscription', '!=', 'annulee');

        if ($evaluation->annee_catechese_id) {
            $inscriptionsQuery->where('annee_catechese_id', $evaluation->annee_catechese_id);
        }

        $inscriptions = $inscriptionsQuery->get();

        $existingNotes = Note::where('evaluation_id', $evaluation->id)
            ->where('paroisse_configuration_id', $paroisseId)
            ->get()
            ->keyBy('catechumene_id');

        $grid = $inscriptions->map(function (InscriptionAnnuelle $inscr) use ($evaluation, $existingNotes) {
            $cat = $inscr->catechumene;
            $note = $cat ? $existingNotes->get($cat->id) : null;
            $noteVal = $note ? (float) $note->note_obtenue : null;
            $appr = $note ? ($note->appreciation ?: Evaluation::calculateAppreciation($noteVal, (float) $evaluation->note_max)) : null;

            return [
                'catechumene_id'   => $cat?->uuid,
                'catechumeneId'    => $cat?->uuid,
                'matricule'        => $cat?->matricule,
                'code_catechumene' => $cat?->matricule,
                'nom'              => $cat?->nom,
                'prenoms'          => $cat?->prenoms,
                'nom_prenoms'      => $cat ? trim("{$cat->nom} {$cat->prenoms}") : null,
                'nomPrenoms'       => $cat ? trim("{$cat->nom} {$cat->prenoms}") : null,
                'note_obtenue'     => $noteVal,
                'note'             => $noteVal,
                'appreciation'     => $appr,
                'note_id'          => $note?->uuid,
            ];
        });

        if (!empty($search)) {
            $searchTerm = strtolower(trim($search));
            $grid = $grid->filter(function ($item) use ($searchTerm) {
                return str_contains(strtolower($item['matricule'] ?? ''), $searchTerm)
                    || str_contains(strtolower($item['nom_prenoms'] ?? ''), $searchTerm);
            })->values();
        }

        return $grid->values()->toArray();
    }

    /**
     * Saisie en lot des notes avec validation stricte (0 <= note <= note_max) et transaction DB.
     */
    public function saveBatchNotes(mixed $user, Evaluation $evaluation, SaveNotesBatchDTO $batchDto): Evaluation
    {
        $this->authorizeAccess($user, $evaluation);

        $paroisseId = $evaluation->paroisse_configuration_id;
        $noteMax = (float) $evaluation->note_max;

        // Validation préalable de chaque note soumise
        foreach ($batchDto->items as $item) {
            if ($item->noteObtenue !== null) {
                if ($item->noteObtenue < 0) {
                    throw ValidationException::withMessages([
                        'notes' => ["La note ne peut pas être négative ({$item->noteObtenue})."],
                    ]);
                }
                if ($item->noteObtenue > $noteMax) {
                    throw ValidationException::withMessages([
                        'notes' => ["La note ({$item->noteObtenue}) dépasse la note maximale autorisée ({$noteMax}) pour cette évaluation."],
                    ]);
                }
            }
        }

        DB::transaction(function () use ($paroisseId, $evaluation, $batchDto, $noteMax) {
            foreach ($batchDto->items as $item) {
                $catId = $item->catechumeneIdentifier;
                if (!$catId) {
                    continue;
                }

                $catechumene = Catechumene::where('paroisse_configuration_id', $paroisseId)
                    ->where(function ($q) use ($catId) {
                        $q->where('uuid', $catId)
                          ->orWhere('matricule', $catId)
                          ->orWhere('id', $catId);
                    })->first();

                if (!$catechumene) {
                    continue;
                }

                // Si la note est vidée (null), suppression de la note existante
                if ($item->noteObtenue === null) {
                    Note::where('evaluation_id', $evaluation->id)
                        ->where('catechumene_id', $catechumene->id)
                        ->delete();
                    continue;
                }

                $appr = $item->appreciation ?: Evaluation::calculateAppreciation($item->noteObtenue, $noteMax);

                Note::updateOrCreate(
                    [
                        'paroisse_configuration_id' => $paroisseId,
                        'evaluation_id'             => $evaluation->id,
                        'catechumene_id'            => $catechumene->id,
                    ],
                    [
                        'note_obtenue' => $item->noteObtenue,
                        'appreciation' => $appr,
                    ]
                );
            }
        });

        $evaluation->load(['anneeCatechese', 'moduleTrimestriel', 'classe.niveau.section', 'notes.catechumene']);
        return $evaluation;
    }

    /**
     * Calcul automatique des moyennes de tous les catéchumènes d'une classe.
     * Règle métier :
     * - Backend = source de vérité unique
     * - Moyenne pondérée selon les coefficients des évaluations réellement notées
     * - Évaluations manquantes / non notées = ignorées (ne comptent PAS pour 0)
     * - Aucune note = null ("Non évalué")
     * - Arrondi à 2 décimales
     *
     * @param User|Animateur $user
     * @param string|int $classeIdentifier
     * @param string|null $anneeIdentifier
     * @return array
     */
    public function getClasseMoyennes(mixed $user, string|int $classeIdentifier, ?string $anneeIdentifier = null, ?string $periodeIdentifier = null): array
    {
        $paroisseId = $user->paroisse_configuration_id ?? CatecheseConfiguration::first()?->id;

        $classe = Classe::with(['niveau.section', 'anneeCatechese'])
            ->where('paroisse_configuration_id', $paroisseId)
            ->where(function ($q) use ($classeIdentifier) {
                $q->where('uuid', $classeIdentifier)
                  ->orWhere('id', $classeIdentifier)
                  ->orWhere('nom', $classeIdentifier);
            })->firstOrFail();

        // Contrôle d'accès classe pour l'enseignant
        if ($user instanceof Animateur && !$this->isAnimateurAssignedToClasse($user->id, $classe->id)) {
            abort(response()->json([
                'status'  => 'error',
                'message' => 'Accès refusé. Vous n\'êtes pas assigné à cette classe.',
            ], 403));
        }

        $anneeId = null;
        if ($anneeIdentifier) {
            $anneeId = $this->resolveAnneeId($anneeIdentifier, $paroisseId);
        }
        if (!$anneeId) {
            $anneeId = $classe->annee_catechese_id ?? AnneeCatechese::getAnneeCourante($paroisseId)?->id;
        }

        // 1. Récupérer toutes les évaluations actives de la classe pour cette année
                $moduleId = null;
        $selectedModule = null;
        if (!empty($periodeIdentifier) && $periodeIdentifier !== "toutes" && $periodeIdentifier !== "all") {
            $selectedModule = ModuleTrimestriel::where("paroisse_configuration_id", $paroisseId)
                ->where(function ($q) use ($periodeIdentifier) {
                    $q->where("uuid", $periodeIdentifier)
                      ->orWhere("id", $periodeIdentifier)
                      ->orWhere("nom", $periodeIdentifier);
                })->first();
            if (!$selectedModule && is_numeric($periodeIdentifier)) {
                $selectedModule = ModuleTrimestriel::where("paroisse_configuration_id", $paroisseId)
                    ->where("numero_trimestre", (int) $periodeIdentifier)
                    ->first();
            }
            $moduleId = $selectedModule?->id;
        }

        $evaluations = Evaluation::with("moduleTrimestriel")->where("paroisse_configuration_id", $paroisseId)
            ->where("classe_id", $classe->id)
            ->when($anneeId, fn($q) => $q->where("annee_catechese_id", $anneeId))
            ->when($moduleId, fn($q) => $q->where("module_trimestriel_id", $moduleId))
            ->where("statut", "actif")
            ->orderBy("date_evaluation", "asc")
            ->get();

        $totalEvaluations = $evaluations->count();
        $evalIds = $evaluations->pluck('id');

        // 2. Récupérer les catéchumènes inscrits dans cette classe
        $inscriptions = InscriptionAnnuelle::with('catechumene')
            ->where('paroisse_configuration_id', $paroisseId)
            ->where('classe_id', $classe->id)
            ->when($anneeId, fn($q) => $q->where('annee_catechese_id', $anneeId))
            ->where('statut_inscription', '!=', 'annulee')
            ->get();

        // 3. Récupérer toutes les notes de ces évaluations
        $notesByCatechumene = Note::whereIn('evaluation_id', $evalIds)
            ->where('paroisse_configuration_id', $paroisseId)
            ->get()
            ->groupBy('catechumene_id');

        $resultats = [];
        $sommeMoyennesClasse = 0.0;
        $elevesAvecMoyenne = 0;
        $meilleureMoyenne = null;
        $faibleMoyenne = null;

        foreach ($inscriptions as $insc) {
            $cat = $insc->catechumene;
            if (!$cat) continue;

            $notesEleve = $notesByCatechumene->get($cat->id, collect());
            $detailsNotes = [];

            $sommePonderee = 0.0;
            $sommeCoeff = 0.0;
            $nombreNotesSaisies = 0;

            foreach ($evaluations as $eval) {
                $noteObj = $notesEleve->firstWhere('evaluation_id', $eval->id);
                $noteVal = $noteObj ? (float) $noteObj->note_obtenue : null;
                $coeff = (float) $eval->coefficient;
                $noteMax = (float) $eval->note_max;

                if ($noteVal !== null) {
                    $nombreNotesSaisies++;

                    // Normalisation sur 20 si le barème diffère de 20
                    $noteSur20 = ($noteMax > 0 && $noteMax != 20.0) ? ($noteVal / $noteMax) * 20.0 : $noteVal;

                    $sommePonderee += ($noteSur20 * $coeff);
                    $sommeCoeff += $coeff;

                    $detailsNotes[] = [
                        'evaluation_id'    => $eval->uuid,
                        'titre'            => $eval->titre,
                        'coefficient'      => $coeff,
                        'note_obtenue'     => $noteVal,
                        'note_max'         => $noteMax,
                        'note_sur_20'      => round($noteSur20, 2),
                        'appreciation'     => $noteObj->appreciation ?: Evaluation::calculateAppreciation($noteVal, $noteMax),
                    ];
                } else {
                    // Évaluation non notée
                    $detailsNotes[] = [
                        'evaluation_id'    => $eval->uuid,
                        'titre'            => $eval->titre,
                        'coefficient'      => $coeff,
                        'note_obtenue'     => null,
                        'note_max'         => $noteMax,
                        'note_sur_20'      => null,
                        'appreciation'     => 'Non noté',
                    ];
                }
            }

            // Calcul de la moyenne de l'élève
            $moyenneFinale = null;
            $appreciationFinale = 'Non évalué';

            if ($sommeCoeff > 0 && $nombreNotesSaisies > 0) {
                $moyenneFinale = round($sommePonderee / $sommeCoeff, 2);
                $appreciationFinale = Evaluation::calculateAppreciation($moyenneFinale, 20.0);

                $sommeMoyennesClasse += $moyenneFinale;
                $elevesAvecMoyenne++;

                if ($meilleureMoyenne === null || $moyenneFinale > $meilleureMoyenne) {
                    $meilleureMoyenne = $moyenneFinale;
                }
                if ($faibleMoyenne === null || $moyenneFinale < $faibleMoyenne) {
                    $faibleMoyenne = $moyenneFinale;
                }
            }

            $dto = new MoyenneItemDTO(
                catechumeneId: $cat->uuid,
                matricule: $cat->matricule,
                nomPrenoms: $cat->nom_complet,
                nombreEvaluations: $totalEvaluations,
                nombreNotes: $nombreNotesSaisies,
                moyenne: $moyenneFinale,
                appreciation: $appreciationFinale,
                detailsNotes: $detailsNotes
            );

            $resultats[] = $dto->toArray();
        }

        $moyenneClasse = $elevesAvecMoyenne > 0 ? round($sommeMoyennesClasse / $elevesAvecMoyenne, 2) : null;

        return [
            'classe' => [
                'id'         => $classe->uuid,
                'nom'        => $classe->nom,
                'niveau'     => $classe->niveau?->nom,
                'section'    => $classe->niveau?->section?->nom,
                'session'    => $classe->niveau?->section?->nom,
                'annee'      => $classe->anneeCatechese?->libelle,
            ],
            'statistiques' => [
                'total_evaluations'   => $totalEvaluations,
                'total_eleves'        => $inscriptions->count(),
                'eleves_evalues'      => $elevesAvecMoyenne,
                'eleves_non_evalues'  => $inscriptions->count() - $elevesAvecMoyenne,
                'moyenne_classe'      => $moyenneClasse,
                'meilleure_moyenne'   => $meilleureMoyenne,
                'plus_faible_moyenne' => $faibleMoyenne,
            ],
            'evaluations' => $evaluations->map(fn($e) => [
                'id'                    => $e->uuid,
                'titre'                 => $e->titre,
                'type_eval'             => $e->type_eval,
                'coefficient'           => (float) $e->coefficient,
                'note_max'              => (float) $e->note_max,
                'date'                  => $e->date_evaluation?->format('Y-m-d'),
                'module_trimestriel_id' => $e->module_trimestriel_id,
                'periode'               => $e->moduleTrimestriel?->nom ?? 'Trimestre 1',
                'trimestre'             => $e->moduleTrimestriel?->nom ?? 'Trimestre 1',
            ])->toArray(),
            'trimestre' => $selectedModule?->nom ?? ($evaluations->first()?->moduleTrimestriel?->nom ?? ($periodeIdentifier ?: 'Trimestre 1')),
            'eleves' => $resultats,
        ];
    }

    /**
     * Synthèse individuelle des évaluations d'un catéchumène pour son bulletin de notes.
     */
    public function getCatechumeneSynthese(mixed $user, string|int $catechumeneIdentifier, ?string $anneeIdentifier = null): array
    {
        $paroisseId = $user->paroisse_configuration_id ?? CatecheseConfiguration::first()?->id;

        $catechumene = Catechumene::where('paroisse_configuration_id', $paroisseId)
            ->where(function ($q) use ($catechumeneIdentifier) {
                $q->where('uuid', $catechumeneIdentifier)
                  ->orWhere('matricule', $catechumeneIdentifier)
                  ->orWhere('id', $catechumeneIdentifier);
            })->firstOrFail();

        // Récupérer l'inscription
        $inscrQuery = InscriptionAnnuelle::with(['classe.niveau.section', 'anneeCatechese'])
            ->where('catechumene_id', $catechumene->id)
            ->where('paroisse_configuration_id', $paroisseId)
            ->where('statut_inscription', '!=', 'annulee');

        if ($anneeIdentifier) {
            $anneeId = $this->resolveAnneeId($anneeIdentifier, $paroisseId);
            if ($anneeId) {
                $inscrQuery->where('annee_catechese_id', $anneeId);
            }
        }

        $inscription = $inscrQuery->latest('id')->first();
        $classe = $inscription?->classe;

        if ($classe && $user instanceof Animateur && !$this->isAnimateurAssignedToClasse($user->id, $classe->id)) {
            abort(response()->json([
                'status'  => 'error',
                'message' => 'Accès refusé. Vous n\'êtes pas l\'enseignant de cet élève.',
            ], 403));
        }

        if (!$classe) {
            return [
                'catechumene' => [
                    'id'          => $catechumene->uuid,
                    'matricule'   => $catechumene->matricule,
                    'nom_complet' => $catechumene->nom_complet,
                ],
                'classe'      => null,
                'evaluations' => [],
                'moyenne'     => null,
                'appreciation'=> 'Non inscrit dans une classe active',
            ];
        }

        $moyennesData = $this->getClasseMoyennes($user, $classe->id, $inscription?->annee_catechese_id);
        $eleveData = collect($moyennesData['eleves'])->firstWhere('catechumene_id', $catechumene->uuid);

        return [
            'catechumene'  => [
                'id'          => $catechumene->uuid,
                'matricule'   => $catechumene->matricule,
                'nom_complet' => $catechumene->nom_complet,
            ],
            'classe'       => $moyennesData['classe'],
            'moyenne'      => $eleveData['moyenne'] ?? null,
            'appreciation' => $eleveData['appreciation'] ?? 'Non évalué',
            'notes'        => $eleveData['details_notes'] ?? [],
        ];
    }

    /**
     * Contrôle d'autorisation multi-tenant et affectation enseignant.
     */
    private function authorizeAccess(mixed $user, Evaluation $evaluation): void
    {
        $userParoisseId = $user->paroisse_configuration_id;
        if ($userParoisseId && $userParoisseId !== $evaluation->paroisse_configuration_id) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé pour cette paroisse.'], 403));
        }

        if ($user instanceof Animateur) {
            if (!$this->isAnimateurAssignedToClasse($user->id, $evaluation->classe_id)) {
                abort(response()->json([
                    'status'  => 'error',
                    'message' => 'Accès refusé. Vous n\'êtes pas affecté à la classe de cette évaluation.',
                ], 403));
            }
        }
    }

    /**
     * Retourne la liste des IDs de classes auxquelles l'animateur est affecté.
     */
    public function getAnimateurAssignedClasseIds(int $animateurId): array
    {
        return AffectationAnimateur::where('animateur_id', $animateurId)
            ->pluck('classe_id')
            ->toArray();
    }

    /**
     * Vérifie si un animateur est assigné à une classe.
     */
    public function isAnimateurAssignedToClasse(int $animateurId, ?int $classeId): bool
    {
        if (!$classeId) {
            return false;
        }

        return AffectationAnimateur::where('animateur_id', $animateurId)
            ->where('classe_id', $classeId)
            ->exists();
    }

    private function resolveAnneeId(string|int $identifier, int $paroisseId): ?int
    {
        return AnneeCatechese::where('paroisse_configuration_id', $paroisseId)
            ->where(function ($q) use ($identifier) {
                $q->where('uuid', $identifier)
                  ->orWhere('libelle', $identifier)
                  ->orWhere('id', $identifier);
            })->value('id');
    }

    private function resolveSectionId(string|int $identifier, int $paroisseId): ?int
    {
        return Section::where('paroisse_configuration_id', $paroisseId)
            ->where(function ($q) use ($identifier) {
                $q->where('uuid', $identifier)
                  ->orWhere('code', $identifier)
                  ->orWhere('nom', $identifier)
                  ->orWhere('id', $identifier);
            })->value('id');
    }

    private function resolveNiveauId(string|int $identifier, int $paroisseId): ?int
    {
        return Niveau::where('paroisse_configuration_id', $paroisseId)
            ->where(function ($q) use ($identifier) {
                $q->where('uuid', $identifier)
                  ->orWhere('nom', $identifier)
                  ->orWhere('id', $identifier);
            })->value('id');
    }

    public function resolveClasseId(string|int $identifier, int $paroisseId): ?int
    {
        return Classe::where('paroisse_configuration_id', $paroisseId)
            ->where(function ($q) use ($identifier) {
                $q->where('uuid', $identifier)
                  ->orWhere('nom', $identifier)
                  ->orWhere('id', $identifier);
            })->value('id');
    }

    public function resolveModuleId(string|int $identifier, int $paroisseId): ?int
    {
        return ModuleTrimestriel::where('paroisse_configuration_id', $paroisseId)
            ->where(function ($q) use ($identifier) {
                $q->where('uuid', $identifier)
                  ->orWhere('nom', $identifier)
                  ->orWhere('id', $identifier);
            })->value('id');
    }
}
