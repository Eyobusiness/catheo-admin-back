<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCatechumeneRequest;
use App\Http\Requests\Api\V1\UpdateCatechumeneRequest;
use App\Http\Resources\Api\V1\CatecheseConfigurationResource;
use App\Http\Resources\Api\V1\CatechumeneResource;
use App\Models\AnneeCatechese;
use App\Models\CatecheseConfiguration;
use App\Models\Ceb;
use App\Models\Classe;
use App\Models\Catechumene;
use App\Models\Niveau;
use App\Models\Section;
use App\Models\InscriptionAnnuelle;
use App\Services\ParoisseHeaderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class CatechumeneController extends Controller
{
    /**
     * Liste filtrée et paginée des catéchumènes de la paroisse par Section, Niveau, Classe et Année.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $paroisseId = $user?->paroisse_configuration_id 
            ?? $request->input('paroisse_configuration_id')
            ?? $request->input('paroisse_id')
            ?? $request->header('X-Paroisse-Id')
            ?? $request->header('X-Paroisse-Configuration-Id');

        $query = Catechumene::with([
            'inscriptionsAnnuelles' => function ($q) {
                $q->latest('id');
            },
            'inscriptionsAnnuelles.anneeCatechese',
            'inscriptionsAnnuelles.section',
            'inscriptionsAnnuelles.niveau.section',
            'inscriptionsAnnuelles.classe',
            'ceb',
            'parrainsMarraines',
        ]);

        if ($paroisseId) {
            $query->where('paroisse_configuration_id', $paroisseId);
        } elseif ($user && !$user->paroisse_configuration_id && ($user->user_type === 'super_admin' || $user->profil?->code === 'SUPER_ADMIN')) {
            // Super Administrateur sans filtre de paroisse spécifique : accès global
        } else {
            // Pas de paroisse identifiée : aucun résultat pour éviter toute fuite inter-paroisses
            $query->whereRaw('1 = 0');
        }

        if ($request->filled('statut') && !in_array(strtolower($request->statut), ['all', 'tous', 'undefined', 'null'])) {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('annee_catechese_id') && !in_array(strtolower($request->annee_catechese_id), ['all', 'tous', 'undefined', 'null'])) {
            $val = $request->annee_catechese_id;
            $anneeId = is_numeric($val) ? (int) $val : AnneeCatechese::where('uuid', $val)->value('id');
            if ($anneeId) {
                $query->whereHas('inscriptionsAnnuelles', function ($q) use ($anneeId) {
                    $q->where('annee_catechese_id', $anneeId)
                      ->where('statut_inscription', '!=', 'annulee');
                });
            }
        }

        if ($request->filled('section_id') && !in_array(strtolower($request->section_id), ['all', 'tous', 'undefined', 'null'])) {
            $val = $request->section_id;
            $sectionId = is_numeric($val) ? (int) $val : Section::where('uuid', $val)->value('id');
            if ($sectionId) {
                $query->whereHas('inscriptionsAnnuelles', function ($q) use ($sectionId) {
                    $q->where('section_id', $sectionId);
                });
            }
        }

        if ($request->filled('niveau_id') && !in_array(strtolower($request->niveau_id), ['all', 'tous', 'undefined', 'null'])) {
            $val = $request->niveau_id;
            $niveauId = is_numeric($val) ? (int) $val : Niveau::where('uuid', $val)->value('id');
            if ($niveauId) {
                $query->whereHas('inscriptionsAnnuelles', function ($q) use ($niveauId) {
                    $q->where('niveau_id', $niveauId);
                });
            }
        }

        if ($request->filled('classe_id') && !in_array(strtolower($request->classe_id), ['all', 'tous', 'undefined', 'null'])) {
            $val = $request->classe_id;
            $classeId = is_numeric($val) ? (int) $val : Classe::where('uuid', $val)->value('id');
            if ($classeId) {
                $query->whereHas('inscriptionsAnnuelles', function ($q) use ($classeId) {
                    $q->where('classe_id', $classeId);
                });
            }
        }

        if ($request->filled('ceb_id') && !in_array(strtolower($request->ceb_id), ['all', 'tous', 'undefined', 'null'])) {
            $val = $request->ceb_id;
            $cebId = is_numeric($val) ? (int) $val : Ceb::where('uuid', $val)->value('id');
            if ($cebId) {
                $query->where('ceb_id', $cebId);
            }
        }

        // Filtre selon le statut de paiement des frais d'inscription
        if ($request->boolean('payes_uniquement') || $request->input('statut_paiement') === 'paye') {
            $query->whereHas('inscriptionsAnnuelles', function ($q) {
                $q->where('frais_inscription_payes', true);
            });
        } elseif ($request->input('statut_paiement') === 'non_paye' || $request->input('statut_paiement') === 'en_attente') {
            $query->whereHas('inscriptionsAnnuelles', function ($q) {
                $q->where('frais_inscription_payes', false);
            });
        }

        $search = $request->input('search')
            ?? $request->input('q')
            ?? $request->input('query')
            ?? $request->input('terme')
            ?? $request->input('term');

        if (!empty($search)) {
            $search = trim($search);
            $isSqlite = \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'sqlite';
            $concat1 = $isSqlite ? "(nom || ' ' || prenoms)" : "CONCAT(nom, ' ', prenoms)";
            $concat2 = $isSqlite ? "(prenoms || ' ' || nom)" : "CONCAT(prenoms, ' ', nom)";

            $query->where(function ($q) use ($search, $concat1, $concat2) {
                $q->where('nom', 'like', "{$search}%")
                  ->orWhere('nom', 'like', "%{$search}%")
                  ->orWhere('prenoms', 'like', "{$search}%")
                  ->orWhere('prenoms', 'like', "%{$search}%")
                  ->orWhere('matricule', 'like', "{$search}%")
                  ->orWhere('matricule', 'like', "%{$search}%")
                  ->orWhere('uuid', $search)
                  ->orWhere('telephone', 'like', "%{$search}%")
                  ->orWhere('telephone_pere', 'like', "%{$search}%")
                  ->orWhere('telephone_mere', 'like', "%{$search}%")
                  ->orWhere('telephone_tuteur', 'like', "%{$search}%");

                $q->orWhereRaw("{$concat1} LIKE ?", ["%{$search}%"])
                  ->orWhereRaw("{$concat2} LIKE ?", ["%{$search}%"]);

                $words = preg_split('/\s+/', $search);
                if (count($words) > 1) {
                    $q->orWhere(function ($subQ) use ($words) {
                        foreach ($words as $word) {
                            $subQ->where(function ($wQ) use ($word) {
                                $wQ->where('nom', 'like', "%{$word}%")
                                   ->orWhere('prenoms', 'like', "%{$word}%")
                                   ->orWhere('matricule', 'like', "%{$word}%");
                            });
                        }
                    });
                }
            });

            // Prioritiser les resultats qui commencent par la saisie de l'utilisateur
            $query->orderByRaw("CASE 
                WHEN nom LIKE ? THEN 1 
                WHEN prenoms LIKE ? THEN 2 
                WHEN matricule LIKE ? THEN 3 
                ELSE 4 
            END", ["{$search}%", "{$search}%", "{$search}%"]);
        }

        if ($request->boolean('all') || $request->get('per_page') === 'all') {
            $items = $query->latest()->get();
            return response()->json([
                'status' => 'success',
                'data'   => CatechumeneResource::collection($items),
            ]);
        }

        $perPage = (int) $request->get('per_page', 15);
        $catechumenes = $query->latest()->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data'   => CatechumeneResource::collection($catechumenes->items()),
            'meta'   => [
                'current_page' => $catechumenes->currentPage(),
                'last_page'    => $catechumenes->lastPage(),
                'per_page'     => $catechumenes->perPage(),
                'total'        => $catechumenes->total(),
            ],
        ]);
    }

    /**
     * Recherche rapide par Matricule pour les guichets d'inscription.
     */
    public function showByMatricule(Request $request, string $code): JsonResponse
    {
        $user = $request->user() ?? auth('sanctum')->user();
        $paroisseId = $user?->paroisse_configuration_id 
            ?? $request->input('paroisse_configuration_id')
            ?? $request->input('paroisse_id')
            ?? $request->header('X-Paroisse-Id')
            ?? $request->header('X-Paroisse-Configuration-Id');

        if (!$paroisseId && ($request->filled('campagne_id') || $request->filled('campagne'))) {
            $campVal = $request->input('campagne_id') ?? $request->input('campagne');
            $paroisseId = \App\Models\CampagnePreinscription::where('uuid', $campVal)
                ->orWhere('id', $campVal)
                ->value('paroisse_configuration_id');
        }

        $trimmedCode = strtolower(trim($code));

        $relations = [
            'ceb',
            'inscriptionsAnnuelles.anneeCatechese',
            'inscriptionsAnnuelles.section',
            'inscriptionsAnnuelles.niveau',
            'inscriptionsAnnuelles.classe',
            'inscriptionsAnnuelles.decisionFinAnnee',
            'parrainsMarraines',
        ];

        $applyCriteria = function ($q) use ($trimmedCode) {
            $q->where(function ($sq) use ($trimmedCode) {
                $sq->whereRaw('LOWER(matricule) = ?', [$trimmedCode])
                   ->orWhere('uuid', $trimmedCode);
                if (is_numeric($trimmedCode)) {
                    $sq->orWhere('id', (int) $trimmedCode);
                }
            });
        };

        $query = Catechumene::with($relations);
        if ($paroisseId) {
            $query->where('paroisse_configuration_id', (int) $paroisseId);
        }

        $catechumene = $query->where($applyCriteria)->first();

        // Fallback sans filtre de paroisse si aucun catéchumène trouvé avec la restriction
        if (!$catechumene && $paroisseId) {
            $catechumene = Catechumene::with($relations)->where($applyCriteria)->first();
        }

        if (!$catechumene) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Aucun catéchumène trouvé avec ce matricule.',
            ], 404);
        }

        $progressionService = app(\App\Services\ProgressionPastoraleService::class);
        $progression = $progressionService->calculerProgression($catechumene, $paroisseId ? (int) $paroisseId : null);

        $resourceData = (new CatechumeneResource($catechumene))->resolve($request);
        $resourceData['progression_pastorale'] = $progression;

        return response()->json([
            'status' => 'success',
            'data'   => $resourceData,
        ]);
    }

    /**
     * Fournit toutes les données pour l'impression de la fiche catéchumène par Angular.
     */
    public function ficheImpression(Request $request, mixed $catechumene): JsonResponse
    {
        $cat = is_numeric($catechumene)
            ? Catechumene::find((int) $catechumene)
            : Catechumene::where('uuid', $catechumene)->orWhere('matricule', $catechumene)->first();

        if (!$cat) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Catéchumène introuvable.',
            ], 404);
        }

        $userParoisseId = $request->user()?->paroisse_configuration_id;
        $this->authorizeTenant($userParoisseId, $cat->paroisse_configuration_id);

        $paroisse = CatecheseConfiguration::find($cat->paroisse_configuration_id)
            ?? ($userParoisseId ? CatecheseConfiguration::find($userParoisseId) : null);

        $cat->loadMissing([
            'ceb',
            'parrainsMarraines',
            'inscriptionsAnnuelles.classe',
            'inscriptionsAnnuelles.niveau.section',
            'inscriptionsAnnuelles.anneeCatechese',
        ]);

        $inscription = $cat->inscriptionsAnnuelles?->sortByDesc('id')->first();
        $annee = AnneeCatechese::resolveAnnee($request, $paroisse->id) ?? $inscription?->anneeCatechese;

        $headerService = app(ParoisseHeaderService::class);
        $entete = $headerService->getHeaderData($paroisse, $annee);

        $prenom = $cat->prenoms ?? ($cat->prenom ?? '');
        $parrain = $cat->parrainsMarraines->firstWhere('type', 'parrain');
        $marraine = $cat->parrainsMarraines->firstWhere('type', 'marraine');

        $photoUrl = $cat->photo_url
            ?? ($cat->photo_path ? asset('storage/' . ltrim($cat->photo_path, '/')) : null);

        return response()->json([
            'status'      => 'success',
            'entete'      => $entete,
            'paroisse'    => new CatecheseConfigurationResource($paroisse),
            'catechumene' => [
                'id'                     => $cat->uuid,
                'matricule'              => $cat->matricule,
                'nom'                    => $cat->nom,
                'prenom'                 => $prenom,
                'prenoms'                => $prenom,
                'nom_complet'            => trim($cat->nom . ' ' . $prenom),
                'sexe'                   => $cat->sexe,
                'date_naissance'         => $cat->date_naissance ? (is_string($cat->date_naissance) ? substr($cat->date_naissance, 0, 10) : $cat->date_naissance->toDateString()) : null,
                'lieu_naissance'         => $cat->lieu_naissance,
                'adresse'                => $cat->adresse,
                'domicile'               => $cat->domicile ?? $cat->adresse,
                'telephone'              => $cat->telephone,
                'profession'             => $cat->profession,
                'classe_scolaire'        => $cat->classe_scolaire,
                'situation_matrimoniale' => $cat->situation_matrimoniale,
                'photo_url'              => $photoUrl,
                'filiation'              => [
                    'pere'   => ['nom' => $cat->nom_pere, 'telephone' => $cat->telephone_pere],
                    'mere'   => ['nom' => $cat->nom_mere, 'telephone' => $cat->telephone_mere],
                    'tuteur' => ['nom' => $cat->nom_tuteur, 'telephone' => $cat->telephone_tuteur],
                ],
                'sacrements'             => [
                    'bapteme'            => [
                        'est_baptise'      => (bool) $cat->est_baptise,
                        'date_bapteme'     => $cat->date_bapteme ? (is_string($cat->date_bapteme) ? substr($cat->date_bapteme, 0, 10) : $cat->date_bapteme->toDateString()) : null,
                        'lieu_bapteme'     => $cat->lieu_bapteme,
                        'paroisse_bapteme' => $cat->paroisse_bapteme,
                        'numero_acte'      => $cat->numero_acte_bapteme,
                    ],
                    'premiere_communion' => [
                        'est_communiant'   => (bool) ($cat->premiere_communion ?? false),
                        'date'             => $cat->date_premiere_communion ? (is_string($cat->date_premiere_communion) ? substr($cat->date_premiere_communion, 0, 10) : $cat->date_premiere_communion->toDateString()) : null,
                        'paroisse'         => $cat->paroisse_premiere_communion,
                    ],
                    'confirmation'       => [
                        'est_confirme'     => (bool) ($cat->confirmation ?? false),
                        'date'             => $cat->date_confirmation ? (is_string($cat->date_confirmation) ? substr($cat->date_confirmation, 0, 10) : $cat->date_confirmation->toDateString()) : null,
                        'paroisse'         => $cat->paroisse_confirmation,
                    ],
                ],
            ],
            'inscription_courante' => $inscription ? [
                'code_inscription' => $inscription->code_inscription,
                'date_inscription' => $inscription->date_inscription ? (is_string($inscription->date_inscription) ? substr($inscription->date_inscription, 0, 10) : $inscription->date_inscription->toDateString()) : null,
                'statut'           => $inscription->statut_inscription,
                'annee'            => $inscription->anneeCatechese?->libelle,
                'section'          => $inscription->niveau?->section?->nom,
                'niveau'           => $inscription->niveau?->nom,
                'classe'           => $inscription->classe?->nom,
            ] : null,
            'parrain'     => $parrain ? [
                'nom_prenoms'            => $parrain->nom_prenoms,
                'telephone'              => $parrain->telephone,
                'sacrement_confirmation' => (bool) $parrain->sacrement_confirmation,
            ] : null,
            'marraine'    => $marraine ? [
                'nom_prenoms'            => $marraine->nom_prenoms,
                'telephone'              => $marraine->telephone,
                'sacrement_confirmation' => (bool) $marraine->sacrement_confirmation,
            ] : null,
            'ceb'         => $cat->ceb ? [
                'nom'      => $cat->ceb->nom,
                'quartier' => $cat->ceb->quartier,
            ] : null,
        ]);
    }

    /**
     * Alias de compatibilité retournant le JSON de la fiche catéchumène.
     */
    public function pdf(Request $request, mixed $catechumene): JsonResponse
    {
        return $this->ficheImpression($request, $catechumene);
    }

    /**
     * Création directe d'une fiche catéchumène par l'administration.
     */
    public function store(StoreCatechumeneRequest $request): JsonResponse
    {
        $paroisseId = $request->user()?->paroisse_configuration_id 
            ?? $request->input('paroisse_configuration_id') 
            ?? $request->input('paroisse_id');

        if (!$paroisseId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'L\'identifiant de la paroisse est obligatoire.',
            ], 422);
        }

        $validated = $request->validated();

        if (!empty($validated['ceb_id'])) {
            $ceb = Ceb::where('uuid', $validated['ceb_id'])->firstOrFail();
            $validated['ceb_id'] = $ceb->id;
        }

        $this->handlePhotoUpload($validated, (int) $paroisseId);

        $validated['paroisse_configuration_id'] = (int) $paroisseId;

        // Résolution de la section et de l'année si transmises
        $section = null;
        if (!empty($validated['section_id'])) {
            $secVal = $validated['section_id'];
            $section = is_numeric($secVal) ? \App\Models\Section::find((int) $secVal) : \App\Models\Section::where('uuid', $secVal)->first();
        } elseif (!empty($validated['niveau_id'])) {
            $nivVal = $validated['niveau_id'];
            $niveau = is_numeric($nivVal) ? \App\Models\Niveau::find((int) $nivVal) : \App\Models\Niveau::where('uuid', $nivVal)->first();
            $section = $niveau?->section;
        } elseif (!empty($validated['classe_id'])) {
            $clsVal = $validated['classe_id'];
            $classe = is_numeric($clsVal) ? \App\Models\Classe::find((int) $clsVal) : \App\Models\Classe::where('uuid', $clsVal)->first();
            $section = $classe?->niveau?->section;
        }

        $yearOrDate = $validated['annee_catechese_id'] ?? null;
        unset($validated['section_id'], $validated['niveau_id'], $validated['classe_id'], $validated['annee_catechese_id']);

        // Génération du matricule officiel unique (ex: SM26-J8HDX)
        $validated['matricule'] = app(\App\Services\MatriculeGeneratorService::class)->generate(
            (int) $paroisseId,
            $section,
            $yearOrDate
        );
        $validated['password'] = Hash::make('12345678');
        $validated['statut'] = $validated['statut'] ?? 'actif';

        $catechumene = Catechumene::create($validated);

        $this->syncInscription(
            $catechumene,
            $request->input('section_id'),
            $request->input('niveau_id'),
            $request->input('classe_id'),
            $request->input('annee_catechese_id')
        );

        $catechumene->load([
            'ceb',
            'inscriptionsAnnuelles.anneeCatechese',
            'inscriptionsAnnuelles.section',
            'inscriptionsAnnuelles.niveau',
            'inscriptionsAnnuelles.classe',
            'parrainsMarraines',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Catéchumène créé avec succès.',
            'data'    => new CatechumeneResource($catechumene),
        ], 201);
    }

    /**
     * Détails complets d'un catéchumène.
     */
    public function show(Request $request, Catechumene $catechumene): JsonResponse
    {
        $this->authorizeTenant($request->user()?->paroisse_configuration_id, $catechumene->paroisse_configuration_id);

        $catechumene->load([
            'ceb',
            'inscriptionsAnnuelles.anneeCatechese',
            'inscriptionsAnnuelles.section',
            'inscriptionsAnnuelles.niveau',
            'inscriptionsAnnuelles.classe',
            'parrainsMarraines',
        ]);

        $progressionService = app(\App\Services\ProgressionPastoraleService::class);
        $progression = $progressionService->calculerProgression($catechumene, $paroisseId ? (int) $paroisseId : null);

        $resourceData = (new CatechumeneResource($catechumene))->resolve($request);
        $resourceData['progression_pastorale'] = $progression;

        return response()->json([
            'status' => 'success',
            'data'   => $resourceData,
        ]);
    }

    /**
     * Mise à jour d'un catéchumène.
     */
    public function update(UpdateCatechumeneRequest $request, Catechumene $catechumene): JsonResponse
    {
        $this->authorizeTenant($request->user()?->paroisse_configuration_id, $catechumene->paroisse_configuration_id);
        $validated = $request->validated();

        if (array_key_exists('ceb_id', $validated)) {
            if ($validated['ceb_id']) {
                $ceb = Ceb::where('uuid', $validated['ceb_id'])->firstOrFail();
                $validated['ceb_id'] = $ceb->id;
            } else {
                $validated['ceb_id'] = null;
            }
        }

        $this->handlePhotoUpload($validated, $catechumene->paroisse_configuration_id);

        $sectionVal = $validated['section_id'] ?? $request->input('section_id');
        $niveauVal  = $validated['niveau_id'] ?? $request->input('niveau_id');
        $classeVal  = $validated['classe_id'] ?? $request->input('classe_id');
        $anneeVal   = $validated['annee_catechese_id'] ?? $request->input('annee_catechese_id');
        unset($validated['section_id'], $validated['niveau_id'], $validated['classe_id'], $validated['annee_catechese_id']);

        $catechumene->update($validated);

        if ($sectionVal !== null || $niveauVal !== null || $classeVal !== null || $anneeVal !== null) {
            $this->syncInscription($catechumene, $sectionVal, $niveauVal, $classeVal, $anneeVal);
        }

        $catechumene->load([
            'ceb',
            'inscriptionsAnnuelles.anneeCatechese',
            'inscriptionsAnnuelles.section',
            'inscriptionsAnnuelles.niveau',
            'inscriptionsAnnuelles.classe',
            'parrainsMarraines',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Catéchumène mis à jour avec succès.',
            'data'    => new CatechumeneResource($catechumene),
        ]);
    }

    /**
     * Traite l'upload ou le décodage d'une photo Base64 / URL.
     */
    private function handlePhotoUpload(array &$validated, ?int $paroisseId): void
    {
        $photoInput = $validated['photo_path'] ?? ($validated['photo_url'] ?? ($validated['photo'] ?? null));
        unset($validated['photo_url'], $validated['photo']);

        if (empty($photoInput)) {
            return;
        }

        // Si image Base64 (data:image/png;base64,...)
        if (preg_match('/^data:image\/(\w+);base64,/', $photoInput, $matches)) {
            $imageType = strtolower($matches[1]);
            $imageData = substr($photoInput, strpos($photoInput, ',') + 1);
            $decoded = base64_decode($imageData);

            if ($decoded !== false) {
                $ext = match ($imageType) {
                    'jpeg', 'jpg' => 'jpg',
                    'png'         => 'png',
                    'gif'         => 'gif',
                    'webp'        => 'webp',
                    default       => 'png',
                };
                $filename = 'catechumenes/photos/' . ($paroisseId ? "p{$paroisseId}_" : '') . uniqid('cat_', true) . '.' . $ext;
                Storage::disk('public')->put($filename, $decoded);
                $validated['photo_path'] = $filename;
                return;
            }
        }

        // Si chemin standard
        $validated['photo_path'] = $photoInput;
    }

    /**
     * Suppression d'un catéchumène.
     */
    public function destroy(Request $request, Catechumene $catechumene): JsonResponse
    {
        $this->authorizeTenant($request->user()?->paroisse_configuration_id, $catechumene->paroisse_configuration_id);

        $catechumene->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Catéchumène supprimé avec succès.',
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }

    /**
     * Synchronise l'inscription annuelle d'un catéchumène (Section, Niveau, Classe, Année).
     */
    private function syncInscription(
        Catechumene $catechumene,
        mixed $sectionVal,
        mixed $niveauVal,
        mixed $classeVal,
        mixed $anneeVal
    ): void {
        $annee = null;
        if (!empty($anneeVal)) {
            $annee = is_numeric($anneeVal)
                ? AnneeCatechese::find((int) $anneeVal)
                : AnneeCatechese::where('uuid', $anneeVal)->first();
        }
        if (!$annee) {
            $annee = AnneeCatechese::where('paroisse_configuration_id', $catechumene->paroisse_configuration_id)
                ->where('est_active', true)
                ->first()
                ?? AnneeCatechese::where('paroisse_configuration_id', $catechumene->paroisse_configuration_id)->latest('id')->first();
        }

        $section = null;
        if (!empty($sectionVal)) {
            $section = is_numeric($sectionVal)
                ? Section::find((int) $sectionVal)
                : Section::where('uuid', $sectionVal)->first();
        }

        $niveau = null;
        if (!empty($niveauVal)) {
            $niveau = is_numeric($niveauVal)
                ? Niveau::find((int) $niveauVal)
                : Niveau::where('uuid', $niveauVal)->first();
            if (!$section && $niveau?->section_id) {
                $section = $niveau->section;
            }
        }

        $classe = null;
        if (!empty($classeVal)) {
            $classe = is_numeric($classeVal)
                ? Classe::find((int) $classeVal)
                : Classe::where('uuid', $classeVal)->first();
            if (!$niveau && $classe?->niveau_id) {
                $niveau = $classe->niveau;
            }
            if (!$section && $classe?->niveau?->section_id) {
                $section = $classe->niveau->section;
            }
        }

        if (!$section && $catechumene->paroisse_configuration_id) {
            $section = Section::where('paroisse_configuration_id', $catechumene->paroisse_configuration_id)->orderBy('ordre_affichage')->first();
        }
        if (!$niveau && $section && $catechumene->paroisse_configuration_id) {
            $niveau = Niveau::where('section_id', $section->id)->where('paroisse_configuration_id', $catechumene->paroisse_configuration_id)->orderBy('ordre_affichage')->first();
        }
        if (!$niveau && $catechumene->paroisse_configuration_id) {
            $niveau = Niveau::where('paroisse_configuration_id', $catechumene->paroisse_configuration_id)->orderBy('ordre_affichage')->first();
        }

        $anneeId = $annee?->id;
        if (!$anneeId) {
            return;
        }

        $inscription = InscriptionAnnuelle::where('catechumene_id', $catechumene->id)
            ->where('annee_catechese_id', $anneeId)
            ->first();

        if (!$inscription) {
            $inscription = InscriptionAnnuelle::where('catechumene_id', $catechumene->id)
                ->latest('id')
                ->first();
        }

        $updateData = [
            'section_id' => $section?->id,
            'niveau_id'  => $niveau?->id,
            'classe_id'  => $classe?->id,
            'ceb_id'     => $catechumene->ceb_id,
        ];

        if ($inscription) {
            if ($anneeId) {
                $updateData['annee_catechese_id'] = $anneeId;
            }
            $inscription->update($updateData);
        } else {
            InscriptionAnnuelle::create([
                'paroisse_configuration_id' => $catechumene->paroisse_configuration_id,
                'catechumene_id'            => $catechumene->id,
                'annee_catechese_id'        => $anneeId,
                'section_id'                => $section?->id,
                'niveau_id'                 => $niveau?->id,
                'classe_id'                 => $classe?->id,
                'ceb_id'                    => $catechumene->ceb_id,
                'code_inscription'          => 'INS-' . ($catechumene->matricule ?: $catechumene->id) . '-' . date('Y'),
                'date_inscription'          => now()->toDateString(),
                'statut_inscription'        => 'validee',
                'frais_inscription_payes'   => true,
            ]);
        }
    }
}