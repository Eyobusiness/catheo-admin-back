<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SuperAdmin\StoreResponsableOrganisationRequest;
use App\Http\Resources\Api\V1\Organisation\OrganisationUserResource;
use App\Http\Resources\Api\V1\SuperAdmin\AbonnementResource;
use App\Http\Resources\Api\V1\SuperAdmin\FormuleResource;
use App\Models\Abonnement;
use App\Models\CatecheseConfiguration;
use App\Models\Formule;
use App\Models\Organisation;
use App\Models\Produit;
use App\Models\Profil;
use App\Models\User;
use App\Services\SuperAdmin\ActionAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SuperAdminOrganisationController extends Controller
{
    /**
     * Liste de toutes les organisations de la plateforme.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Organisation::with(['produit', 'paroisse', 'users'])
            ->withCount(['membres', 'activites', 'users'])
            ->latest('id');

        if ($request->filled('type_organisation') && $request->type_organisation !== 'tous') {
            $query->where('type_organisation', $request->type_organisation);
        }

        if ($request->filled('statut') && $request->statut !== 'tous') {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('mode') && $request->mode !== 'tous') {
            if ($request->mode === 'independant') {
                $query->where(function ($q) {
                    $q->where('mode', 'independant')->orWhereNull('paroisse_configuration_id');
                });
            } elseif ($request->mode === 'liee') {
                $query->where(function ($q) {
                    $q->where('mode', 'liee')->orWhereNotNull('paroisse_configuration_id');
                });
            }
        }

        if ($request->filled('paroisse_id')) {
            $pVal = $request->paroisse_id;
            $pId = is_numeric($pVal)
                ? (int) $pVal
                : CatecheseConfiguration::where('uuid', $pVal)->value('id');
            if ($pId) {
                $query->where('paroisse_configuration_id', $pId);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nom', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('responsable_nom', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 15);
        $result = $query->paginate($perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Liste des organisations récupérée avec succès.',
            'data'    => $result->items(),
            'meta'    => [
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
                'per_page'     => $result->perPage(),
                'total'        => $result->total(),
            ],
        ]);
    }

    /**
     * Création d'organisation(s) en Super Admin (Support organisation liée et organisation indépendante).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'paroisse_id'           => ['nullable'],
            'independant'           => ['nullable', 'boolean'],
            'produits'              => ['nullable', 'array'],
            'produits.*'            => ['string', 'in:OPPE,OPPJ,OPPA,oppe,oppj,oppa'],
            'produit'               => ['nullable', 'string', 'in:OPPE,OPPJ,OPPA,oppe,oppj,oppa'],
            'type_organisation'     => ['nullable', 'string', 'in:OPPE,OPPJ,OPPA,oppe,oppj,oppa'],
            'nom'                   => ['nullable', 'string', 'max:255'],
            'description'           => ['nullable', 'string'],
            'telephone'             => ['nullable', 'string', 'max:30'],
            'email'                 => ['nullable', 'email', 'max:150'],
            'adresse'               => ['nullable', 'string'],
            'responsable_nom'       => ['nullable', 'string', 'max:255'],
            'responsable_telephone' => ['nullable', 'string', 'max:30'],
            'responsable_email'     => ['nullable', 'email', 'max:150'],
        ]);

        // F25.8 Fix: independant flag is explicit; fallback only when no paroisse_id AND explicitement marqué indépendant
        $isIndependant = $request->boolean('independant') === true;

        // SCÉNARIO B : Organisation indépendante (sans paroisse, sans CATHEO)
        if ($isIndependant) {
            $code = strtoupper(trim((string) (
                $validated['type_organisation']
                ?? $validated['produit']
                ?? (!empty($validated['produits']) && is_array($validated['produits']) ? $validated['produits'][0] : '')
            )));

            if (empty($code)) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Veuillez spécifier le type d\'organisation (OPPE, OPPJ, OPPA).',
                ], 422);
            }

            if (empty($validated['nom'])) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'Le nom est obligatoire pour une organisation indépendante.',
                ], 422);
            }

            $produit = Produit::where('code', $code)->first();
            if (!$produit) {
                return response()->json([
                    'status'  => 'error',
                    'message' => "Produit [{$code}] introuvable.",
                ], 404);
            }

            $slugNom = strtoupper(Str::slug(Str::limit($validated['nom'], 12, '')));
            $codeOrg = "{$code}-IND-" . ($slugNom ?: 'ORG');
            if (Organisation::where('code', $codeOrg)->exists()) {
                $codeOrg .= '-' . strtoupper(Str::random(4));
            }

            $org = Organisation::create([
                'mode'                      => 'independant',
                'paroisse_configuration_id' => null,
                'produit_id'                => $produit->id,
                'type_organisation'         => $code,
                'code'                      => $codeOrg,
                'nom'                       => $validated['nom'],
                'description'               => $validated['description'] ?? "Organisation indépendante {$code} ({$validated['nom']})",
                'telephone'                 => $validated['telephone'] ?? null,
                'email'                     => $validated['email'] ?? null,
                'adresse'                   => $validated['adresse'] ?? null,
                'responsable_nom'           => $validated['responsable_nom'] ?? null,
                'responsable_telephone'     => $validated['responsable_telephone'] ?? null,
                'responsable_email'         => $validated['responsable_email'] ?? null,
                'statut'                    => 'actif',
                'date_activation'           => now()->toDateString(),
            ]);

            // F25.8: Handle logo upload for independent organisation
            if ($request->hasFile('logo') && $request->file('logo')->isValid()) {
                $logoFile = $request->file('logo');
                $logoName = 'org_' . $org->uuid . '_' . time() . '.' . $logoFile->getClientOriginalExtension();
                $logoFile->storeAs('organisations/logos', $logoName, 'public');
                $org->update(['logo_path' => $logoName]);
            }

            $org->load('produit');

            ActionAuditService::log(
                action: 'create',
                module: 'Organisation',
                description: "Création de l'organisation indépendante {$org->nom} ({$code})",
                entite: $org,
                anciennesValeurs: null,
                nouvellesValeurs: $org->toArray(),
                paroisseId: null,
                organisationId: $org->id,
                request: $request
            );

            return response()->json([
                'status'  => 'success',
                'message' => 'Organisation indépendante créée avec succès.',
                'data'    => $org,
                'meta'    => [
                    'mode'          => 'independant',
                    'created_count' => 1,
                    'skipped_count' => 0,
                ],
            ], 201);
        }

        // SCÉNARIO A : Organisation rattachée à une paroisse
        if (empty($validated['paroisse_id'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Le champ paroisse_id est obligatoire pour une organisation liée à une paroisse.',
            ], 422);
        }

        $paroisseVal = $validated['paroisse_id'];
        $paroisse = is_numeric($paroisseVal)
            ? CatecheseConfiguration::find((int) $paroisseVal)
            : CatecheseConfiguration::where('uuid', $paroisseVal)->first();

        if (!$paroisse) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Paroisse introuvable.',
            ], 404);
        }

        // Déterminer la liste des codes produits à créer
        $codes = [];
        if (!empty($validated['produits']) && is_array($validated['produits'])) {
            $codes = array_map(fn($c) => strtoupper(trim($c)), $validated['produits']);
        } elseif (!empty($validated['produit'])) {
            $codes = [strtoupper(trim($validated['produit']))];
        } elseif (!empty($validated['type_organisation'])) {
            $codes = [strtoupper(trim($validated['type_organisation']))];
        }

        if (empty($codes)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Veuillez spécifier au moins un produit valide (OPPE, OPPJ, OPPA).',
            ], 422);
        }

        $createdOrganisations = [];
        $skippedOrganisations = [];

        DB::transaction(function () use ($codes, $paroisse, $validated, &$createdOrganisations, &$skippedOrganisations, $request) {
            foreach ($codes as $code) {
                $produit = Produit::where('code', $code)->first();
                if (!$produit) {
                    continue;
                }

                $alreadyExists = Organisation::where('paroisse_configuration_id', $paroisse->id)
                    ->where('type_organisation', $code)
                    ->exists();

                if ($alreadyExists) {
                    $skippedOrganisations[] = $code;
                    continue;
                }

                $nomOrg = !empty($validated['nom']) && count($codes) === 1
                    ? $validated['nom']
                    : "{$code} {$paroisse->nom_paroisse}";

                $codeOrg = "{$code}-" . strtoupper(Str::slug($paroisse->code_paroisse ?? $paroisse->nom_paroisse));

                $org = Organisation::create([
                    'mode'                      => 'liee',
                    'paroisse_configuration_id' => $paroisse->id,
                    'produit_id'                => $produit->id,
                    'type_organisation'         => $code,
                    'code'                      => $codeOrg,
                    'nom'                       => $nomOrg,
                    'description'               => $validated['description'] ?? "Organisation {$code} rattachée à {$paroisse->nom_paroisse}",
                    'telephone'                 => $validated['telephone'] ?? $paroisse->telephone,
                    'email'                     => $validated['email'] ?? $paroisse->email,
                    'adresse'                   => $validated['adresse'] ?? $paroisse->adresse,
                    'responsable_nom'           => $validated['responsable_nom'] ?? null,
                    'responsable_telephone'     => $validated['responsable_telephone'] ?? null,
                    'responsable_email'         => $validated['responsable_email'] ?? null,
                    'statut'                    => 'actif',
                    'date_activation'           => now()->toDateString(),
                ]);

                $org->load(['produit', 'paroisse']);
                $createdOrganisations[] = $org;

                ActionAuditService::log(
                    action: 'create',
                    module: 'Organisation',
                    description: "Création de l'organisation {$org->nom} ({$code}) pour la paroisse {$paroisse->nom_paroisse}",
                    entite: $org,
                    anciennesValeurs: null,
                    nouvellesValeurs: $org->toArray(),
                    paroisseId: $paroisse->id,
                    organisationId: $org->id,
                    request: $request
                );
            }
        });

        return response()->json([
            'status'  => 'success',
            'message' => count($createdOrganisations) . ' organisation(s) créée(s) avec succès.',
            'data'    => count($createdOrganisations) === 1 ? $createdOrganisations[0] : $createdOrganisations,
            'meta'    => [
                'mode'          => 'liee',
                'created_count' => count($createdOrganisations),
                'skipped_count' => count($skippedOrganisations),
                'skipped'       => $skippedOrganisations,
            ],
        ], 201);
    }

    /**
     * Fiche d'une organisation avec tous ses modules métiers.
     */
    public function show(string $id): JsonResponse
    {
        $organisation = is_numeric($id)
            ? Organisation::with([
                'produit',
                'paroisse',
                'users.profil',
                'membres',
                'activites',
                'campagnesPelerinage',
                'operations',
                'abonnements.formule.produit',
                'abonnementActif.formule.produit',
            ])->find((int) $id)
            : Organisation::with([
                'produit',
                'paroisse',
                'users.profil',
                'membres',
                'activites',
                'campagnesPelerinage',
                'operations',
                'abonnements.formule.produit',
                'abonnementActif.formule.produit',
            ])->where('uuid', $id)->first();

        if (!$organisation) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Organisation introuvable.',
            ], 404);
        }

        $mode = $organisation->mode ?? ($organisation->paroisse_configuration_id ? 'liee' : 'independant');

        // Préparer les données enrichies avec tous les blocs d'administration demandés
        $data = $organisation->toArray();
        $data['mode'] = $mode;

        // 1. Informations
        $data['informations'] = [
            'id'                 => $organisation->uuid,
            'uuid'               => $organisation->uuid,
            'id_interne'         => $organisation->id,
            'nom'                => $organisation->nom,
            'code'               => $organisation->code,
            'type_organisation'  => $organisation->type_organisation,
            'mode'               => $mode,
            'statut'             => $organisation->statut,
            'description'        => $organisation->description,
            'logo_url'           => $organisation->logo_url,
            'telephone'          => $organisation->telephone,
            'email'              => $organisation->email,
            'adresse'            => $organisation->adresse,
            'paroisse'           => $organisation->paroisse ? [
                'id'           => $organisation->paroisse->uuid,
                'uuid'         => $organisation->paroisse->uuid,
                'nom_paroisse' => $organisation->paroisse->nom_paroisse,
                'code_paroisse'=> $organisation->paroisse->code_paroisse,
            ] : null,
            'produit'            => $organisation->produit ? [
                'id'   => $organisation->produit->uuid,
                'uuid' => $organisation->produit->uuid,
                'code' => $organisation->produit->code,
                'nom'  => $organisation->produit->nom,
            ] : null,
            'date_activation'    => $organisation->date_activation?->toDateString(),
            'date_desactivation' => $organisation->date_desactivation?->toDateString(),
            'created_at'         => $organisation->created_at?->toIso8601String(),
        ];

        // 2. Responsable
        $data['responsable'] = [
            'nom'       => $organisation->responsable_nom,
            'telephone' => $organisation->responsable_telephone,
            'email'     => $organisation->responsable_email,
        ];

        // 3. Utilisateurs
        $data['utilisateurs'] = OrganisationUserResource::collection($organisation->users);

        // 4. Statistiques
        $data['statistiques'] = [
            'total_membres'      => $organisation->membres->count(),
            'total_activites'    => $organisation->activites->count(),
            'total_pelerinages'  => $organisation->campagnesPelerinage->count(),
            'total_utilisateurs' => $organisation->users->count(),
            'total_operations'   => $organisation->operations->count(),
            'solde_caisse'       => (float) ($organisation->operations->where('statut', 'validee')->sum('montant') ?? 0),
        ];

        // 5. Membres
        $data['membres'] = $organisation->membres;

        // 6. Activités
        $data['activites'] = $organisation->activites;

        // 7. Pèlerinages
        $data['pelerinages'] = $organisation->campagnesPelerinage;

        // 8. Caisse
        $data['caisse'] = [
            'operations'   => $organisation->operations,
            'solde_actuel' => (float) ($organisation->operations->where('statut', 'validee')->sum('montant') ?? 0),
        ];

        // 9. Abonnement
        $dernierAbonnement = $organisation->abonnementActif ?? $organisation->abonnements->sortByDesc('id')->first();
        $data['abonnement'] = $dernierAbonnement ? new AbonnementResource($dernierAbonnement) : null;

        return response()->json([
            'status'  => 'success',
            'message' => 'Détails de l\'organisation récupérés avec succès.',
            'data'    => $data,
        ]);
    }

    /**
     * Formules tarifaires éligibles pour cette organisation selon son produit SaaS.
     */
    public function formules(string $id): JsonResponse
    {
        $organisation = is_numeric($id)
            ? Organisation::with('produit')->find((int) $id)
            : Organisation::with('produit')->where('uuid', $id)->first();

        if (!$organisation) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Organisation introuvable.',
            ], 404);
        }

        $formules = Formule::with('produit')
            ->where('produit_id', $organisation->produit_id)
            ->where('statut', 'actif')
            ->orderBy('ordre', 'asc')
            ->get();

        return response()->json([
            'status'  => 'success',
            'message' => "Formules tarifaires pour le produit [{$organisation->produit?->code}] de l'organisation.",
            'data'    => FormuleResource::collection($formules),
            'meta'    => [
                'organisation_uuid' => $organisation->uuid,
                'organisation_nom'  => $organisation->nom,
                'produit_code'      => $organisation->produit?->code,
                'total'             => $formules->count(),
            ],
        ]);
    }

    /**
     * Mise à jour d'une organisation (champs et logo).
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $organisation = is_numeric($id)
            ? Organisation::find((int) $id)
            : Organisation::where('uuid', $id)->first();

        if (!$organisation) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Organisation introuvable.',
            ], 404);
        }

        $validated = $request->validate([
            'nom'                   => ['sometimes', 'required', 'string', 'max:255'],
            'description'           => ['nullable', 'string'],
            'telephone'             => ['nullable', 'string', 'max:30'],
            'email'                 => ['nullable', 'email', 'max:150'],
            'adresse'               => ['nullable', 'string'],
            'responsable_nom'       => ['nullable', 'string', 'max:255'],
            'responsable_telephone' => ['nullable', 'string', 'max:30'],
            'responsable_email'     => ['nullable', 'email', 'max:150'],
            'logo'                  => ['nullable'],
            'supprimer_logo'        => ['nullable', 'boolean'],
            // F25.8: Changement de rattachement paroisse
            'paroisse_id'           => ['nullable', 'string'],
        ]);

        $anciennesValeurs = $organisation->toArray();

        // F25.8: Résoudre paroisse_id en paroisse_configuration_id
        if (array_key_exists('paroisse_id', $validated) && $validated['paroisse_id'] !== null) {
            $paroisseVal = $validated['paroisse_id'];
            $paroisseLinked = is_numeric($paroisseVal)
                ? \App\Models\CatecheseConfiguration::find((int) $paroisseVal)
                : \App\Models\CatecheseConfiguration::where('uuid', $paroisseVal)->first();
            if ($paroisseLinked) {
                $validated['paroisse_configuration_id'] = $paroisseLinked->id;
                $validated['mode'] = 'liee';
            }
        } elseif (array_key_exists('paroisse_id', $validated) && $validated['paroisse_id'] === null) {
            $validated['paroisse_configuration_id'] = null;
            $validated['mode'] = 'independant';
        }
        unset($validated['paroisse_id']);

        // Gestion du logo de l'organisation
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            if ($file->isValid()) {
                // Supprimer l'ancien logo si existant
                if ($organisation->logo_path) {
                    $old = str_contains($organisation->logo_path, '/')
                        ? $organisation->logo_path
                        : 'organisations/logos/' . $organisation->logo_path;
                    if (Storage::disk('public')->exists($old)) {
                        Storage::disk('public')->delete($old);
                    }
                }

                $filename = 'org_' . $organisation->uuid . '_' . time() . '.' . $file->getClientOriginalExtension();
                $file->storeAs('organisations/logos', $filename, 'public');
                $validated['logo_path'] = $filename;
            }
        } elseif ($request->boolean('supprimer_logo') || $request->input('logo') === 'DELETE') {
            if ($organisation->logo_path) {
                $old = str_contains($organisation->logo_path, '/')
                    ? $organisation->logo_path
                    : 'organisations/logos/' . $organisation->logo_path;
                if (Storage::disk('public')->exists($old)) {
                    Storage::disk('public')->delete($old);
                }
            }
            $validated['logo_path'] = null;
        }

        unset($validated['logo'], $validated['supprimer_logo']);

        $organisation->update($validated);
        $organisation->load(['produit', 'paroisse']);

        // Log audit
        ActionAuditService::log(
            action: 'update',
            module: 'Organisation',
            description: "Mise à jour de l'organisation {$organisation->nom}",
            entite: $organisation,
            anciennesValeurs: $anciennesValeurs,
            nouvellesValeurs: $organisation->toArray(),
            paroisseId: $organisation->paroisse_configuration_id,
            organisationId: $organisation->id,
            request: $request
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Organisation mise à jour avec succès.',
            'data'    => $organisation,
        ]);
    }

    /**
     * Changement de statut d'une organisation (actif, suspendu, inactif).
     */
    public function changerStatut(Request $request, string $id): JsonResponse
    {
        $organisation = is_numeric($id)
            ? Organisation::find((int) $id)
            : Organisation::where('uuid', $id)->first();

        if (!$organisation) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Organisation introuvable.',
            ], 404);
        }

        $validated = $request->validate([
            'statut' => ['required', 'string', 'in:actif,suspendu,inactif'],
            'motif'  => ['nullable', 'string', 'max:500'],
        ]);

        $ancienStatut = $organisation->statut;
        $nouveauStatut = $validated['statut'];

        $updateData = ['statut' => $nouveauStatut];
        if ($nouveauStatut === 'actif' && empty($organisation->date_activation)) {
            $updateData['date_activation'] = now()->toDateString();
        } elseif ($nouveauStatut === 'inactif' || $nouveauStatut === 'suspendu') {
            $updateData['date_desactivation'] = now()->toDateString();
        }

        $organisation->update($updateData);

        // Log audit
        ActionAuditService::log(
            action: 'status_change',
            module: 'Organisation',
            description: "Changement de statut de l'organisation {$organisation->nom} : {$ancienStatut} -> {$nouveauStatut}" . (!empty($validated['motif']) ? " ({$validated['motif']})" : ""),
            entite: $organisation,
            anciennesValeurs: ['statut' => $ancienStatut],
            nouvellesValeurs: ['statut' => $nouveauStatut],
            paroisseId: $organisation->paroisse_configuration_id,
            organisationId: $organisation->id,
            request: $request
        );

        return response()->json([
            'status'  => 'success',
            'message' => "Statut de l'organisation passé à [{$nouveauStatut}].",
            'data'    => $organisation->fresh(['produit', 'paroisse']),
        ]);
    }

    /**
     * Suppression logique (Soft Delete) d'une organisation.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $organisation = is_numeric($id)
            ? Organisation::find((int) $id)
            : Organisation::where('uuid', $id)->first();

        if (!$organisation) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Organisation introuvable.',
            ], 404);
        }

        $snapshot = $organisation->toArray();
        $organisation->delete();

        // Log audit
        ActionAuditService::log(
            action: 'delete',
            module: 'Organisation',
            description: "Suppression logique (Soft Delete) de l'organisation {$organisation->nom}",
            entite: $organisation,
            anciennesValeurs: $snapshot,
            nouvellesValeurs: ['deleted_at' => now()->toIso8601String()],
            paroisseId: $organisation->paroisse_configuration_id,
            organisationId: $organisation->id,
            request: $request
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Organisation supprimée (déplacée vers la corbeille).',
        ]);
    }

    /**
     * Provisionner le premier responsable d'une organisation après activation.
     */
    public function createResponsable(StoreResponsableOrganisationRequest $request, string $id): JsonResponse
    {
        $organisation = is_numeric($id)
            ? Organisation::find((int) $id)
            : Organisation::where('uuid', $id)->first();

        if (!$organisation) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Organisation introuvable.',
            ], 404);
        }

        $data = $request->validated();

        // Profil par défaut pour le responsable selon le type d'organisation
        $profilId = $data['profil_id'] ?? null;
        if (!$profilId) {
            $codeProfil = "RESPONSABLE_{$organisation->type_organisation}";
            $profil = Profil::where('code', $codeProfil)->first();
            if ($profil) {
                $profilId = $profil->id;
            }
        }

        $user = User::create([
            'name'                      => $data['name'],
            'email'                     => $data['email'],
            'telephone'                 => $data['telephone'] ?? null,
            'password'                  => Hash::make($data['password'] ?? 'Responsable123!'),
            'paroisse_configuration_id' => $organisation->paroisse_configuration_id,
            'organisation_id'           => $organisation->id,
            'profil_id'                 => $profilId,
            'user_type'                 => 'admin',
            'statut'                    => 'actif',
        ]);

        // Mettre à jour les informations du responsable sur la fiche organisation
        $organisation->update([
            'responsable_nom'       => $user->name,
            'responsable_email'     => $user->email,
            'responsable_telephone' => $user->telephone,
        ]);

        // Log audit
        ActionAuditService::log(
            action: 'responsable_created',
            module: 'Organisation',
            description: "Création du premier responsable {$user->name} ({$user->email}) pour l'organisation {$organisation->nom}",
            entite: $user,
            anciennesValeurs: null,
            nouvellesValeurs: $user->toArray(),
            paroisseId: $organisation->paroisse_configuration_id,
            organisationId: $organisation->id,
            request: $request
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Premier responsable de l\'organisation créé avec succès.',
            'data'    => new OrganisationUserResource($user->load('profil')),
        ], 201);
    }
}
