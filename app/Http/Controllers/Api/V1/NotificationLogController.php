<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreNotificationLogRequest;
use App\Http\Resources\Api\V1\NotificationLogResource;
use App\Models\NotificationLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationLogController extends Controller
{
    /**
     * Historique des notifications d'envoi (SMS / Email / In-App).
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $query = NotificationLog::where('paroisse_configuration_id', $paroisseId);

        if ($request->filled('canal')) {
            $query->where('canal', $request->canal);
        }

        if ($request->filled('statut_envoi')) {
            $query->where('statut_envoi', $request->statut_envoi);
        }

        $logs = $query->latest()->paginate($request->get('per_page', 20));

        return response()->json([
            'status' => 'success',
            'data' => NotificationLogResource::collection($logs->items()),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
        ]);
    }

    /**
     * Enregistrer la traçabilité d'un envoi de notification.
     */
    public function store(StoreNotificationLogRequest $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;
        $validated = $request->validated();

        $validated['paroisse_configuration_id'] = $paroisseId;
        $validated['statut_envoi'] = $validated['statut_envoi'] ?? 'envoye';
        $validated['date_envoi'] = $validated['date_envoi'] ?? now();

        $log = NotificationLog::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Notification consignée avec succès.',
            'data' => new NotificationLogResource($log),
        ], 201);
    }

    /**
     * Détails d'une notification.
     */
    public function show(Request $request, NotificationLog $notificationLog): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $notificationLog->paroisse_configuration_id);

        return response()->json([
            'status' => 'success',
            'data' => new NotificationLogResource($notificationLog),
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
