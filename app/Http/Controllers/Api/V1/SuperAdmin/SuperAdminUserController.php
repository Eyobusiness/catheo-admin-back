<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\CatecheseConfiguration;
use App\Models\Organisation;
use App\Models\Profil;
use App\Models\User;
use App\Services\SuperAdmin\ActionAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SuperAdminUserController extends Controller
{
    /**
     * Liste des utilisateurs de la plateforme avec filtres complets.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['profil', 'paroisse', 'organisation'])
            ->latest('id');

        if ($request->filled('statut') && $request->statut !== 'tous') {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('user_type') && $request->user_type !== 'tous') {
            $query->where('user_type', $request->user_type);
        }

        if ($request->filled('profil')) {
            $profVal = $request->profil;
            $query->whereHas('profil', function ($q) use ($profVal) {
                if (is_numeric($profVal)) {
                    $q->where('id', (int) $profVal);
                } else {
                    $q->where('code', $profVal)->orWhere('uuid', $profVal)->orWhere('nom', 'like', "%{$profVal}%");
                }
            });
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

        if ($request->filled('organisation_id')) {
            $oVal = $request->organisation_id;
            $oId = is_numeric($oVal)
                ? (int) $oVal
                : Organisation::where('uuid', $oVal)->value('id');
            if ($oId) {
                $query->where('organisation_id', $oId);
            }
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('telephone', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 15);
        $result = $query->paginate($perPage);

        // Transformation standardisée avec UUIDs garantis
        $items = collect($result->items())->map(function ($u) {
            return [
                'id'              => $u->uuid,
                'uuid'            => $u->uuid,
                'id_interne'      => $u->id,
                'nom'             => $u->name,
                'name'            => $u->name,
                'email'           => $u->email,
                'telephone'       => $u->telephone,
                'user_type'       => $u->user_type,
                'statut'          => $u->statut,
                'profil'          => $u->profil ? [
                    'id'   => $u->profil->uuid,
                    'code' => $u->profil->code,
                    'nom'  => $u->profil->nom,
                ] : null,
                'paroisse'        => $u->paroisse ? [
                    'id'            => $u->paroisse->uuid,
                    'nom_paroisse'  => $u->paroisse->nom_paroisse,
                    'code_paroisse' => $u->paroisse->code_paroisse,
                ] : null,
                'organisation'    => $u->organisation ? [
                    'id'                => $u->organisation->uuid,
                    'nom'               => $u->organisation->nom,
                    'type_organisation' => $u->organisation->type_organisation,
                ] : null,
                'dernier_login_at'=> $u->dernier_login_at?->toIso8601String(),
                'created_at'      => $u->created_at?->toIso8601String(),
            ];
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Liste des utilisateurs récupérée avec succès.',
            'data'    => $items,
            'meta'    => [
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
                'per_page'     => $result->perPage(),
                'total'        => $result->total(),
            ],
        ]);
    }

    /**
     * Création d'un utilisateur depuis le Super Admin.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'email'           => ['required', 'email', 'max:255', 'unique:users,email'],
            'telephone'       => ['nullable', 'string', 'max:30'],
            'password'        => ['nullable', 'string', 'min:8'],
            'user_type'       => ['nullable', 'string', 'in:super_admin,admin,animateur,parent,utilisateur'],
            'profil_id'       => ['nullable'],
            'paroisse_id'     => ['nullable'],
            'organisation_id' => ['nullable'],
            'statut'          => ['nullable', 'string', 'in:actif,suspendu,inactif'],
        ]);

        // Résolution de la paroisse
        $paroisseId = null;
        if (!empty($validated['paroisse_id'])) {
            $paroisseId = is_numeric($validated['paroisse_id'])
                ? (int) $validated['paroisse_id']
                : CatecheseConfiguration::where('uuid', $validated['paroisse_id'])->value('id');
        }

        // Résolution de l'organisation
        $organisationId = null;
        if (!empty($validated['organisation_id'])) {
            $org = is_numeric($validated['organisation_id'])
                ? Organisation::find((int) $validated['organisation_id'])
                : Organisation::where('uuid', $validated['organisation_id'])->first();

            if ($org) {
                $organisationId = $org->id;
                // Si paroisse non précisée, reprendre celle de l'organisation
                if (!$paroisseId) {
                    $paroisseId = $org->paroisse_configuration_id;
                }
            }
        }

        // Résolution du profil
        $profilId = null;
        if (!empty($validated['profil_id'])) {
            $profVal = $validated['profil_id'];
            $profilId = is_numeric($profVal)
                ? (int) $profVal
                : Profil::where('uuid', $profVal)->orWhere('code', $profVal)->value('id');
        }

        $passwordRaw = $validated['password'] ?? 'CatheoPass123!';
        $userType = $validated['user_type'] ?? (
            $organisationId ? 'utilisateur' : ($paroisseId ? 'admin' : 'super_admin')
        );

        $user = User::create([
            'name'                      => $validated['name'],
            'email'                     => $validated['email'],
            'telephone'                 => $validated['telephone'] ?? null,
            'password'                  => Hash::make($passwordRaw),
            'user_type'                 => $userType,
            'profil_id'                 => $profilId,
            'paroisse_configuration_id' => $paroisseId,
            'organisation_id'           => $organisationId,
            'statut'                    => $validated['statut'] ?? 'actif',
        ]);

        $user->load(['profil', 'paroisse', 'organisation']);

        // Log audit
        ActionAuditService::log(
            action: 'create',
            module: 'Utilisateur',
            description: "Création de l'utilisateur {$user->name} ({$user->email}) [Type: {$user->user_type}]",
            entite: $user,
            anciennesValeurs: null,
            nouvellesValeurs: $user->toArray(),
            paroisseId: $paroisseId,
            organisationId: $organisationId,
            request: $request
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Utilisateur créé avec succès.',
            'data'    => [
                'id'              => $user->uuid,
                'uuid'            => $user->uuid,
                'id_interne'      => $user->id,
                'name'            => $user->name,
                'email'           => $user->email,
                'telephone'       => $user->telephone,
                'user_type'       => $user->user_type,
                'statut'          => $user->statut,
                'profil'          => $user->profil,
                'paroisse'        => $user->paroisse,
                'organisation'    => $user->organisation,
                'created_at'      => $user->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Fiche détaillée d'un utilisateur.
     */
    public function show(string $id): JsonResponse
    {
        $user = is_numeric($id)
            ? User::with(['profil', 'paroisse', 'organisation'])->find((int) $id)
            : User::with(['profil', 'paroisse', 'organisation'])->where('uuid', $id)->first();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Utilisateur introuvable.',
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Détails de l\'utilisateur récupérés avec succès.',
            'data'    => [
                'id'              => $user->uuid,
                'uuid'            => $user->uuid,
                'id_interne'      => $user->id,
                'name'            => $user->name,
                'email'           => $user->email,
                'telephone'       => $user->telephone,
                'user_type'       => $user->user_type,
                'statut'          => $user->statut,
                'profil'          => $user->profil,
                'paroisse'        => $user->paroisse,
                'organisation'    => $user->organisation,
                'dernier_login_at'=> $user->dernier_login_at?->toIso8601String(),
                'created_at'      => $user->created_at?->toIso8601String(),
                'updated_at'      => $user->updated_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Modification d'un utilisateur.
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = is_numeric($id)
            ? User::find((int) $id)
            : User::where('uuid', $id)->first();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Utilisateur introuvable.',
            ], 404);
        }

        $validated = $request->validate([
            'name'            => ['sometimes', 'required', 'string', 'max:255'],
            'email'           => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'telephone'       => ['nullable', 'string', 'max:30'],
            'user_type'       => ['nullable', 'string', 'in:super_admin,admin,animateur,parent,utilisateur'],
            'profil_id'       => ['nullable'],
            'paroisse_id'     => ['nullable'],
            'organisation_id' => ['nullable'],
            'statut'          => ['nullable', 'string', 'in:actif,suspendu,inactif'],
        ]);

        $anciennesValeurs = $user->toArray();

        if (array_key_exists('paroisse_id', $validated)) {
            $pVal = $validated['paroisse_id'];
            $validated['paroisse_configuration_id'] = empty($pVal)
                ? null
                : (is_numeric($pVal) ? (int) $pVal : CatecheseConfiguration::where('uuid', $pVal)->value('id'));
            unset($validated['paroisse_id']);
        }

        if (array_key_exists('organisation_id', $validated)) {
            $oVal = $validated['organisation_id'];
            $validated['organisation_id'] = empty($oVal)
                ? null
                : (is_numeric($oVal) ? (int) $oVal : Organisation::where('uuid', $oVal)->value('id'));
        }

        if (array_key_exists('profil_id', $validated)) {
            $prVal = $validated['profil_id'];
            $validated['profil_id'] = empty($prVal)
                ? null
                : (is_numeric($prVal) ? (int) $prVal : Profil::where('uuid', $prVal)->orWhere('code', $prVal)->value('id'));
        }

        $user->update($validated);
        $user->load(['profil', 'paroisse', 'organisation']);

        // Log audit
        ActionAuditService::log(
            action: 'update',
            module: 'Utilisateur',
            description: "Mise à jour de l'utilisateur {$user->name} ({$user->email})",
            entite: $user,
            anciennesValeurs: $anciennesValeurs,
            nouvellesValeurs: $user->toArray(),
            paroisseId: $user->paroisse_configuration_id,
            organisationId: $user->organisation_id,
            request: $request
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Utilisateur mis à jour avec succès.',
            'data'    => [
                'id'           => $user->uuid,
                'uuid'         => $user->uuid,
                'name'         => $user->name,
                'email'        => $user->email,
                'telephone'    => $user->telephone,
                'user_type'    => $user->user_type,
                'statut'       => $user->statut,
                'profil'       => $user->profil,
                'paroisse'     => $user->paroisse,
                'organisation' => $user->organisation,
            ],
        ]);
    }

    /**
     * Changement de statut d'un utilisateur (actif, suspendu, inactif).
     */
    public function changerStatut(Request $request, string $id): JsonResponse
    {
        $user = is_numeric($id)
            ? User::find((int) $id)
            : User::where('uuid', $id)->first();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Utilisateur introuvable.',
            ], 404);
        }

        $validated = $request->validate([
            'statut' => ['required', 'string', 'in:actif,suspendu,inactif'],
            'motif'  => ['nullable', 'string', 'max:500'],
        ]);

        $ancienStatut = $user->statut;
        $nouveauStatut = $validated['statut'];

        $user->update(['statut' => $nouveauStatut]);

        // Log audit
        ActionAuditService::log(
            action: 'status_change',
            module: 'Utilisateur',
            description: "Changement de statut utilisateur {$user->name} : {$ancienStatut} -> {$nouveauStatut}",
            entite: $user,
            anciennesValeurs: ['statut' => $ancienStatut],
            nouvellesValeurs: ['statut' => $nouveauStatut],
            paroisseId: $user->paroisse_configuration_id,
            organisationId: $user->organisation_id,
            request: $request
        );

        return response()->json([
            'status'  => 'success',
            'message' => "Statut de l'utilisateur passé à [{$nouveauStatut}].",
            'data'    => [
                'id'     => $user->uuid,
                'uuid'   => $user->uuid,
                'name'   => $user->name,
                'statut' => $user->statut,
            ],
        ]);
    }

    /**
     * Réinitialisation du mot de passe d'un utilisateur.
     */
    public function resetPassword(Request $request, string $id): JsonResponse
    {
        $user = is_numeric($id)
            ? User::find((int) $id)
            : User::where('uuid', $id)->first();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Utilisateur introuvable.',
            ], 404);
        }

        $validated = $request->validate([
            'password' => ['nullable', 'string', 'min:8'],
        ]);

        $newPassword = $validated['password'] ?? Str::password(12, true, true, false, false);
        $user->update([
            'password' => Hash::make($newPassword),
        ]);

        // Log audit
        ActionAuditService::log(
            action: 'password_reset',
            module: 'Utilisateur',
            description: "Réinitialisation du mot de passe pour l'utilisateur {$user->name} ({$user->email})",
            entite: $user,
            anciennesValeurs: null,
            nouvellesValeurs: ['password_reset' => true],
            paroisseId: $user->paroisse_configuration_id,
            organisationId: $user->organisation_id,
            request: $request
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Mot de passe réinitialisé avec succès.',
            'data'    => [
                'id'                  => $user->uuid,
                'uuid'                => $user->uuid,
                'email'               => $user->email,
                'temporary_password'  => empty($validated['password']) ? $newPassword : null,
            ],
        ]);
    }

    /**
     * Suppression logique (Soft Delete) d'un utilisateur.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $user = is_numeric($id)
            ? User::find((int) $id)
            : User::where('uuid', $id)->first();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Utilisateur introuvable.',
            ], 404);
        }

        // Empêcher l'auto-suppression
        if ($request->user() && (int) $request->user()->id === (int) $user->id) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Vous ne pouvez pas supprimer votre propre compte administrateur connecté.',
            ], 422);
        }

        $snapshot = $user->toArray();
        $user->delete();

        // Log audit
        ActionAuditService::log(
            action: 'delete',
            module: 'Utilisateur',
            description: "Suppression logique (Soft Delete) de l'utilisateur {$user->name} ({$user->email})",
            entite: $user,
            anciennesValeurs: $snapshot,
            nouvellesValeurs: ['deleted_at' => now()->toIso8601String()],
            paroisseId: $user->paroisse_configuration_id,
            organisationId: $user->organisation_id,
            request: $request
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Utilisateur supprimé (déplacé vers la corbeille).',
        ]);
    }
}
