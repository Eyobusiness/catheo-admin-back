<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreCampagnePreinscriptionRequest;
use App\Http\Requests\Api\V1\UpdateCampagnePreinscriptionRequest;
use App\Http\Resources\Api\V1\CampagnePreinscriptionResource;
use App\Models\AnneeCatechese;
use App\Models\CampagnePreinscription;
use App\Models\CatecheseConfiguration;
use App\Models\Section;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampagnePreinscriptionController extends Controller
{
    /**
     * Liste des campagnes de préinscription.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user() ?? auth('sanctum')->user();
        $paroisseId = $user?->paroisse_configuration_id 
            ?? $request->input('paroisse_configuration_id')
            ?? $request->header('X-Paroisse-Id');

        if (!$paroisseId) {
            return response()->json([
                'status' => 'success',
                'data'   => [],
                'meta'   => ['total' => 0],
            ]);
        }

        $query = CampagnePreinscription::with('anneeCatechese')
            ->withCount('preinscriptions')
            ->where('paroisse_configuration_id', (int) $paroisseId);

        if ($request->filled('statut')) {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('annee_catechese_id')) {
            $anneeId = AnneeCatechese::where('uuid', $request->annee_catechese_id)
                ->orWhere('id', $request->annee_catechese_id)
                ->value('id');
            if ($anneeId) {
                $query->where('annee_catechese_id', $anneeId);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('titre', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $campagnes = $query->latest('date_debut')->get();

        return response()->json([
            'status' => 'success',
            'meta'   => [
                'total_elements' => $campagnes->count(),
            ],
            'data'   => CampagnePreinscriptionResource::collection($campagnes),
        ]);
    }

    /**
     * Consultation publique d'une campagne de préinscription (Pour le formulaire web des parents).
     */
    public function showActivePublic(): JsonResponse
    {
        return $this->showPublic('active');
    }

    /**
     * Consultation publique d'une campagne de préinscription (Pour le formulaire web des parents).
     */
    public function showPublic(string $uuid): JsonResponse
    {
        $campagne = CampagnePreinscription::where('uuid', $uuid)
            ->orWhere('id', $uuid)
            ->first();

        if (!$campagne && ($uuid === 'active' || $uuid === 'current' || $uuid === 'default' || empty($uuid))) {
            $campagne = CampagnePreinscription::where('statut', 'ouverte')->latest('date_debut')->first()
                ?? CampagnePreinscription::latest('id')->first();
        }

        if (!$campagne) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Campagne de préinscription introuvable.',
            ], 404);
        }

        $campagne->load(['anneeCatechese', 'paroisse']);
        $paroisseId = (int) $campagne->paroisse_configuration_id;

        // Configuration complète de la paroisse
        $paroisseConfig = \App\Models\CatecheseConfiguration::find($paroisseId);

        // Sections et niveaux autorisés pour le formulaire parent
        $allParoisseSections = \App\Models\Section::where('paroisse_configuration_id', $paroisseId)
            ->where(function($q) {
                $q->where('statut', 'actif')->orWhere('statut', 'Active')->orWhereNull('statut');
            })
            ->with(['niveaux' => function ($q) {
                $q->where(function($sq) {
                    $sq->where('statut', 'actif')->orWhere('statut', 'Active')->orWhereNull('statut');
                })->orderBy('ordre_affichage');
            }])
            ->orderBy('ordre_affichage')
            ->get();

        if (!empty($campagne->sections_autorisees) && is_array($campagne->sections_autorisees) && count($campagne->sections_autorisees) > 0) {
            $authList = array_map(function($v) { return strtolower(trim((string)$v)); }, $campagne->sections_autorisees);
            
            $filteredSections = $allParoisseSections->filter(function($sec) use ($authList) {
                $nom = strtolower(trim($sec->nom));
                $code = strtolower(trim($sec->code ?? ''));
                $uuid = strtolower(trim($sec->uuid ?? ''));
                $id = (string) $sec->id;

                if (in_array($nom, $authList) || in_array($code, $authList) || in_array($uuid, $authList) || in_array($id, $authList)) {
                    return true;
                }

                foreach ($authList as $item) {
                    if (str_contains($nom, 'primair') && str_contains($item, 'primair')) return true;
                    if ((str_contains($nom, 'colleg') || str_contains($nom, 'collèg')) && (str_contains($item, 'colleg') || str_contains($item, 'collèg'))) return true;
                    if (str_contains($nom, 'jeun') && str_contains($item, 'jeun')) return true;
                    if (str_contains($nom, 'adult') && str_contains($item, 'adult')) return true;
                }
                return false;
            });

            $sections = $filteredSections->isNotEmpty() ? $filteredSections->values() : $allParoisseSections;
        } else {
            $sections = $allParoisseSections;
        }

        $niveaux = $sections->pluck('niveaux')->flatten();

        $cebs = \App\Models\Ceb::where('paroisse_configuration_id', $paroisseId)
            ->where(function($q) {
                $q->where('statut', 'actif')->orWhere('statut', 'Active')->orWhereNull('statut');
            })
            ->orderBy('nom')
            ->get();

        $mouvements = \App\Models\Mouvement::where('paroisse_configuration_id', $paroisseId)
            ->where(function($q) {
                $q->where('statut', 'actif')->orWhere('statut', 'Active')->orWhereNull('statut');
            })
            ->orderBy('nom')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => [
                'campagne'   => new \App\Http\Resources\Api\V1\CampagnePreinscriptionResource($campagne),
                'paroisse'   => $paroisseConfig ? new \App\Http\Resources\Api\V1\CatecheseConfigurationResource($paroisseConfig) : [
                    'nom_paroisse' => $campagne->paroisse?->nom,
                    'nom'          => $campagne->paroisse?->nom,
                    'commune'      => $campagne->paroisse?->commune,
                    'ville'        => $campagne->paroisse?->ville,
                    'logo_url'     => $campagne->paroisse?->logo_url,
                ],
                'sections'   => \App\Http\Resources\Api\V1\SectionResource::collection($sections),
                'niveaux'    => \App\Http\Resources\Api\V1\NiveauResource::collection($niveaux),
                'cebs'       => \App\Http\Resources\Api\V1\CebResource::collection($cebs),
                'mouvements' => \App\Http\Resources\Api\V1\MouvementResource::collection($mouvements),
            ],
        ]);
    }

    /**
     * Créer une campagne de préinscription.
     */
    public function store(StoreCampagnePreinscriptionRequest $request): JsonResponse
    {
        $user = $request->user() ?? auth('sanctum')->user();
        $paroisseId = $user?->paroisse_configuration_id 
            ?? $request->input('paroisse_configuration_id')
            ?? $request->header('X-Paroisse-Id');

        if (!$paroisseId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'L\'identifiant de la paroisse est obligatoire.',
            ], 422);
        }

        $validated = $request->validated();

        $anneeParam = $validated['annee_catechese_id'] ?? null;
        $anneeId = null;
        if ($anneeParam) {
            $anneeId = AnneeCatechese::where('paroisse_configuration_id', (int) $paroisseId)
                ->where(function ($q) use ($anneeParam) {
                    $q->where('uuid', $anneeParam)
                      ->orWhere('id', $anneeParam)
                      ->orWhere('libelle', $anneeParam);
                })
                ->value('id');
        }
        if (!$anneeId) {
            $anneeId = AnneeCatechese::getAnneeCourante((int) $paroisseId)?->id;
        }

        $validated['annee_catechese_id'] = $anneeId;
        $validated['paroisse_configuration_id'] = $paroisseId;
        $validated['titre'] = $validated['titre'] ?? $validated['nom'] ?? 'Campagne de préinscription';
        $validated['statut'] = $validated['statut'] ?? 'ouverte';
        unset($validated['nom']);

        $campagne = CampagnePreinscription::create($validated);
        $campagne->load('anneeCatechese')->loadCount('preinscriptions');

        return response()->json([
            'status'  => 'success',
            'message' => 'Campagne de préinscription créée avec succès.',
            'data'    => new CampagnePreinscriptionResource($campagne),
        ], 201);
    }

    /**
     * Détails d'une campagne.
     */
    public function show(Request $request, mixed $campagne): JsonResponse
    {
        $user = $request->user() ?? auth('sanctum')->user();
        $model = $this->resolveCampagne($campagne);
        $this->authorizeTenant($user?->paroisse_configuration_id, $model->paroisse_configuration_id);

        $model->load('anneeCatechese')->loadCount('preinscriptions');

        return response()->json([
            'status' => 'success',
            'data'   => new CampagnePreinscriptionResource($model),
        ]);
    }

    /**
     * Mise à jour d'une campagne.
     */
    public function update(UpdateCampagnePreinscriptionRequest $request, mixed $campagne): JsonResponse
    {
        $user = $request->user() ?? auth('sanctum')->user();
        $model = $this->resolveCampagne($campagne);
        $this->authorizeTenant($user?->paroisse_configuration_id, $model->paroisse_configuration_id);
        $validated = $request->validated();

        if (isset($validated['nom'])) {
            $validated['titre'] = $validated['nom'];
            unset($validated['nom']);
        }

        if (!empty($validated['annee_catechese_id'])) {
            $anneeId = AnneeCatechese::where('uuid', $validated['annee_catechese_id'])
                ->orWhere('id', $validated['annee_catechese_id'])
                ->value('id');
            if ($anneeId) {
                $validated['annee_catechese_id'] = $anneeId;
            }
        }

        $model->update($validated);
        $model->load('anneeCatechese')->loadCount('preinscriptions');

        return response()->json([
            'status'  => 'success',
            'message' => 'Campagne mise à jour avec succès.',
            'data'    => new CampagnePreinscriptionResource($model),
        ]);
    }

    /**
     * Basculer le statut d'une campagne.
     */
    public function updateStatus(Request $request, mixed $campagne): JsonResponse
    {
        $user = $request->user() ?? auth('sanctum')->user();
        $model = $this->resolveCampagne($campagne);
        $this->authorizeTenant($user?->paroisse_configuration_id, $model->paroisse_configuration_id);

        $statut = $request->input('statut') ?? $request->input('status');
        if ($request->has('est_ouverte')) {
            $statut = $request->boolean('est_ouverte') ? 'ouverte' : 'fermee';
        }

        if (!$statut) {
            $statut = $model->statut === 'ouverte' ? 'fermee' : 'ouverte';
        }

        $model->update(['statut' => $statut]);
        $model->load('anneeCatechese')->loadCount('preinscriptions');

        return response()->json([
            'status'  => 'success',
            'message' => 'Statut de la campagne mis à jour avec succès.',
            'data'    => new CampagnePreinscriptionResource($model),
        ]);
    }

    /**
     * Suppression d'une campagne.
     */
    public function destroy(Request $request, mixed $campagne): JsonResponse
    {
        $user = $request->user() ?? auth('sanctum')->user();
        $model = $this->resolveCampagne($campagne);
        $this->authorizeTenant($user?->paroisse_configuration_id, $model->paroisse_configuration_id);

        $model->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Campagne supprimée avec succès.',
        ]);
    }

    private function resolveCampagne(mixed $campagne): CampagnePreinscription
    {
        if ($campagne instanceof CampagnePreinscription && $campagne->exists) {
            return $campagne;
        }

        $identifier = is_object($campagne) ? ($campagne->uuid ?? $campagne->id ?? null) : $campagne;

        return CampagnePreinscription::where('uuid', $identifier)
            ->orWhere('id', $identifier)
            ->firstOrFail();
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}

