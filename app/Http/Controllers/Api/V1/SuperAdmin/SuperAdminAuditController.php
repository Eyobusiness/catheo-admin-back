<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActionAuditLog;
use App\Models\CatecheseConfiguration;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SuperAdminAuditController extends Controller
{
    /**
     * Consultation du journal d'audit centralisé des actions Super Admin.
     */
    public function index(Request $request): JsonResponse
    {
        $query = ActionAuditLog::with(['user', 'paroisse', 'organisation'])
            ->latest('id');

        // Filtre Action
        if ($request->filled('action') && $request->action !== 'tous') {
            $query->where('action', $request->action);
        }

        // Filtre Module
        if ($request->filled('module') && $request->module !== 'tous') {
            $query->where('module', $request->module);
        }

        // Filtre Utilisateur
        if ($request->filled('user_id') || $request->filled('utilisateur')) {
            $uVal = $request->user_id ?? $request->utilisateur;
            if (is_numeric($uVal)) {
                $query->where('user_id', (int) $uVal);
            } else {
                $query->where(function ($q) use ($uVal) {
                    $q->where('user_uuid', $uVal)
                      ->orWhere('user_name', 'like', "%{$uVal}%")
                      ->orWhere('user_email', 'like', "%{$uVal}%");
                });
            }
        }

        // Filtre Paroisse
        if ($request->filled('paroisse_id')) {
            $pVal = $request->paroisse_id;
            $pId = is_numeric($pVal)
                ? (int) $pVal
                : CatecheseConfiguration::where('uuid', $pVal)->value('id');
            if ($pId) {
                $query->where('paroisse_configuration_id', $pId);
            }
        }

        // Filtre Organisation
        if ($request->filled('organisation_id')) {
            $oVal = $request->organisation_id;
            $oId = is_numeric($oVal)
                ? (int) $oVal
                : Organisation::where('uuid', $oVal)->value('id');
            if ($oId) {
                $query->where('organisation_id', $oId);
            }
        }

        // Filtre Période
        if ($request->filled('date_debut')) {
            $query->whereDate('created_at', '>=', $request->date_debut);
        }
        if ($request->filled('date_fin')) {
            $query->whereDate('created_at', '<=', $request->date_fin);
        }

        // Recherche globale
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('user_name', 'like', "%{$search}%")
                  ->orWhere('user_email', 'like', "%{$search}%")
                  ->orWhere('action', 'like', "%{$search}%")
                  ->orWhere('module', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->input('per_page', 20);
        $result = $query->paginate($perPage);

        $items = collect($result->items())->map(function ($log) {
            return [
                'id'                => $log->uuid,
                'uuid'              => $log->uuid,
                'date'              => $log->created_at?->toIso8601String(),
                'action'            => $log->action,
                'module'            => $log->module,
                'description'       => $log->description,
                'utilisateur'       => [
                    'id'    => $log->user_uuid ?? $log->user?->uuid,
                    'nom'   => $log->user_name ?? $log->user?->name,
                    'email' => $log->user_email ?? $log->user?->email,
                ],
                'profil'            => $log->profil,
                'ip'                => $log->ip_address,
                'navigateur'        => $log->user_agent,
                'paroisse'          => $log->paroisse ? [
                    'id'  => $log->paroisse->uuid,
                    'nom' => $log->paroisse->nom_paroisse,
                ] : null,
                'organisation'      => $log->organisation ? [
                    'id'   => $log->organisation->uuid,
                    'nom'  => $log->organisation->nom,
                    'type' => $log->organisation->type_organisation,
                ] : null,
                'ancienne_valeur'   => $log->anciennes_valeurs,
                'nouvelle_valeur'   => $log->nouvelles_valeurs,
            ];
        });

        return response()->json([
            'status'  => 'success',
            'message' => 'Journal d\'audit récupéré avec succès.',
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
     * Détail d'une entrée du journal d'audit.
     */
    public function show(string $id): JsonResponse
    {
        $log = is_numeric($id)
            ? ActionAuditLog::with(['user', 'paroisse', 'organisation'])->find((int) $id)
            : ActionAuditLog::with(['user', 'paroisse', 'organisation'])->where('uuid', $id)->first();

        if (!$log) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Entrée d\'audit introuvable.',
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Détails de l\'action d\'audit récupérés avec succès.',
            'data'    => [
                'id'                => $log->uuid,
                'uuid'              => $log->uuid,
                'date'              => $log->created_at?->toIso8601String(),
                'action'            => $log->action,
                'module'            => $log->module,
                'description'       => $log->description,
                'entite_id'         => $log->entite_id,
                'entite_uuid'       => $log->entite_uuid,
                'utilisateur'       => [
                    'id'    => $log->user_uuid ?? $log->user?->uuid,
                    'nom'   => $log->user_name ?? $log->user?->name,
                    'email' => $log->user_email ?? $log->user?->email,
                ],
                'profil'            => $log->profil,
                'ip'                => $log->ip_address,
                'navigateur'        => $log->user_agent,
                'paroisse'          => $log->paroisse ? [
                    'id'  => $log->paroisse->uuid,
                    'nom' => $log->paroisse->nom_paroisse,
                ] : null,
                'organisation'      => $log->organisation ? [
                    'id'   => $log->organisation->uuid,
                    'nom'  => $log->organisation->nom,
                    'type' => $log->organisation->type_organisation,
                ] : null,
                'ancienne_valeur'   => $log->anciennes_valeurs,
                'nouvelle_valeur'   => $log->nouvelles_valeurs,
                'created_at'        => $log->created_at?->toIso8601String(),
            ],
        ]);
    }
}
