<?php

namespace App\Services\SuperAdmin;

use App\Models\ActionAuditLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActionAuditService
{
    /**
     * Enregistrer une action dans le journal d'audit Super Admin.
     */
    public static function log(
        string $action,
        string $module,
        ?string $description = null,
        mixed $entite = null,
        ?array $anciennesValeurs = null,
        ?array $nouvellesValeurs = null,
        ?int $paroisseId = null,
        ?int $organisationId = null,
        ?Request $request = null
    ): ActionAuditLog {
        $req = $request ?? request();
        /** @var User|null $user */
        $user = Auth::user() ?? $req?->user();

        $entiteId = null;
        $entiteUuid = null;

        if (is_object($entite)) {
            $entiteId = $entite->id ?? null;
            $entiteUuid = $entite->uuid ?? null;
            if (!$paroisseId && isset($entite->paroisse_configuration_id)) {
                $paroisseId = $entite->paroisse_configuration_id;
            }
            if (!$organisationId && isset($entite->organisation_id)) {
                $organisationId = $entite->organisation_id;
            }
        } elseif (is_array($entite)) {
            $entiteId = $entite['id'] ?? null;
            $entiteUuid = $entite['uuid'] ?? null;
        }

        $profilNom = null;
        if ($user) {
            $profilNom = $user->profil?->nom ?? $user->user_type;
            if (!$paroisseId && $user->paroisse_configuration_id) {
                $paroisseId = $user->paroisse_configuration_id;
            }
            if (!$organisationId && $user->organisation_id) {
                $organisationId = $user->organisation_id;
            }
        }

        return ActionAuditLog::create([
            'user_id'                   => $user?->id,
            'user_uuid'                 => $user?->uuid,
            'user_name'                 => $user?->name,
            'user_email'                => $user?->email,
            'profil'                    => $profilNom,
            'paroisse_configuration_id' => $paroisseId,
            'organisation_id'           => $organisationId,
            'action'                    => $action,
            'module'                    => $module,
            'entite_id'                 => $entiteId,
            'entite_uuid'               => $entiteUuid,
            'description'               => $description,
            'anciennes_valeurs'         => $anciennesValeurs,
            'nouvelles_valeurs'         => $nouvellesValeurs,
            'ip_address'                => $req?->ip(),
            'user_agent'                => $req?->userAgent(),
        ]);
    }
}
