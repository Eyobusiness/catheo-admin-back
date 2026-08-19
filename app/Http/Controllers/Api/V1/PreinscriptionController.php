<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePreinscriptionRequest;
use App\Http\Requests\Api\V1\ValiderPreinscriptionRequest;
use App\Http\Resources\Api\V1\PreinscriptionResource;
use App\Models\CampagnePreinscription;
use App\Models\Catechumene;
use App\Models\Classe;
use App\Models\InscriptionAnnuelle;
use App\Models\Niveau;
use App\Models\ParrainMarraine;
use App\Models\Preinscription;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PreinscriptionController extends Controller
{
    /**
     * Liste paginée des préinscriptions de la paroisse avec filtres.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $query = Preinscription::with(['campagne', 'anneeCatechese', 'sectionSouhaite', 'niveauSouhaite'])
            ->where('paroisse_configuration_id', $paroisseId);

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('section_id')) {
            $sectionId = Section::where('uuid', $request->section_id)->value('id');
            if ($sectionId) {
                $query->where('section_souhaite_id', $sectionId);
            }
        }

        if ($request->filled('campagne_id')) {
            $campagneId = CampagnePreinscription::where('uuid', $request->campagne_id)->value('id');
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

        $perPage = (int) $request->get('per_page', 15);
        $preinscriptions = $query->latest()->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => PreinscriptionResource::collection($preinscriptions->items()),
            'meta' => [
                'current_page' => $preinscriptions->currentPage(),
                'last_page' => $preinscriptions->lastPage(),
                'per_page' => $preinscriptions->perPage(),
                'total' => $preinscriptions->total(),
            ],
        ]);
    }

    /**
     * Soumettre une préinscription en ligne (Formulaire public ou guichet).
     */
    public function store(StorePreinscriptionRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $campagne = CampagnePreinscription::where('uuid', $validated['campagne_id'])->firstOrFail();

        if ($campagne->statut !== 'ouverte') {
            return response()->json([
                'status' => 'error',
                'message' => 'La campagne de préinscription n\'est pas ouverte actuellement.',
            ], 422);
        }

        $validated['paroisse_configuration_id'] = $campagne->paroisse_configuration_id;
        $validated['campagne_preinscription_id'] = $campagne->id;
        $validated['annee_catechese_id'] = $campagne->annee_catechese_id;
        $validated['code_dossier'] = 'PRE-' . strtoupper(Str::random(6));

        // Normalisation type_demande
        if (($validated['type_demande'] ?? '') === 'premiere_inscription') {
            $validated['type_demande'] = 'nouvelle_inscription';
        }

        if (!empty($validated['section_souhaite_id'])) {
            $section = Section::where('uuid', $validated['section_souhaite_id'])->firstOrFail();
            $validated['section_souhaite_id'] = $section->id;
        }

        if (!empty($validated['niveau_souhaite_id'])) {
            $niveau = Niveau::where('uuid', $validated['niveau_souhaite_id'])->firstOrFail();
            $validated['niveau_souhaite_id'] = $niveau->id;
        }

        $preinscription = Preinscription::create($validated);
        $preinscription->load(['campagne', 'anneeCatechese', 'sectionSouhaite', 'niveauSouhaite']);

        return response()->json([
            'status' => 'success',
            'message' => 'Préinscription soumise avec succès. Votre code dossier est : ' . $preinscription->code_dossier,
            'data' => new PreinscriptionResource($preinscription),
        ], 201);
    }

    /**
     * Afficher les détails d'une préinscription.
     */
    public function show(Request $request, Preinscription $preinscription): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $preinscription->paroisse_configuration_id);

        $preinscription->load(['campagne', 'anneeCatechese', 'sectionSouhaite', 'niveauSouhaite']);

        return response()->json([
            'status' => 'success',
            'data' => new PreinscriptionResource($preinscription),
        ]);
    }

    /**
     * Valider la préinscription par l'administration ➔ Crée automatiquement le Catéchumène, son Inscription Annuelle (avec section_id) et son Parrain/Marraine.
     */
    public function valider(ValiderPreinscriptionRequest $request, Preinscription $preinscription): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $preinscription->paroisse_configuration_id);

        if ($preinscription->statut === 'validee') {
            return response()->json([
                'status' => 'error',
                'message' => 'Cette préinscription a déjà été validée.',
            ], 422);
        }

        $validated = $request->validated();

        $resultat = DB::transaction(function () use ($preinscription, $validated) {
            $niveau = Niveau::where('uuid', $validated['niveau_id'])->firstOrFail();
            $classeId = null;

            if (!empty($validated['classe_id'])) {
                $classeId = Classe::where('uuid', $validated['classe_id'])->value('id');
            }

            // 1. Génération du matricule unique (ex: CAT-2026-0001)
            $annee = $preinscription->anneeCatechese;
            $prefixAnnee = explode('-', $annee->libelle ?? date('Y'))[0] ?? date('Y');
            $dernierMatricule = Catechumene::where('paroisse_configuration_id', $preinscription->paroisse_configuration_id)
                ->where('code_catechumene', 'like', "CAT-{$prefixAnnee}-%")
                ->count();
            $codeCatechumene = sprintf("CAT-%s-%04d", $prefixAnnee, $dernierMatricule + 1);

            // 2. Création de la fiche Catéchumène (Authenticatable direct avec mot de passe par défaut)
            $catechumene = Catechumene::create([
                'paroisse_configuration_id' => $preinscription->paroisse_configuration_id,
                'code_catechumene'          => $codeCatechumene,
                'nom'                       => $preinscription->nom,
                'prenoms'                   => $preinscription->prenoms,
                'sexe'                      => $preinscription->sexe,
                'date_naissance'            => $preinscription->date_naissance,
                'lieu_naissance'            => $preinscription->lieu_naissance,
                'adresse'                   => $preinscription->adresse,
                'telephone'                 => $preinscription->telephone,
                'photo_path'                => $preinscription->photo_url,
                'situation_matrimoniale'    => $preinscription->situation_matrimoniale,
                'nom_pere'                  => $preinscription->nom_pere,
                'telephone_pere'            => $preinscription->telephone_pere,
                'nom_mere'                  => $preinscription->nom_mere,
                'telephone_mere'            => $preinscription->telephone_mere,
                'nom_tuteur'                => $preinscription->nom_tuteur,
                'telephone_tuteur'          => $preinscription->telephone_tuteur,
                'password'                  => Hash::make('12345678'),
                'est_baptise'               => $preinscription->est_baptise,
                'date_bapteme'              => $preinscription->date_bapteme,
                'lieu_bapteme'              => $preinscription->lieu_bapteme,
                'paroisse_bapteme'          => $preinscription->paroisse_bapteme,
                'statut'                    => 'actif',
            ]);

            // 3. Création de son Inscription Annuelle (avec section_id déduit ou explicite)
            $codeInscription = sprintf("INS-%s-%04d", $prefixAnnee, $dernierMatricule + 1);
            $inscription = InscriptionAnnuelle::create([
                'paroisse_configuration_id' => $preinscription->paroisse_configuration_id,
                'catechumene_id'            => $catechumene->id,
                'annee_catechese_id'        => $preinscription->annee_catechese_id,
                'section_id'                => $niveau->section_id,
                'niveau_id'                 => $niveau->id,
                'classe_id'                 => $classeId,
                'code_inscription'          => $codeInscription,
                'date_inscription'          => now()->toDateString(),
                'statut_inscription'        => 'valide',
                'frais_inscription_payes'   => (bool) ($validated['frais_payes'] ?? false),
                'observation'               => $validated['notes_validation'] ?? null,
            ]);

            // 4. Création du Parrain/Marraine si renseigné
            if (!empty($preinscription->nom_parrain)) {
                $typeParrain = ($preinscription->sexe_parrain === 'F') ? 'marraine' : 'parrain';
                ParrainMarraine::create([
                    'paroisse_configuration_id' => $preinscription->paroisse_configuration_id,
                    'catechumene_id'            => $catechumene->id,
                    'type'                      => $typeParrain,
                    'nom_prenoms'               => $preinscription->nom_parrain,
                    'telephone'                 => $preinscription->telephone_parrain,
                    'sacrement_confirmation'    => true,
                ]);
            }

            // 5. Mise à jour du statut de la préinscription
            $preinscription->update([
                'statut'           => 'validee',
                'notes_validation' => $validated['notes_validation'] ?? 'Validée par l\'administration.',
            ]);

            return [
                'catechumene' => $catechumene,
                'inscription' => $inscription,
            ];
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Préinscription validée avec succès. Fiche catéchumène et inscription annuelle créées.',
            'data'    => [
                'code_catechumene' => $resultat['catechumene']->code_catechumene,
                'catechumene_id'   => $resultat['catechumene']->uuid,
                'inscription_id'   => $resultat['inscription']->uuid,
            ],
        ]);
    }

    /**
     * Rejeter une préinscription.
     */
    public function rejeter(Request $request, Preinscription $preinscription): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $preinscription->paroisse_configuration_id);

        $validated = $request->validate([
            'motif' => ['nullable', 'string', 'max:500'],
        ]);

        $preinscription->update([
            'statut'           => 'rejetee',
            'notes_validation' => $validated['motif'] ?? 'Préinscription rejetée par l\'administration.',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Préinscription rejetée.',
            'data'    => new PreinscriptionResource($preinscription),
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
