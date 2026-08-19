<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreAuditLogRequest;
use App\Http\Resources\Api\V1\AuditLogResource;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    /**
     * Consultation du journal d'audit de sécurité des actions utilisateurs.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $query = AuditLog::with('user')
            ->where('paroisse_configuration_id', $paroisseId);

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('entite_type')) {
            $query->where('entite_type', $request->entite_type);
        }

        $logs = $query->latest()->paginate($request->get('per_page', 25));

        return response()->json([
            'status' => 'success',
            'data' => AuditLogResource::collection($logs->items()),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Consigner manuellement une entrée dans le journal d'audit.
     */
    public function store(StoreAuditLogRequest $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;
        $validated = $request->validated();

        $validated['paroisse_configuration_id'] = $paroisseId;
        $validated['user_id'] = $request->user()->id;
        $validated['ip_address'] = $request->ip();
        $validated['user_agent'] = $request->userAgent();

        $log = AuditLog::create($validated);
        $log->load('user');

        return response()->json([
            'status' => 'success',
            'message' => 'Action consignée dans le journal d\'audit.',
            'data' => new AuditLogResource($log),
        ], 201);
    }

    /**
     * Détails d'une entrée du journal d'audit.
     */
    public function show(Request $request, AuditLog $auditLog): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $auditLog->paroisse_configuration_id);

        $auditLog->load('user');

        return response()->json([
            'status' => 'success',
            'data' => new AuditLogResource($auditLog),
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
