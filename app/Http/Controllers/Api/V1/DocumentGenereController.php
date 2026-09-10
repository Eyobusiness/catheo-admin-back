<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CatecheseConfigurationResource;
use App\Http\Resources\Api\V1\DocumentGenereResource;
use App\Models\AnneeCatechese;
use App\Models\CatecheseConfiguration;
use App\Models\Catechumene;
use App\Models\DocumentGenere;
use App\Models\InscriptionAnnuelle;
use App\Models\ModeleDocument;
use App\Models\Niveau;
use Illuminate\Support\Facades\DB;
use App\Services\ParoisseHeaderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentGenereController extends Controller
{
    private function resolveDocument(mixed $document): DocumentGenere
    {
        if ($document instanceof DocumentGenere) {
            return $document;
        }

        $item = is_numeric($document)
            ? DocumentGenere::find((int) $document)
            : DocumentGenere::where('uuid', $document)->orWhere('reference_document', $document)->first();

        if (!$item) {
            abort(response()->json([
                'status'  => 'error',
                'message' => 'Document généré introuvable.',
            ], 404));
        }

        return $item;
    }

    private function generateUniqueReference(string $typeDocument): string
    {
        $prefix = match ($typeDocument) {
            'certificat'  => 'CERT',
            'attestation' => 'ATT',
            'carte'       => 'CRT',
            'convocation' => 'CNV',
            default       => 'DOC',
        };
        $year = date('Y');
        $count = DocumentGenere::withTrashed()->where('reference_document', 'like', "{$prefix}-{$year}-%")->count() + 1;
        do {
            $reference = sprintf('%s-%s-%04d', $prefix, $year, $count);
            $count++;
        } while (DocumentGenere::withTrashed()->where('reference_document', $reference)->exists());

        return $reference;
    }


    /**
     * Liste et historique des documents générés.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id ?? CatecheseConfiguration::value('id');

        $query = DocumentGenere::with(['catechumene', 'modeleDocument', 'anneeCatechese', 'user']);

        if ($paroisseId) {
            $query->where('paroisse_configuration_id', $paroisseId);
        }

        if ($request->filled('type_document') && $request->type_document !== 'all' && $request->type_document !== 'tous') {
            $query->where('type_document', $request->type_document);
        }

        if ($request->filled('modele_document_id')) {
            $val = $request->modele_document_id;
            $modId = is_numeric($val) ? (int) $val : ModeleDocument::where('uuid', $val)->value('id');
            if ($modId) {
                $query->where('modele_document_id', $modId);
            }
        }

        if ($request->filled('catechumene_id')) {
            $val = $request->catechumene_id;
            $catId = is_numeric($val) ? (int) $val : Catechumene::where('uuid', $val)->value('id');
            if ($catId) {
                $query->where('catechumene_id', $catId);
            }
        }

        if ($request->filled('annee_catechese_id') && $request->annee_catechese_id !== 'all' && $request->annee_catechese_id !== 'tous') {
            $val = $request->annee_catechese_id;
            $anneeId = is_numeric($val) ? (int) $val : AnneeCatechese::where('uuid', $val)->value('id');
            if ($anneeId) {
                $query->where('annee_catechese_id', $anneeId);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('titre', 'like', "%{$search}%")
                  ->orWhere('reference_document', 'like', "%{$search}%")
                  ->orWhereHas('catechumene', function ($qc) use ($search) {
                      $qc->where('nom', 'like', "%{$search}%")
                        ->orWhere('prenom', 'like', "%{$search}%")
                        ->orWhere('matricule', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('date_debut') && $request->filled('date_fin')) {
            $query->whereBetween('date_generation', [$request->date_debut, $request->date_fin]);
        }

        $documents = $query->orderBy('date_generation', 'desc')
            ->orderBy('id', 'desc')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data'   => DocumentGenereResource::collection($documents->items()),
            'meta'   => [
                'current_page' => $documents->currentPage(),
                'last_page'    => $documents->lastPage(),
                'per_page'     => $documents->perPage(),
                'total'        => $documents->total(),
            ],
        ]);
    }

    /**
     * Générer un document officiel pour un catéchumène.
     */
    public function store(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id ?? CatecheseConfiguration::value('id');

        $validated = $request->validate([
            'modele_document_id'        => ['required', 'string'],
            'catechumene_id'            => ['required', 'string'],
            'annee_catechese_id'        => ['nullable', 'string'],
            'date_generation'           => ['nullable', 'date'],
            'variables_personnalisees'  => ['nullable', 'array'],
        ]);

        $modele = is_numeric($validated['modele_document_id'])
            ? ModeleDocument::findOrFail($validated['modele_document_id'])
            : ModeleDocument::where('uuid', $validated['modele_document_id'])->orWhere('code', $validated['modele_document_id'])->firstOrFail();

        $cat = is_numeric($validated['catechumene_id'])
            ? Catechumene::findOrFail($validated['catechumene_id'])
            : Catechumene::where('uuid', $validated['catechumene_id'])->orWhere('matricule', $validated['catechumene_id'])->firstOrFail();

        $annee = !empty($validated['annee_catechese_id'])
            ? (is_numeric($validated['annee_catechese_id']) ? AnneeCatechese::find($validated['annee_catechese_id']) : AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->first())
            : AnneeCatechese::resolveAnnee($request, $paroisseId);

        $paroisse = CatecheseConfiguration::find($paroisseId) ?? $cat->paroisse;

        // Reference automatique unique
        $reference = $this->generateUniqueReference($modele->type_document);

        // Fusion des balises
        $renderedData = $this->renderDocumentTemplate(
            $modele,
            $cat,
            $paroisse,
            $annee,
            $reference,
            $validated['variables_personnalisees'] ?? []
        );

        $prenom = $cat->prenoms ?? ($cat->prenom ?? '');
        $titre = $modele->titre . ' - ' . trim($cat->nom . ' ' . $prenom);

        $docGenere = DocumentGenere::create([
            'paroisse_configuration_id' => $paroisseId,
            'modele_document_id'        => $modele->id,
            'catechumene_id'            => $cat->id,
            'annee_catechese_id'        => $annee?->id,
            'user_id'                   => $request->user()->id,
            'reference_document'        => $reference,
            'titre'                     => $titre,
            'type_document'             => $modele->type_document,
            'contenu'                   => $renderedData['html'],
            'metadonnees'               => $renderedData['tags'],
            'date_generation'           => $validated['date_generation'] ?? now()->toDateString(),
            'statut'                    => 'valide',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => "Document officiel généré avec succès ({$reference}).",
            'data'    => new DocumentGenereResource($docGenere->load(['catechumene', 'modeleDocument', 'anneeCatechese', 'user'])),
        ], 201);
    }

    /**
     * Génération en masse pour une classe ou un niveau.
     */
    public function genererMasse(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id ?? CatecheseConfiguration::value('id');

        $validated = $request->validate([
            'modele_document_id' => ['required', 'string'],
            'classe_id'          => ['nullable', 'string'],
            'niveau_id'          => ['nullable', 'string'],
            'annee_catechese_id' => ['nullable', 'string'],
            'catechumenes_ids'   => ['nullable', 'array'],
        ]);

        $modele = is_numeric($validated['modele_document_id'])
            ? ModeleDocument::findOrFail($validated['modele_document_id'])
            : ModeleDocument::where('uuid', $validated['modele_document_id'])->orWhere('code', $validated['modele_document_id'])->firstOrFail();

        $annee = !empty($validated['annee_catechese_id'])
            ? (is_numeric($validated['annee_catechese_id']) ? AnneeCatechese::find($validated['annee_catechese_id']) : AnneeCatechese::where('uuid', $validated['annee_catechese_id'])->first())
            : AnneeCatechese::resolveAnnee($request, $paroisseId);

        $paroisse = $paroisseId ? CatecheseConfiguration::find($paroisseId) : null;

        // Récupérer les catéchumènes cibles
        $catQuery = Catechumene::query();
        if ($paroisseId) {
            $catQuery->where('paroisse_configuration_id', $paroisseId);
        }

        if (!empty($validated['catechumenes_ids'])) {
            $catQuery->whereIn('uuid', $validated['catechumenes_ids'])
                     ->orWhereIn('id', $validated['catechumenes_ids']);
        } elseif (!empty($validated['classe_id'])) {
            $clsId = is_numeric($validated['classe_id']) ? (int) $validated['classe_id'] : \App\Models\Classe::where('uuid', $validated['classe_id'])->value('id');
            $catIds = InscriptionAnnuelle::where('classe_id', $clsId)
                ->where('statut_inscription', 'validee')
                ->pluck('catechumene_id');
            $catQuery->whereIn('id', $catIds);
        } elseif (!empty($validated['niveau_id'])) {
            $nivId = is_numeric($validated['niveau_id']) ? (int) $validated['niveau_id'] : \App\Models\Niveau::where('uuid', $validated['niveau_id'])->value('id');
            $catIds = InscriptionAnnuelle::where('niveau_id', $nivId)
                ->where('statut_inscription', 'validee')
                ->pluck('catechumene_id');
            $catQuery->whereIn('id', $catIds);
        }

        $catechumenes = $catQuery->get();
        $generatedCount = 0;
        $createdDocs = [];

        foreach ($catechumenes as $cat) {
            // Reference automatique unique
            $reference = $this->generateUniqueReference($modele->type_document);

            $renderedData = $this->renderDocumentTemplate(
                $modele,
                $cat,
                $paroisse,
                $annee,
                $reference,
                []
            );

            $catPrenom = $cat->prenoms ?? ($cat->prenom ?? '');
            $doc = DocumentGenere::create([
                'paroisse_configuration_id' => $paroisseId,
                'modele_document_id'        => $modele->id,
                'catechumene_id'            => $cat->id,
                'annee_catechese_id'        => $annee?->id,
                'user_id'                   => $request->user()->id,
                'reference_document'        => $reference,
                'titre'                     => $modele->titre . ' - ' . trim($cat->nom . ' ' . $catPrenom),
                'type_document'             => $modele->type_document,
                'contenu'                   => $renderedData['html'],
                'metadonnees'               => $renderedData['tags'],
                'date_generation'           => now()->toDateString(),
                'statut'                    => 'valide',
            ]);

            $createdDocs[] = $doc;
            $generatedCount++;
        }

        return response()->json([
            'status'  => 'success',
            'message' => "{$generatedCount} document(s) généré(s) avec succès.",
            'count'   => $generatedCount,
            'data'    => DocumentGenereResource::collection(collect($createdDocs)->take(10)),
        ]);
    }

    /**
     * Détails d'un document généré.
     */
    public function show(Request $request, mixed $document): JsonResponse
    {
        $item = $this->resolveDocument($document);

        return response()->json([
            'status' => 'success',
            'data'   => new DocumentGenereResource($item->load(['catechumene', 'modeleDocument', 'anneeCatechese', 'user'])),
        ]);
    }

    /**
     * Fournit les données prêtes pour l'impression du document officiel par Angular.
     */
    public function printData(Request $request, mixed $document): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id ?? CatecheseConfiguration::value('id');
        $item = $this->resolveDocument($document);

        if ($paroisseId && $item->paroisse_configuration_id && (int) $paroisseId !== (int) $item->paroisse_configuration_id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Accès refusé à ce document.',
            ], 403);
        }

        $paroisse = CatecheseConfiguration::find($item->paroisse_configuration_id)
            ?? CatecheseConfiguration::find($paroisseId)
            ?? CatecheseConfiguration::firstOrFail();

        $item->loadMissing(['catechumene', 'modeleDocument', 'anneeCatechese', 'user']);

        $headerService = app(ParoisseHeaderService::class);
        $entete = $headerService->getHeaderData($paroisse, $item->anneeCatechese);

        return response()->json([
            'status'   => 'success',
            'entete'   => $entete,
            'paroisse' => new CatecheseConfigurationResource($paroisse),
            'document' => new DocumentGenereResource($item),
            'modele'   => $item->modeleDocument,
            'print_payload' => [
                'titre'              => $item->titre_document ?? $item->modeleDocument?->titre ?? 'Document Officiel',
                'reference'          => $item->reference_document,
                'contenu_html'       => $item->contenu ?? $item->contenu_genere,
                'date_generation'    => $item->date_generation?->format('d/m/Y') ?? now()->format('d/m/Y'),
                'type_document'      => $item->type_document,
                'signataire_nom'     => $item->signataire_nom ?? $paroisse->cure_nom,
                'signataire_qualite' => $item->signataire_qualite ?? 'Le Curé de la Paroisse',
            ],
        ]);
    }

    /**
     * Alias de compatibilité retournant les données d'impression en JSON.
     */
    public function pdf(Request $request, mixed $document): JsonResponse
    {
        return $this->printData($request, $document);
    }

    /**
     * Supprimer un document généré de l'historique.
     */
    public function destroy(Request $request, mixed $document): JsonResponse
    {
        $item = $this->resolveDocument($document);
        $item->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Document officiel supprimé de l\'historique.',
        ]);
    }

    /**
     * Moteur de fusion de template.
     */
    private function renderDocumentTemplate(
        ModeleDocument $modele,
        Catechumene $cat,
        ?CatecheseConfiguration $paroisse,
        ?AnneeCatechese $annee,
        string $reference,
        array $customTags = []
    ): array {
        $inscription = InscriptionAnnuelle::with(['classe', 'niveau', 'section'])
            ->where('catechumene_id', $cat->id)
            ->when($annee, fn($q) => $q->where('annee_catechese_id', $annee->id))
            ->latest('id')
            ->first();

        if (!$inscription) {
            $inscription = InscriptionAnnuelle::with(['classe', 'niveau', 'section'])
                ->where('catechumene_id', $cat->id)
                ->latest('id')
                ->first();
        }

        $prenoms = $cat->prenoms ?? ($cat->prenom ?? '');
        $currentNiveau = $inscription?->niveau;

        // Détermination intelligente du niveau suivant
        $niveauSuivant = '';
        if ($currentNiveau) {
            $nextNiveau = Niveau::where('section_id', $currentNiveau->section_id)
                ->where('ordre_affichage', '>', $currentNiveau->ordre_affichage)
                ->orderBy('ordre_affichage', 'asc')
                ->first();

            if ($nextNiveau) {
                $niveauSuivant = $nextNiveau->nom;
            } else {
                $niveauSuivant = 'Année supérieure';
            }
        }

        // Responsable de coordination depuis responsables_paroisse ou paroisse_configurations
        $responsableNom = null;
        if ($paroisse?->id) {
            $resp = DB::table('responsables_paroisse')
                ->where('paroisse_configuration_id', $paroisse->id)
                ->where('statut', 'actif')
                ->where(function($q) {
                    $q->where('fonction', 'like', '%coordination%')
                      ->orWhere('fonction', 'like', '%catéchèse%')
                      ->orWhere('fonction', 'like', '%responsable%');
                })
                ->orderBy('id', 'asc')
                ->first();

            if (!$resp) {
                $resp = DB::table('responsables_paroisse')
                    ->where('paroisse_configuration_id', $paroisse->id)
                    ->where('statut', 'actif')
                    ->orderBy('id', 'asc')
                    ->first();
            }
            $responsableNom = $resp?->nom_prenoms;
        }
        $coordNom = $responsableNom ?: ($paroisse?->coordination_nom ?: 'La Coordination');

        // Mutation / motif de départ
        $mutation = DB::table('mutations_catechumenes')
            ->where('catechumene_id', $cat->id)
            ->latest('id')
            ->first();
        $motifDepart = $mutation?->motif ?: 'déménagement';

        // Valeurs de paroisse
        $nomParoisse = $paroisse?->nom_paroisse ?: 'Paroisse Catholique';
        $cureNom = $modele->signature_nom ?: ($paroisse?->cure_nom ?: 'Le Curé');
        $villeParoisse = $paroisse?->ville ?: ($paroisse?->commune ?: 'Abidjan');
        $diocese = $paroisse?->diocese ?: '';
        $doyenne = $paroisse?->doyenne ?: '';
        $adresse = $paroisse?->adresse ?: '';
        $telephone = $paroisse?->telephone ?: '';
        $email = $paroisse?->email ?: '';

        // Catéchumène et inscription
        $classeNom = $inscription?->classe?->nom ?? ($cat->classe_scolaire ?? 'Non assignée');
        $niveauNom = $inscription?->niveau?->nom ?? '';
        $sectionNom = $inscription?->section?->nom ?? '';
        $anneeLibelle = $annee?->libelle ?? (date('Y') . '-' . (date('Y') + 1));
        $dateAujourdhui = date('d/m/Y');

        // Balises de base exhaustives avec toutes les variantes
        $baseTags = [
            // Catéchumène
            'matricule'                => $cat->matricule ?? ($cat->code_catechumene ?? ''),
            'nom'                      => strtoupper($cat->nom ?? ''),
            'prenom'                   => ucwords($prenoms),
            'prenoms'                  => ucwords($prenoms),
            'nom_complet'              => trim(($cat->nom ?? '') . ' ' . $prenoms),
            'date_naissance'           => $cat->date_naissance ? (is_string($cat->date_naissance) ? date('d/m/Y', strtotime($cat->date_naissance)) : $cat->date_naissance->format('d/m/Y')) : '',
            'lieu_naissance'           => $cat->lieu_naissance ?? '',
            'pere_nom'                 => $cat->pere_nom_complet ?? ($cat->nom_pere ?? ''),
            'nom_pere'                 => $cat->pere_nom_complet ?? ($cat->nom_pere ?? ''),
            'mere_nom'                 => $cat->mere_nom_complet ?? ($cat->nom_mere ?? ''),
            'nom_mere'                 => $cat->mere_nom_complet ?? ($cat->nom_mere ?? ''),
            'telephone'                => $cat->telephone ?? '',

            // Sacrements
            'date_bapteme'             => $cat->date_bapteme ? (is_string($cat->date_bapteme) ? date('d/m/Y', strtotime($cat->date_bapteme)) : $cat->date_bapteme->format('d/m/Y')) : 'En préparation',
            'lieu_bapteme'             => $cat->lieu_bapteme ?? $nomParoisse,
            'date_premiere_communion'  => $cat->date_premiere_communion ? (is_string($cat->date_premiere_communion) ? date('d/m/Y', strtotime($cat->date_premiere_communion)) : $cat->date_premiere_communion->format('d/m/Y')) : 'En préparation',
            'date_confirmation'        => $cat->date_confirmation ? (is_string($cat->date_confirmation) ? date('d/m/Y', strtotime($cat->date_confirmation)) : $cat->date_confirmation->format('d/m/Y')) : 'En préparation',
            'parrain_marraine'         => $cat->parrain_nom_complet ?? ($cat->marraine_nom_complet ?? 'N/A'),

            // Cursus
            'classe'                   => $classeNom,
            'niveau'                   => $niveauNom,
            'section'                  => $sectionNom,
            'annee_pastorale'          => $anneeLibelle,
            'niveau_suivant'           => $niveauSuivant,
            'motif_depart'             => $motifDepart,
            'motif'                    => $motifDepart,

            // Paroisse & Administration
            'paroisse'                 => $nomParoisse,
            'nom_paroisse'             => $nomParoisse,
            'paroisse_nom'             => $nomParoisse,
            'diocese'                  => $diocese,
            'paroisse_diocese'         => $diocese,
            'doyenne'                  => $doyenne,
            'paroisse_doyenne'         => $doyenne,
            'ville'                    => $villeParoisse,
            'ville_paroisse'           => $villeParoisse,
            'paroisse_ville'           => $villeParoisse,
            'adresse'                  => $adresse,
            'adresse_paroisse'         => $adresse,
            'paroisse_adresse'         => $adresse,
            'telephone_paroisse'       => $telephone,
            'paroisse_telephone'       => $telephone,
            'email_paroisse'           => $email,
            'paroisse_email'           => $email,

            // Responsables & Signataires
            'cure_nom'                 => $cureNom,
            'nom_cure'                 => $cureNom,
            'cure'                     => $cureNom,
            'paroisse_cure'            => $cureNom,
            'responsable_coordination' => $coordNom,
            'coordination_responsable' => $coordNom,
            'coordination_nom'         => $coordNom,
            'responsable_catechese'    => $coordNom,

            // Dates & Documents
            'date_du_jour'             => $dateAujourdhui,
            'date_generation'          => $dateAujourdhui,
            'reference_document'       => $reference,
            'reference'                => $reference,
        ];

        // Remplacement prioritaire par les variables personnalisées
        foreach ($customTags as $k => $v) {
            $cleanKey = trim(str_replace(['{', '}'], '', (string) $k));
            if ($cleanKey !== '' && $v !== null && $v !== '') {
                $baseTags[$cleanKey] = (string) $v;
            }
        }

        // Remplacement dans le contenu HTML
        $html = $modele->contenu ?? '';
        foreach ($baseTags as $key => $val) {
            $pattern = '/\{\{\s*' . preg_quote($key, '/') . '\s*\}\}/i';
            $html = preg_replace($pattern, (string) $val, $html);
        }

        // Préparation du dictionnaire de métadonnées avec balises {{clé}}
        $tagsOutput = [];
        foreach ($baseTags as $key => $val) {
            $tagsOutput['{{' . $key . '}}'] = (string) $val;
        }

        return [
            'html' => $html,
            'tags' => $tagsOutput,
        ];
    }
}
