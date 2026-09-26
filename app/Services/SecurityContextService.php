<?php

namespace App\Services;

use App\Models\CatecheseConfiguration;
use App\Models\Organisation;
use App\Models\Produit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class SecurityContextService
{
    /**
     * Résout le contexte de sécurité réel et certifié de l'utilisateur.
     *
     * @param User|null $user Utilisateur explicite ou utilisateur authentifié
     * @param Request|null $request Requête HTTP courante (pour contexte Super Admin éventuel)
     * @return array
     */
    public function getContext(?User $user = null, ?Request $request = null): array
    {
        $currentUser = $user ?? Auth::user();

        if (!$currentUser) {
            return [
                'authenticated'      => false,
                'user'               => null,
                'user_type'          => 'guest',
                'is_super_admin'     => false,
                'paroisse_id'        => null,
                'paroisse'           => null,
                'organisation_id'    => null,
                'organisation'       => null,
                'type_organisation'  => null,
                'espace'             => null,
                'permissions'        => [],
            ];
        }

        // 1. Vérification de cohérence stricte User <-> Organisation <-> Paroisse
        $this->verifyUserOrganisationIntegrity($currentUser);

        $isSuperAdmin = $this->isSuperAdmin($currentUser);

        // 2. Détermination de la paroisse effective
        $paroisseId = $this->resolveEffectiveParoisseId($currentUser, $request);
        $paroisse = $paroisseId ? CatecheseConfiguration::find($paroisseId) : null;

        // 3. Détermination de l'organisation effective
        $organisationId = $this->resolveEffectiveOrganisationId($currentUser, $request);
        $organisation = $organisationId ? Organisation::with('produit')->find($organisationId) : null;

        // 4. Détermination du type d'espace / produit
        $espace = $this->resolveEspace($currentUser, $organisation);

        return [
            'authenticated'      => true,
            'user'               => $currentUser,
            'user_type'          => $this->resolveUserType($currentUser, $isSuperAdmin),
            'is_super_admin'     => $isSuperAdmin,
            'paroisse_id'        => $paroisseId,
            'paroisse'           => $paroisse,
            'organisation_id'    => $organisationId,
            'organisation'       => $organisation,
            'type_organisation'  => $organisation?->type_organisation,
            'espace'             => $espace,
            'permissions'        => (array) ($currentUser->profil?->permissions ?? []),
        ];
    }

    /**
     * Vérifie rigoureusement l'intégrité entre l'utilisateur et son organisation.
     * Un utilisateur de la paroisse A ne peut JAMAIS appartenir à une organisation de la paroisse B.
     *
     * @throws AccessDeniedHttpException Si incohérence détectée
     */
    public function verifyUserOrganisationIntegrity(User $user): bool
    {
        if (empty($user->organisation_id)) {
            return true;
        }

        $organisation = Organisation::find($user->organisation_id);

        if (!$organisation) {
            throw new AccessDeniedHttpException("L'organisation rattachée à cet utilisateur est introuvable ou inactive.");
        }

        // Si l'organisation est indépendante (sans paroisse)
        if ($organisation->isIndependant() || empty($organisation->paroisse_configuration_id)) {
            return true;
        }

        if ((int) $organisation->paroisse_configuration_id !== (int) $user->paroisse_configuration_id) {
            throw new AccessDeniedHttpException(
                "Violation de sécurité multi-tenant : L'organisation [{$organisation->id}] appartient à la paroisse [{$organisation->paroisse_configuration_id}] " .
                "alors que l'utilisateur [{$user->id}] appartient à la paroisse [{$user->paroisse_configuration_id}]."
            );
        }

        return true;
    }

    /**
     * Résout l'identifiant de paroisse effectif en appliquant les règles de sécurité.
     * RÈGLE ABSOLUE : Les utilisateurs normaux sont STRICTEMENT cantonnés à leur paroisse_configuration_id.
     * Seul le Super Admin peut cibler une paroisse explicite.
     */
    public function resolveEffectiveParoisseId(User $user, ?Request $request = null): ?int
    {
        if ($this->isSuperAdmin($user)) {
            // Le Super Admin peut cibler une paroisse transmise dans la requête, ou rester au niveau plateforme (null)
            if ($request) {
                $requestedId = $request->input('paroisse_configuration_id')
                    ?? $request->input('paroisse_id')
                    ?? $request->header('X-Paroisse-Id')
                    ?? $request->header('X-Paroisse-Configuration-Id');

                if (!empty($requestedId) && is_numeric($requestedId)) {
                    return (int) $requestedId;
                }
            }

            return $user->paroisse_configuration_id ? (int) $user->paroisse_configuration_id : null;
        }

        // Pour tous les utilisateurs standards (non Super Admin), leur paroisse_configuration_id est IMPÉRATIF
        return $user->paroisse_configuration_id ? (int) $user->paroisse_configuration_id : null;
    }

    /**
     * Résout l'identifiant d'organisation effectif.
     */
    public function resolveEffectiveOrganisationId(User $user, ?Request $request = null): ?int
    {
        // Si l'utilisateur est lié à une organisation spécifique, c'est son unique contexte
        if (!empty($user->organisation_id)) {
            return (int) $user->organisation_id;
        }

        // Si l'utilisateur est Super Admin ou Admin de paroisse, il peut cibler une organisation de sa paroisse
        if ($request && ($this->isSuperAdmin($user) || $user->isParoisseAdmin())) {
            $requestedOrgId = $request->input('organisation_id')
                ?? $request->header('X-Organisation-Id');

            if (!empty($requestedOrgId) && is_numeric($requestedOrgId)) {
                $org = Organisation::find((int) $requestedOrgId);
                if ($org) {
                    // Vérifier que l'organisation ciblée appartient bien à la paroisse de l'utilisateur (ou Super Admin)
                    if ($this->isSuperAdmin($user) || (int) $org->paroisse_configuration_id === (int) $user->paroisse_configuration_id) {
                        return (int) $org->id;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Vérifie si l'utilisateur est un Super Administrateur de la plateforme.
     */
    public function isSuperAdmin(User $user): bool
    {
        if ($user->user_type === 'super_admin') {
            return true;
        }

        $code = strtoupper((string) ($user->profil?->code ?? ''));
        if ($code === 'SUPER_ADMIN') {
            return true;
        }

        $permissions = (array) ($user->profil?->permissions ?? []);
        return in_array('*', $permissions, true) && empty($user->paroisse_configuration_id);
    }

    /**
     * Détermine la typologie fonctionnelle de l'utilisateur.
     */
    protected function resolveUserType(User $user, bool $isSuperAdmin): string
    {
        if ($isSuperAdmin) {
            return 'super_admin';
        }

        if (!empty($user->organisation_id)) {
            return 'organisation_user';
        }

        if (!empty($user->paroisse_configuration_id)) {
            return 'paroisse_admin';
        }

        return $user->user_type ?? 'user';
    }

    /**
     * Détermine le nom de l'espace / produit concerné.
     */
    protected function resolveEspace(User $user, ?Organisation $organisation): string
    {
        if ($this->isSuperAdmin($user) && empty($organisation)) {
            return 'SUPER_ADMIN';
        }

        if ($organisation) {
            return $organisation->type_organisation; // OPPE, OPPJ, OPPA
        }

        return Produit::CODE_CATHEO;
    }

    /**
     * Valide l'accès d'un utilisateur à une organisation donnée.
     *
     * @throws AccessDeniedHttpException
     */
    public function assertCanAccessOrganisation(User $user, Organisation $organisation): void
    {
        if ($this->isSuperAdmin($user)) {
            return;
        }

        // Un utilisateur d'une organisation ne peut accéder qu'à son organisation
        if (!empty($user->organisation_id) && (int) $user->organisation_id !== (int) $organisation->id) {
            throw new AccessDeniedHttpException("Accès refusé à cette organisation.");
        }

        // Organisation indépendante (sans paroisse parente)
        if ($organisation->isIndependant() || empty($organisation->paroisse_configuration_id)) {
            if ((int) $user->organisation_id !== (int) $organisation->id) {
                throw new AccessDeniedHttpException("Accès refusé à cette organisation indépendante.");
            }
            return;
        }

        // Un administrateur de paroisse ne peut accéder qu'aux organisations de sa paroisse
        if ((int) $user->paroisse_configuration_id !== (int) $organisation->paroisse_configuration_id) {
            throw new AccessDeniedHttpException("Accès refusé : cette organisation n'appartient pas à votre paroisse.");
        }
    }
}
