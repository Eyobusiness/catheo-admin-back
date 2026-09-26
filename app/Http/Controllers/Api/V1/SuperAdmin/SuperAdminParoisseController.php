<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Profil;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SuperAdmin\StoreParoisseRequest;
use App\Http\Requests\Api\V1\SuperAdmin\UpdateParoisseRequest;
use App\Http\Resources\Api\V1\SuperAdmin\SuperAdminParoisseResource;
use App\Models\CatecheseConfiguration;
use App\Services\SuperAdmin\ActionAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SuperAdminParoisseController extends Controller
{
    /**
     * Liste des paroisses supervisées avec leurs abonnements SaaS.
     */
    public function index(Request $request): JsonResponse
    {
        $query = CatecheseConfiguration::with([
            'abonnements.formule.produit',
            'organisations.produit',
        ])->latest('id');

        if ($request->filled('diocese')) {
            $query->where('diocese', 'like', "%{$request->diocese}%");
        }

        if ($request->filled('ville')) {
            $query->where('ville', 'like', "%{$request->ville}%");
        }

        if ($request->filled('statut') && $request->statut !== 'tous') {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('nom_paroisse', 'like', "%{$search}%")
                  ->orWhere('code_paroisse', 'like', "%{$search}%")
                  ->orWhere('ville', 'like', "%{$search}%")
                  ->orWhere('diocese', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 15);
        $result = $query->paginate($perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Liste des paroisses récupérée avec succès.',
            'data'    => SuperAdminParoisseResource::collection($result),
            'meta'    => [
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
                'per_page'     => $result->perPage(),
                'total'        => $result->total(),
            ],
        ]);
    }

    /**
     * Créer une nouvelle paroisse directement depuis le Super Admin.
     * Phase B — F25.8
     */
    public function store(StoreParoisseRequest $request): JsonResponse
    {
        $validated = $request->validated();

        // Génération automatique des préfixes à partir du code paroisse
        $code = strtoupper($validated['code_paroisse']);
        $validated['prefixe_matricule'] = $code;
        $validated['prefixe_recu']      = $code . '-R';
        $validated['statut']            = 'cree';
        $validated['created_by']        = $request->user()?->id;

        // Gestion logo paroisse
        if ($request->hasFile('logo_paroisse') && $request->file('logo_paroisse')->isValid()) {
            $filename = 'logo_paroisse_' . $code . '_' . time() . '.' . $request->file('logo_paroisse')->getClientOriginalExtension();
            $request->file('logo_paroisse')->storeAs('paroisses/logos', $filename, 'public');
            $validated['logo_paroisse'] = $filename;
        }

        // Gestion logo catéchèse
        if ($request->hasFile('logo_catechese') && $request->file('logo_catechese')->isValid()) {
            $filename = 'logo_catechese_' . $code . '_' . time() . '.' . $request->file('logo_catechese')->getClientOriginalExtension();
            $request->file('logo_catechese')->storeAs('paroisses/logos', $filename, 'public');
            $validated['logo_catechese'] = $filename;
        }

        $paroisse = CatecheseConfiguration::create($validated);

        // Audit
        ActionAuditService::log(
            action: 'create',
            module: 'Paroisse',
            description: "Création de la paroisse {$paroisse->nom_paroisse} (code: {$paroisse->code_paroisse})",
            entite: $paroisse,
            anciennesValeurs: null,
            nouvellesValeurs: $paroisse->toArray(),
            paroisseId: $paroisse->id,
            organisationId: null,
            request: $request
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Paroisse créée avec succès.',
            'data'    => [
                'uuid'         => $paroisse->uuid,
                'nom_paroisse' => $paroisse->nom_paroisse,
                'code_paroisse'=> $paroisse->code_paroisse,
                'statut'       => $paroisse->statut,
                'diocese'      => $paroisse->diocese,
            ],
        ], 201);
    }

    /**
     * Détails d'une paroisse avec l'historique complet de ses abonnements.
     */
    public function show(string $id): JsonResponse
    {
        $relations = [
            'organisations.produit',
            'abonnements.formule.produit',
            'abonnements.echeances.paiements',
        ];

        $paroisse = is_numeric($id)
            ? CatecheseConfiguration::with($relations)->find((int) $id)
            : CatecheseConfiguration::with($relations)->where('uuid', $id)->first();

        if (!$paroisse) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Paroisse introuvable.',
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Détails de la paroisse récupérés avec succès.',
            'data'    => new SuperAdminParoisseResource($paroisse),
        ]);
    }

    /**
     * Mettre à jour une paroisse.
     * Phase E — F25.8
     */
    public function update(UpdateParoisseRequest $request, string $id): JsonResponse
    {
        $paroisse = is_numeric($id)
            ? CatecheseConfiguration::find((int) $id)
            : CatecheseConfiguration::where('uuid', $id)->first();

        if (!$paroisse) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Paroisse introuvable.',
            ], 404);
        }

        $validated    = $request->validated();
        $anciennesValeurs = $paroisse->toArray();

        $validated['updated_by'] = $request->user()?->id;

        // Gestion logo paroisse
        if ($request->hasFile('logo_paroisse') && $request->file('logo_paroisse')->isValid()) {
            if ($paroisse->logo_paroisse) {
                $old = 'paroisses/logos/' . $paroisse->logo_paroisse;
                if (Storage::disk('public')->exists($old)) Storage::disk('public')->delete($old);
            }
            $filename = 'logo_paroisse_' . $paroisse->code_paroisse . '_' . time() . '.' . $request->file('logo_paroisse')->getClientOriginalExtension();
            $request->file('logo_paroisse')->storeAs('paroisses/logos', $filename, 'public');
            $validated['logo_paroisse'] = $filename;
        }

        // Gestion logo catéchèse
        if ($request->hasFile('logo_catechese') && $request->file('logo_catechese')->isValid()) {
            if ($paroisse->logo_catechese) {
                $old = 'paroisses/logos/' . $paroisse->logo_catechese;
                if (Storage::disk('public')->exists($old)) Storage::disk('public')->delete($old);
            }
            $filename = 'logo_catechese_' . $paroisse->code_paroisse . '_' . time() . '.' . $request->file('logo_catechese')->getClientOriginalExtension();
            $request->file('logo_catechese')->storeAs('paroisses/logos', $filename, 'public');
            $validated['logo_catechese'] = $filename;
        }

        $paroisse->update($validated);
        $paroisse->load(['organisations.produit', 'abonnements.formule.produit']);

        // Audit
        ActionAuditService::log(
            action: 'update',
            module: 'Paroisse',
            description: "Mise à jour de la paroisse {$paroisse->nom_paroisse}",
            entite: $paroisse,
            anciennesValeurs: $anciennesValeurs,
            nouvellesValeurs: $paroisse->toArray(),
            paroisseId: $paroisse->id,
            organisationId: null,
            request: $request
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Paroisse mise à jour avec succès.',
            'data'    => new SuperAdminParoisseResource($paroisse),
        ]);
    }

    /**
     * Suppression logique (Soft Delete) d'une paroisse.
     * Phase F25.8
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $paroisse = is_numeric($id)
            ? CatecheseConfiguration::find((int) $id)
            : CatecheseConfiguration::where('uuid', $id)->first();

        if (!$paroisse) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Paroisse introuvable.',
            ], 404);
        }

        // Vérifier les dépendances actives
        $orgActives = $paroisse->organisations()->whereNull('deleted_at')->count();
        if ($orgActives > 0) {
            return response()->json([
                'status'  => 'error',
                'message' => "Impossible de supprimer la paroisse : elle possède {$orgActives} organisation(s) active(s).",
                'data'    => ['organisations_actives' => $orgActives],
            ], 422);
        }

        $snapshot = $paroisse->toArray();
        $paroisse->update(['deleted_by' => $request->user()?->id]);
        $paroisse->delete();

        // Audit
        ActionAuditService::log(
            action: 'delete',
            module: 'Paroisse',
            description: "Suppression logique (Soft Delete) de la paroisse {$paroisse->nom_paroisse}",
            entite: $paroisse,
            anciennesValeurs: $snapshot,
            nouvellesValeurs: ['deleted_at' => now()->toIso8601String()],
            paroisseId: $paroisse->id,
            organisationId: null,
            request: $request
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Paroisse supprimée (déplacée vers la corbeille).',
        ]);
    }


    /**
     * Liste des profils système disponibles (is_system = 1).
     */
    public function systemProfils(): JsonResponse
    {
        $profils = Profil::where('is_system', true)
            ->where(function ($q) {
                $q->where('statut', 'actif')->orWhereNull('statut');
            })
            ->orderBy('nom', 'asc')
            ->get(['id', 'uuid', 'code', 'nom', 'description', 'is_system', 'statut']);

        return response()->json([
            'status' => 'success',
            'data'   => $profils,
        ]);
    }

    /**
     * Liste des utilisateurs rattachés directement à une paroisse (hors organisation).
     */
    public function users(Request $request, string $id): JsonResponse
    {
        $paroisse = is_numeric($id)
            ? CatecheseConfiguration::find((int) $id)
            : CatecheseConfiguration::where('uuid', $id)->first();

        if (!$paroisse) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Paroisse introuvable.',
            ], 404);
        }

        $users = User::with('profil')
            ->where('paroisse_configuration_id', $paroisse->id)
            ->whereNull('organisation_id')
            ->latest('id')
            ->get();

        $data = $users->map(function ($u) {
            return [
                'id'                        => $u->uuid,
                'uuid'                      => $u->uuid,
                'id_interne'                => $u->id,
                'paroisse_configuration_id' => $u->paroisse_configuration_id,
                'organisation_id'           => $u->organisation_id,
                'profil_id'                 => $u->profil_id,
                'profil'                    => $u->profil ? [
                    'id'        => $u->profil->id,
                    'uuid'      => $u->profil->uuid ?? null,
                    'code'      => $u->profil->code,
                    'nom'       => $u->profil->nom,
                    'is_system' => (bool) $u->profil->is_system,
                ] : null,
                'user_type'                 => $u->user_type ?? 'admin',
                'username'                  => $u->username,
                'name'                      => $u->name,
                'email'                     => $u->email,
                'telephone'                 => $u->telephone,
                'email_verified_at'         => $u->email_verified_at?->toIso8601String(),
                'statut'                    => $u->statut ?? 'actif',
                'dernier_login_at'          => $u->dernier_login_at?->toIso8601String(),
                'created_at'                => $u->created_at?->toIso8601String(),
                'updated_at'                => $u->updated_at?->toIso8601String(),
                'created_by'                => $u->created_by,
                'updated_by'                => $u->updated_by,
                'deleted_by'                => $u->deleted_by,
                'deleted_at'                => $u->deleted_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'status' => 'success',
            'data'   => $data,
        ]);
    }

    /**
     * Création d'un utilisateur / administrateur paroissial.
     */
    public function storeUser(Request $request, string $id): JsonResponse
    {
        $paroisse = is_numeric($id)
            ? CatecheseConfiguration::find((int) $id)
            : CatecheseConfiguration::where('uuid', $id)->first();

        if (!$paroisse) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Paroisse introuvable.',
            ], 404);
        }

        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'email'     => ['required', 'email', 'max:255', 'unique:users,email'],
            'username'  => ['required', 'string', 'max:100', 'unique:users,username', 'regex:/^[a-zA-Z0-9._-]+$/'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'password'  => ['required', 'string', 'min:6'],
            'profil_id' => ['required'],
            'user_type' => ['nullable', 'string', 'max:50'],
            'statut'    => ['nullable', 'string', 'in:actif,inactif,suspendu'],
        ], [
            'name.required'     => 'Le nom et prénoms sont obligatoires.',
            'email.required'    => 'L\'adresse email est obligatoire.',
            'email.unique'      => 'Cette adresse email est déjà utilisée.',
            'username.required' => 'L\'identifiant utilisateur (username) est obligatoire.',
            'username.unique'   => 'Cet identifiant est déjà utilisé.',
            'username.regex'    => 'L\'identifiant ne peut contenir que des lettres, chiffres, points, tirets et underscores.',
            'password.required' => 'Le mot de passe est obligatoire.',
            'password.min'      => 'Le mot de passe doit comporter au moins 6 caractères.',
            'profil_id.required'=> 'Le profil est obligatoire.',
        ]);

        $profVal = $validated['profil_id'];
        $profil = is_numeric($profVal)
            ? Profil::find((int) $profVal)
            : Profil::where('uuid', $profVal)->orWhere('code', $profVal)->first();

        if (!$profil) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Profil introuvable.',
            ], 422);
        }

        $user = User::create([
            'paroisse_configuration_id' => $paroisse->id,
            'organisation_id'           => null,
            'profil_id'                 => $profil->id,
            'user_type'                 => $validated['user_type'] ?? 'admin',
            'username'                  => trim($validated['username']),
            'name'                      => trim($validated['name']),
            'email'                     => strtolower(trim($validated['email'])),
            'telephone'                 => $validated['telephone'] ?? null,
            'password'                  => Hash::make($validated['password']),
            'statut'                    => $validated['statut'] ?? 'actif',
            'created_by'                => $request->user()?->id,
        ]);

        $user->load('profil');

        ActionAuditService::log(
            action: 'create',
            module: 'ParoisseUser',
            description: "Création de l'utilisateur {$user->name} ({$user->email}) pour la paroisse {$paroisse->nom_paroisse}",
            entite: $user,
            anciennesValeurs: null,
            nouvellesValeurs: $user->toArray(),
            paroisseId: $paroisse->id,
            organisationId: null,
            request: $request
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Utilisateur paroissial créé avec succès.',
            'data'    => [
                'id'                        => $user->uuid,
                'uuid'                      => $user->uuid,
                'id_interne'                => $user->id,
                'paroisse_configuration_id' => $user->paroisse_configuration_id,
                'organisation_id'           => null,
                'profil_id'                 => $user->profil_id,
                'profil'                    => [
                    'id'   => $profil->id,
                    'code' => $profil->code,
                    'nom'  => $profil->nom,
                ],
                'user_type'                 => $user->user_type,
                'username'                  => $user->username,
                'name'                      => $user->name,
                'email'                     => $user->email,
                'telephone'                 => $user->telephone,
                'statut'                    => $user->statut,
                'created_at'                => $user->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Modification d'un utilisateur paroissial.
     */
    public function updateUser(Request $request, string $paroisseId, string $userId): JsonResponse
    {
        $paroisse = is_numeric($paroisseId)
            ? CatecheseConfiguration::find((int) $paroisseId)
            : CatecheseConfiguration::where('uuid', $paroisseId)->first();

        if (!$paroisse) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Paroisse introuvable.',
            ], 404);
        }

        $user = is_numeric($userId)
            ? User::where('paroisse_configuration_id', $paroisse->id)->find((int) $userId)
            : User::where('paroisse_configuration_id', $paroisse->id)->where('uuid', $userId)->first();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Utilisateur introuvable pour cette paroisse.',
            ], 404);
        }

        $validated = $request->validate([
            'name'      => ['sometimes', 'required', 'string', 'max:255'],
            'email'     => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'username'  => ['sometimes', 'required', 'string', 'max:100', Rule::unique('users', 'username')->ignore($user->id), 'regex:/^[a-zA-Z0-9._-]+$/'],
            'telephone' => ['nullable', 'string', 'max:30'],
            'password'  => ['nullable', 'string', 'min:6'],
            'profil_id' => ['sometimes', 'required'],
            'user_type' => ['nullable', 'string', 'max:50'],
            'statut'    => ['nullable', 'string', 'in:actif,inactif,suspendu'],
        ]);

        $anciennesValeurs = $user->toArray();

        if (!empty($validated['profil_id'])) {
            $profVal = $validated['profil_id'];
            $profil = is_numeric($profVal)
                ? Profil::find((int) $profVal)
                : Profil::where('uuid', $profVal)->orWhere('code', $profVal)->first();
            if ($profil) {
                $validated['profil_id'] = $profil->id;
            }
        }

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        $validated['updated_by'] = $request->user()?->id;

        $user->update($validated);
        $user->load('profil');

        ActionAuditService::log(
            action: 'update',
            module: 'ParoisseUser',
            description: "Mise à jour de l'utilisateur {$user->name} ({$user->email}) pour la paroisse {$paroisse->nom_paroisse}",
            entite: $user,
            anciennesValeurs: $anciennesValeurs,
            nouvellesValeurs: $user->toArray(),
            paroisseId: $paroisse->id,
            organisationId: null,
            request: $request
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Utilisateur paroissial mis à jour avec succès.',
            'data'    => [
                'id'                        => $user->uuid,
                'uuid'                      => $user->uuid,
                'id_interne'                => $user->id,
                'paroisse_configuration_id' => $user->paroisse_configuration_id,
                'profil_id'                 => $user->profil_id,
                'profil'                    => [
                    'id'   => $user->profil?->id,
                    'code' => $user->profil?->code,
                    'nom'  => $user->profil?->nom,
                ],
                'user_type'                 => $user->user_type,
                'username'                  => $user->username,
                'name'                      => $user->name,
                'email'                     => $user->email,
                'telephone'                 => $user->telephone,
                'statut'                    => $user->statut,
                'updated_at'                => $user->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Suppression logique d'un utilisateur paroissial.
     */
    public function destroyUser(Request $request, string $paroisseId, string $userId): JsonResponse
    {
        $paroisse = is_numeric($paroisseId)
            ? CatecheseConfiguration::find((int) $paroisseId)
            : CatecheseConfiguration::where('uuid', $paroisseId)->first();

        if (!$paroisse) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Paroisse introuvable.',
            ], 404);
        }

        $user = is_numeric($userId)
            ? User::where('paroisse_configuration_id', $paroisse->id)->find((int) $userId)
            : User::where('paroisse_configuration_id', $paroisse->id)->where('uuid', $userId)->first();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Utilisateur introuvable pour cette paroisse.',
            ], 404);
        }

        if ($request->user() && (int) $request->user()->id === (int) $user->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Vous ne pouvez pas supprimer votre propre compte connecté.',
            ], 422);
        }

        $snapshot = $user->toArray();
        $user->update(['deleted_by' => $request->user()?->id]);
        $user->delete();

        ActionAuditService::log(
            action: 'delete',
            module: 'ParoisseUser',
            description: "Suppression de l'utilisateur {$user->name} de la paroisse {$paroisse->nom_paroisse}",
            entite: $user,
            anciennesValeurs: $snapshot,
            nouvellesValeurs: ['deleted_at' => now()->toIso8601String()],
            paroisseId: $paroisse->id,
            organisationId: null,
            request: $request
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Utilisateur paroissial supprimé (déplacé vers la corbeille).',
        ]);
    }
}
