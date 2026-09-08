<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePreinscriptionRequest;
use App\Http\Requests\Api\V1\UpdatePreinscriptionRequest;
use App\Http\Requests\Api\V1\ValiderPreinscriptionRequest;
use App\Http\Resources\Api\V1\PreinscriptionResource;
use App\Models\AnneeCatechese;
use App\Models\CampagnePreinscription;
use App\Models\CatecheseConfiguration;
use App\Models\Catechumene;
use App\Models\Classe;
use App\Models\InscriptionAnnuelle;
use App\Models\Niveau;
use App\Models\OperationPaiement;
use App\Models\ParrainMarraine;
use App\Models\Preinscription;
use App\Models\Section;
use App\Models\Tarif;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PreinscriptionController extends Controller
{
    /**
     * Résout une instance de Preinscription à partir d'un UUID ou d'un ID numérique.
     */
    private function resolvePreinscription(mixed $preinscription): Preinscription
    {
        if ($preinscription instanceof Preinscription) {
            return $preinscription;
        }

        return Preinscription::where('uuid', $preinscription)
            ->orWhere('id', $preinscription)
            ->orWhere('code_dossier', $preinscription)
            ->firstOrFail();
    }

    /**
     * Liste paginée des préinscriptions de la paroisse avec filtres.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()?->paroisse_configuration_id 
            ?? CatecheseConfiguration::first()?->id;

        $query = Preinscription::with(['campagne', 'anneeCatechese', 'sectionSouhaite', 'niveauSouhaite']);

        if ($paroisseId) {
            $query->where('paroisse_configuration_id', $paroisseId);
        }

        if ($request->filled('statut')) {
            $statut = $request->statut;
            if ($statut === 'valide') $statut = 'validee';
            if ($statut === 'rejete') $statut = 'rejetee';
            $query->where('statut', $statut);
        }

        if ($request->filled('type_demande')) {
            $type = $request->type_demande;
            if ($type === 'premiere_inscription') $type = 'nouvelle_inscription';
            $query->where('type_demande', $type);
        }

        if ($request->filled('section_id') || $request->filled('section_souhaite_id')) {
            $secParam = $request->get('section_id', $request->get('section_souhaite_id'));
            $sectionId = is_numeric($secParam) ? (int)$secParam : Section::where('uuid', $secParam)->value('id');
            if ($sectionId) {
                $query->where('section_souhaite_id', $sectionId);
            }
        }

        if ($request->filled('niveau_id') || $request->filled('niveau_souhaite_id')) {
            $nivParam = $request->get('niveau_id', $request->get('niveau_souhaite_id'));
            $niveauId = is_numeric($nivParam) ? (int)$nivParam : Niveau::where('uuid', $nivParam)->value('id');
            if ($niveauId) {
                $query->where('niveau_souhaite_id', $niveauId);
            }
        }

        if ($request->filled('campagne_id') || $request->filled('campagne_preinscription_id')) {
            $campParam = $request->get('campagne_id', $request->get('campagne_preinscription_id'));
            $campagneId = is_numeric($campParam) ? (int)$campParam : CampagnePreinscription::where('uuid', $campParam)->value('id');
            if ($campagneId) {
                $query->where('campagne_preinscription_id', $campagneId);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('prenoms', 'like', "%{$search}%")
                  ->orWhere('code_dossier', 'like', "%{$search}%")
                  ->orWhere('telephone', 'like', "%{$search}%")
                  ->orWhere('telephone_pere', 'like', "%{$search}%")
                  ->orWhere('telephone_mere', 'like', "%{$search}%")
                  ->orWhere('telephone_tuteur', 'like', "%{$search}%");
            });
        }

        if ($request->boolean('all')) {
            $items = $query->latest()->get();
            return response()->json([
                'status' => 'success',
                'data'   => PreinscriptionResource::collection($items),
                'total'  => $items->count(),
            ]);
        }

        $perPage = (int) $request->get('per_page', 15);
        $preinscriptions = $query->latest()->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data'   => PreinscriptionResource::collection($preinscriptions->items()),
            'meta'   => [
                'current_page' => $preinscriptions->currentPage(),
                'last_page'    => $preinscriptions->lastPage(),
                'per_page'     => $preinscriptions->perPage(),
                'total'        => $preinscriptions->total(),
            ],
        ]);
    }

    /**
     * Soumettre ou enregistrer une préinscription.
     */
    public function store(StorePreinscriptionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // 1. Résolution Campagne
        $campagneParam = $validated['campagne_id'] ?? $validated['campagne_preinscription_id'] ?? null;
        if ($campagneParam) {
            $campagne = is_numeric($campagneParam)
                ? CampagnePreinscription::findOrFail($campagneParam)
                : CampagnePreinscription::where('uuid', $campagneParam)->firstOrFail();
        } else {
            $campagne = CampagnePreinscription::where('statut', 'ouverte')->latest()->first()
                ?? CampagnePreinscription::latest()->firstOrFail();
        }

        if ($campagne->statut !== 'ouverte') {
            return response()->json([
                'status'  => 'error',
                'message' => "La campagne de préinscription pour l'année pastorale en cours est actuellement clôturée. Veuillez vous rendre au secrétariat de la paroisse.",
            ], 422);
        }

        $paroisseId = $request->user()?->paroisse_configuration_id 
            ?? $campagne->paroisse_configuration_id 
            ?? CatecheseConfiguration::first()?->id;

        $validated['paroisse_configuration_id'] = $paroisseId;
        $validated['campagne_preinscription_id'] = $campagne->id;
        $validated['annee_catechese_id'] = $campagne->annee_catechese_id ?? AnneeCatechese::where('est_active', true)->value('id');
        $validated['code_dossier'] = 'PRE-' . strtoupper(Str::random(6));

        // 2. Normalisation type_demande
        $typeDemande = $validated['type_demande'] ?? 'nouvelle_inscription';
        if ($typeDemande === 'premiere_inscription') {
            $typeDemande = 'nouvelle_inscription';
        }
        $validated['type_demande'] = $typeDemande;

        // 3. Normalisation Section & Niveau
        $sectionParam = $validated['section_souhaite_id'] ?? $validated['section_id'] ?? null;
        if ($sectionParam) {
            $sectionId = is_numeric($sectionParam) ? (int)$sectionParam : Section::where('uuid', $sectionParam)->value('id');
            $validated['section_souhaite_id'] = $sectionId;
        }

        $niveauParam = $validated['niveau_souhaite_id'] ?? $validated['niveau_id'] ?? null;
        if ($niveauParam) {
            $niveauId = is_numeric($niveauParam) ? (int)$niveauParam : Niveau::where('uuid', $niveauParam)->value('id');
            $validated['niveau_souhaite_id'] = $niveauId;
        }

        $validated['statut'] = $validated['statut'] ?? 'en_attente';
        $preinscription = Preinscription::create($validated);
        $preinscription->load(['campagne', 'anneeCatechese', 'sectionSouhaite', 'niveauSouhaite']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Préinscription enregistrée avec succès. Code dossier : ' . $preinscription->code_dossier,
            'data'    => new PreinscriptionResource($preinscription),
        ], 201);
    }

    /**
     * Afficher les détails d'une préinscription.
     */
    public function show(Request $request, mixed $preinscription): JsonResponse
    {
        $item = $this->resolvePreinscription($preinscription);
        $item->load(['campagne', 'anneeCatechese', 'sectionSouhaite', 'niveauSouhaite']);

        return response()->json([
            'status' => 'success',
            'data'   => new PreinscriptionResource($item),
        ]);
    }

    /**
     * Mettre à jour une préinscription.
     */
    public function update(UpdatePreinscriptionRequest $request, mixed $preinscription): JsonResponse
    {
        $item = $this->resolvePreinscription($preinscription);
        $validated = $request->validated();

        if (isset($validated['campagne_id']) || isset($validated['campagne_preinscription_id'])) {
            $campParam = $validated['campagne_id'] ?? $validated['campagne_preinscription_id'];
            $campId = is_numeric($campParam) ? (int)$campParam : CampagnePreinscription::where('uuid', $campParam)->value('id');
            if ($campId) $validated['campagne_preinscription_id'] = $campId;
        }

        if (isset($validated['section_souhaite_id']) || isset($validated['section_id'])) {
            $secParam = $validated['section_souhaite_id'] ?? $validated['section_id'];
            $secId = is_numeric($secParam) ? (int)$secParam : Section::where('uuid', $secParam)->value('id');
            if ($secId) $validated['section_souhaite_id'] = $secId;
        }

        if (isset($validated['niveau_souhaite_id']) || isset($validated['niveau_id'])) {
            $nivParam = $validated['niveau_souhaite_id'] ?? $validated['niveau_id'];
            $nivId = is_numeric($nivParam) ? (int)$nivParam : Niveau::where('uuid', $nivParam)->value('id');
            if ($nivId) $validated['niveau_souhaite_id'] = $nivId;
        }

        if (isset($validated['type_demande'])) {
            if ($validated['type_demande'] === 'premiere_inscription') {
                $validated['type_demande'] = 'nouvelle_inscription';
            }
        }

        if (isset($validated['statut'])) {
            if ($validated['statut'] === 'valide') $validated['statut'] = 'validee';
            if ($validated['statut'] === 'rejete') $validated['statut'] = 'rejetee';
        }

        $item->update($validated);
        $item->load(['campagne', 'anneeCatechese', 'sectionSouhaite', 'niveauSouhaite']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Préinscription mise à jour avec succès.',
            'data'    => new PreinscriptionResource($item),
        ]);
    }

    /**
     * Mettre à jour le statut d'une préinscription (en_attente, validee, rejetee).
     */
    public function updateStatus(Request $request, mixed $preinscription): JsonResponse
    {
        $item = $this->resolvePreinscription($preinscription);

        $newStatut = $request->input('statut', $request->input('status', 'en_attente'));
        if ($newStatut === 'valide') $newStatut = 'validee';
        if ($newStatut === 'rejete') $newStatut = 'rejetee';

        $item->update([
            'statut'           => $newStatut,
            'notes_validation' => $request->input('notes_validation', $request->input('motif', $item->notes_validation)),
        ]);

        $item->load(['campagne', 'anneeCatechese', 'sectionSouhaite', 'niveauSouhaite']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Statut de la préinscription mis à jour.',
            'data'    => new PreinscriptionResource($item),
        ]);
    }

    /**
     * Valider la préinscription par l'administration ➔ Crée ou rattache le Catéchumène et crée son Inscription Annuelle.
     */
    public function valider(ValiderPreinscriptionRequest $request, mixed $preinscription): JsonResponse
    {
        $item = $this->resolvePreinscription($preinscription);

        if ($item->statut === 'validee') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Cette préinscription a déjà été validée.',
            ], 422);
        }

        $validated = $request->validated();

        $resultat = DB::transaction(function () use ($item, $validated, $request) {
            $niveauParam = $validated['niveau_id'] ?? $item->niveau_souhaite_id;
            $niveau = is_numeric($niveauParam) 
                ? Niveau::findOrFail($niveauParam) 
                : Niveau::where('uuid', $niveauParam)->firstOrFail();

            $classeId = null;
            if (!empty($validated['classe_id'])) {
                $classeId = is_numeric($validated['classe_id'])
                    ? (int)$validated['classe_id']
                    : Classe::where('uuid', $validated['classe_id'])->value('id');
            }

            $annee = $item->anneeCatechese ?? AnneeCatechese::where('est_active', true)->first();
            $prefixAnnee = explode('-', $annee->libelle ?? date('Y'))[0] ?? date('Y');

            // Vérification du type de demande : réinscription vs nouvelle inscription
            $catechumene = null;

            if ($item->type_demande === 'reinscription') {
                // Recherche d'un catéchumène existant
                if (!empty($validated['catechumene_id'])) {
                    $catParam = $validated['catechumene_id'];
                    $catechumene = is_numeric($catParam) 
                        ? Catechumene::find($catParam) 
                        : Catechumene::where('uuid', $catParam)->first();
                }

                if (!$catechumene && $item->telephone) {
                    $catechumene = Catechumene::where('paroisse_configuration_id', $item->paroisse_configuration_id)
                        ->where('telephone', $item->telephone)
                        ->first();
                }

                if (!$catechumene) {
                    $catechumene = Catechumene::where('paroisse_configuration_id', $item->paroisse_configuration_id)
                        ->where('nom', $item->nom)
                        ->where('prenoms', $item->prenoms)
                        ->first();
                }
            }

            // Si nouvelle inscription ou catéchumène non trouvé, on le crée
            if (!$catechumene) {
                $sectionForMatricule = $niveau->section ?? $niveau->section_id;
                $yearForMatricule = $annee?->libelle ?? $annee?->date_debut;
                $matricule = app(\App\Services\MatriculeGeneratorService::class)->generate(
                    $item->paroisse_configuration_id,
                    $sectionForMatricule,
                    $yearForMatricule
                );

                $catechumene = Catechumene::create([
                    'paroisse_configuration_id' => $item->paroisse_configuration_id,
                    'matricule'                 => $matricule,
                    'nom'                       => $item->nom,
                    'prenoms'                   => $item->prenoms,
                    'sexe'                      => $item->sexe,
                    'date_naissance'            => $item->date_naissance,
                    'lieu_naissance'            => $item->lieu_naissance,
                    'adresse'                   => $item->adresse,
                    'telephone'                 => $item->telephone,
                    'photo_path'                => $item->photo_url,
                    'situation_matrimoniale'    => $item->situation_matrimoniale,
                    'nom_pere'                  => $item->nom_pere,
                    'telephone_pere'            => $item->telephone_pere,
                    'nom_mere'                  => $item->nom_mere,
                    'telephone_mere'            => $item->telephone_mere,
                    'nom_tuteur'                => $item->nom_tuteur,
                    'telephone_tuteur'          => $item->telephone_tuteur,
                    'password'                  => '12345678',
                    'est_baptise'               => $item->est_baptise,
                    'date_bapteme'              => $item->date_bapteme,
                    'lieu_bapteme'              => $item->lieu_bapteme,
                    'paroisse_bapteme'          => $item->paroisse_bapteme,
                    'statut'                    => 'actif',
                ]);
            } else {

                // Mise à jour éventuelle des infos de contact
                $catechumene->update([
                    'adresse'          => $item->adresse ?? $catechumene->adresse,
                    'telephone'        => $item->telephone ?? $catechumene->telephone,
                    'nom_tuteur'       => $item->nom_tuteur ?? $catechumene->nom_tuteur,
                    'telephone_tuteur' => $item->telephone_tuteur ?? $catechumene->telephone_tuteur,
                ]);
            }

            // 2. Création ou mise à jour de l'Inscription Annuelle
            $anneeId = $annee?->id ?? $item->annee_catechese_id;
            $codeInscription = 'INS-' . $prefixAnnee . '-' . strtoupper(Str::random(5));

            $inscription = InscriptionAnnuelle::updateOrCreate(
                [
                    'paroisse_configuration_id' => $item->paroisse_configuration_id,
                    'catechumene_id'            => $catechumene->id,
                    'annee_catechese_id'        => $anneeId,
                ],
                [
                    'section_id'                => $niveau->section_id,
                    'niveau_id'                 => $niveau->id,
                    'classe_id'                 => $classeId,
                    'code_inscription'          => $codeInscription,
                    'date_inscription'          => now()->toDateString(),
                    'statut_inscription'        => 'valide',
                    'frais_inscription_payes'   => (bool) ($validated['frais_payes'] ?? false),
                    'observation'               => $validated['notes_validation'] ?? null,
                ]
            );

            // 3. Déclenchement automatique de l'opération de paiement en attente dans la finance UNIQUEMENT si un tarif réel existe
            if (!$inscription->frais_inscription_payes) {
                $explicitTarifId = $validated['tarif_id'] ?? $request->input('tarif_id');
                $tarif = Tarif::resolveForInscription(
                    $item->paroisse_configuration_id,
                    $anneeId,
                    $niveau,
                    $explicitTarifId
                );

                if ($tarif) {
                    $refCount = OperationPaiement::where('paroisse_configuration_id', $item->paroisse_configuration_id)->count() + 1;
                    $reference = 'OP-' . date('Y') . '-' . sprintf('%04d', $refCount);

                    OperationPaiement::updateOrCreate(
                        [
                            'paroisse_configuration_id' => $item->paroisse_configuration_id,
                            'catechumene_id'            => $catechumene->id,
                            'annee_catechese_id'        => $anneeId,
                            'tarif_id'                  => $tarif->id,
                            'statut'                    => 'en_attente',
                        ],
                        [
                            'reference'    => $reference,
                            'libelle'      => "{$tarif->intitule} - {$catechumene->nom_complet} ({$niveau->nom})",
                            'montant'      => (float) $tarif->montant,
                            'montant_paye' => 0,
                            'echeance'     => $tarif->periode_fin?->toDateString() ?? now()->addMonths(1)->toDateString(),
                            'statut'       => 'en_attente',
                        ]
                    );
                }
            }

            // 4. Création du Parrain/Marraine si renseigné
            if (!empty($item->nom_parrain)) {
                $typeParrain = (strtoupper($item->sexe_parrain ?? '') === 'F') ? 'marraine' : 'parrain';
                ParrainMarraine::firstOrCreate(
                    [
                        'paroisse_configuration_id' => $item->paroisse_configuration_id,
                        'catechumene_id'            => $catechumene->id,
                        'nom_prenoms'               => $item->nom_parrain,
                    ],
                    [
                        'type'                      => $typeParrain,
                        'telephone'                 => $item->telephone_parrain,
                        'sacrement_confirmation'    => true,
                    ]
                );
            }

            // 5. Marquer la préinscription comme validée
            $item->update([
                'statut'           => 'validee',
                'notes_validation' => $validated['notes_validation'] ?? 'Validée par le secrétariat.',
            ]);

            return [
                'catechumene' => $catechumene,
                'inscription' => $inscription,
            ];
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Préinscription validée avec succès. Dossier catéchumène et inscription annuelle prêts.',
            'data'    => [
                'matricule'        => $resultat['catechumene']->matricule,
                'code_catechumene' => $resultat['catechumene']->matricule,
                'catechumene_id'   => $resultat['catechumene']->uuid,
                'inscription_id'   => $resultat['inscription']->uuid,
            ],
        ]);
    }

    /**
     * Rejeter une préinscription.
     */
    public function rejeter(Request $request, mixed $preinscription): JsonResponse
    {
        $item = $this->resolvePreinscription($preinscription);

        $validated = $request->validate([
            'motif'            => ['nullable', 'string', 'max:500'],
            'motif_rejet'      => ['nullable', 'string', 'max:500'],
            'notes_validation' => ['nullable', 'string', 'max:500'],
        ]);

        $motif = $validated['motif_rejet'] ?? $validated['motif'] ?? $validated['notes_validation'] ?? 'Préinscription rejetée par l\'administration.';

        $item->update([
            'statut'           => 'rejetee',
            'notes_validation' => $motif,
        ]);

        $item->load(['campagne', 'anneeCatechese', 'sectionSouhaite', 'niveauSouhaite']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Préinscription rejetée.',
            'data'    => new PreinscriptionResource($item),
        ]);
    }

    /**
     * Supprimer une préinscription.
     */
    public function destroy(Request $request, mixed $preinscription): JsonResponse
    {
        $item = $this->resolvePreinscription($preinscription);
        $item->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Préinscription supprimée avec succès.',
        ]);
    }
}
