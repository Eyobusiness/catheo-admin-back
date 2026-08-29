<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ModeleDocumentResource;
use App\Models\CatecheseConfiguration;
use App\Models\ModeleDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ModeleDocumentController extends Controller
{
    private function resolveModele(mixed $modele): ModeleDocument
    {
        if ($modele instanceof ModeleDocument) {
            return $modele;
        }

        $item = is_numeric($modele)
            ? ModeleDocument::find((int) $modele)
            : ModeleDocument::where('uuid', $modele)->orWhere('code', $modele)->first();

        if (!$item) {
            abort(response()->json([
                'status'  => 'error',
                'message' => 'Modèle de document introuvable.',
            ], 404));
        }

        return $item;
    }

    /**
     * Liste des modèles de documents.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id ?? CatecheseConfiguration::value('id');

        $query = ModeleDocument::query();

        if ($paroisseId) {
            $query->where(function ($q) use ($paroisseId) {
                $q->where('paroisse_configuration_id', $paroisseId)
                  ->orWhereNull('paroisse_configuration_id');
            });
        }

        if ($request->filled('type_document') && $request->type_document !== 'all' && $request->type_document !== 'tous') {
            $query->where('type_document', $request->type_document);
        }

        if ($request->filled('statut') && $request->statut !== 'all' && $request->statut !== 'tous') {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('titre', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $modeles = $query->orderBy('titre', 'asc')->paginate($request->integer('per_page', 15));

        return response()->json([
            'status' => 'success',
            'data'   => ModeleDocumentResource::collection($modeles->items()),
            'meta'   => [
                'current_page' => $modeles->currentPage(),
                'last_page'    => $modeles->lastPage(),
                'per_page'     => $modeles->perPage(),
                'total'        => $modeles->total(),
            ],
        ]);
    }

    /**
     * Enregistrer un nouveau modèle de document.
     */
    public function store(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id ?? CatecheseConfiguration::value('id');

        $validated = $request->validate([
            'titre'                 => ['required', 'string', 'max:255'],
            'code'                  => ['nullable', 'string', 'max:100'],
            'type_document'         => ['required', 'string', 'max:100'],
            'description'           => ['nullable', 'string'],
            'contenu'               => ['required', 'string'],
            'variables_disponibles' => ['nullable', 'array'],
            'signature_nom'         => ['nullable', 'string', 'max:255'],
            'signature_titre'       => ['nullable', 'string', 'max:255'],
            'statut'                => ['nullable', 'string', 'in:actif,inactif'],
        ]);

        $code = !empty($validated['code'])
            ? Str::slug($validated['code'], '_')
            : Str::slug($validated['titre'], '_');

        $modele = ModeleDocument::create([
            'paroisse_configuration_id' => $paroisseId,
            'titre'                     => $validated['titre'],
            'code'                      => strtoupper($code),
            'type_document'             => $validated['type_document'],
            'description'               => $validated['description'] ?? null,
            'contenu'                   => $validated['contenu'],
            'variables_disponibles'     => $validated['variables_disponibles'] ?? $this->getDefaultVariables(),
            'signature_nom'             => $validated['signature_nom'] ?? null,
            'signature_titre'           => $validated['signature_titre'] ?? 'Le Curé de la Paroisse',
            'statut'                    => $validated['statut'] ?? 'actif',
            'is_system'                 => false,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Modèle de document créé avec succès.',
            'data'    => new ModeleDocumentResource($modele),
        ], 201);
    }

    /**
     * Détails d'un modèle.
     */
    public function show(Request $request, mixed $modele): JsonResponse
    {
        $item = $this->resolveModele($modele);

        return response()->json([
            'status' => 'success',
            'data'   => new ModeleDocumentResource($item),
        ]);
    }

    /**
     * Mettre à jour un modèle.
     */
    public function update(Request $request, mixed $modele): JsonResponse
    {
        $item = $this->resolveModele($modele);

        $validated = $request->validate([
            'titre'                 => ['sometimes', 'string', 'max:255'],
            'code'                  => ['sometimes', 'nullable', 'string', 'max:100'],
            'type_document'         => ['sometimes', 'string', 'max:100'],
            'description'           => ['nullable', 'string'],
            'contenu'               => ['sometimes', 'string'],
            'variables_disponibles' => ['nullable', 'array'],
            'signature_nom'         => ['nullable', 'string', 'max:255'],
            'signature_titre'       => ['nullable', 'string', 'max:255'],
            'statut'                => ['sometimes', 'string', 'in:actif,inactif'],
        ]);

        if (isset($validated['code'])) {
            $validated['code'] = strtoupper(Str::slug($validated['code'], '_'));
        }

        $item->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Modèle de document mis à jour avec succès.',
            'data'    => new ModeleDocumentResource($item),
        ]);
    }

    /**
     * Activer / Désactiver un modèle.
     */
    public function toggleStatus(Request $request, mixed $modele): JsonResponse
    {
        $item = $this->resolveModele($modele);
        $newStatus = $item->statut === 'actif' ? 'inactif' : 'actif';
        $item->update(['statut' => $newStatus]);

        return response()->json([
            'status'  => 'success',
            'message' => "Modèle de document désormais {$newStatus}.",
            'data'    => new ModeleDocumentResource($item),
        ]);
    }

    /**
     * Supprimer un modèle de document.
     */
    public function destroy(Request $request, mixed $modele): JsonResponse
    {
        $item = $this->resolveModele($modele);

        if ($item->is_system) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Impossible de supprimer un modèle système prédéfini.',
            ], 422);
        }

        $item->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Modèle de document supprimé avec succès.',
        ]);
    }

    /**
     * Liste des variables dynamiques supportées pour l'éditeur de templates.
     */
    public function variablesSysteme(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data'   => $this->getDefaultVariables(),
        ]);
    }

    private function getDefaultVariables(): array
    {
        return [
            ['tag' => '{{matricule}}', 'description' => 'Numéro matricule unique du catéchumène'],
            ['tag' => '{{nom}}', 'description' => 'Nom de famille du catéchumène'],
            ['tag' => '{{prenom}}', 'description' => 'Prénoms du catéchumène'],
            ['tag' => '{{nom_complet}}', 'description' => 'Nom et Prénoms du catéchumène'],
            ['tag' => '{{date_naissance}}', 'description' => 'Date de naissance (JJ/MM/AAAA)'],
            ['tag' => '{{lieu_naissance}}', 'description' => 'Lieu de naissance'],
            ['tag' => '{{pere_nom}}', 'description' => 'Nom complet du père'],
            ['tag' => '{{mere_nom}}', 'description' => 'Nom complet de la mère'],
            ['tag' => '{{classe}}', 'description' => 'Classe / Groupe actuel'],
            ['tag' => '{{niveau}}', 'description' => 'Niveau pastoral (ex: Initiation 1)'],
            ['tag' => '{{section}}', 'description' => 'Section pastorale (ex: Enfants)'],
            ['tag' => '{{annee_pastorale}}', 'description' => 'Année pastorale (ex: 2026-2027)'],
            ['tag' => '{{date_bapteme}}', 'description' => 'Date du baptême'],
            ['tag' => '{{lieu_bapteme}}', 'description' => 'Paroisse ou lieu du baptême'],
            ['tag' => '{{date_premiere_communion}}', 'description' => 'Date de la 1ère communion'],
            ['tag' => '{{date_confirmation}}', 'description' => 'Date de la confirmation'],
            ['tag' => '{{parrain_marraine}}', 'description' => 'Nom du parrain ou de la marraine'],
            ['tag' => '{{paroisse_nom}}', 'description' => 'Nom officiel de la paroisse'],
            ['tag' => '{{paroisse_diocese}}', 'description' => 'Nom du diocèse'],
            ['tag' => '{{paroisse_ville}}', 'description' => 'Ville de la paroisse'],
            ['tag' => '{{paroisse_cure}}', 'description' => 'Nom du Curé de la paroisse'],
            ['tag' => '{{date_du_jour}}', 'description' => 'Date de génération du document'],
            ['tag' => '{{reference_document}}', 'description' => 'Numéro de référence officiel du document'],
        ];
    }
}
