<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SystemNotificationResource;
use App\Models\SystemNotification;
use App\Services\NotificationManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SystemNotificationController extends Controller
{
    /**
     * Liste des notifications système et activités avec filtres & pagination.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $paroisseId = $user->paroisse_configuration_id ?? 1;

        $query = SystemNotification::forUser($user)->latest('id');

        // Filtre par type (alerte, activite, rappel, info)
        if ($request->filled('type') && $request->type !== 'all') {
            $query->where('type', $request->type);
        }

        // Filtre par statut lu/non-lu
        if ($request->has('is_read')) {
            $query->where('is_read', filter_var($request->is_read, FILTER_VALIDATE_BOOLEAN));
        }

        // Filtre de recherche
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('titre', 'like', "%{$search}%")
                  ->orWhere('message', 'like', "%{$search}%");
            });
        }

        $perPage = (int) $request->get('per_page', 20);
        $notifications = $query->paginate($perPage);

        // Compteur non lues
        $unreadCount = SystemNotification::forUser($user)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'status'       => 'success',
            'unread_count' => $unreadCount,
            'data'         => SystemNotificationResource::collection($notifications->items()),
            'meta'         => [
                'current_page' => $notifications->currentPage(),
                'last_page'    => $notifications->lastPage(),
                'per_page'     => $notifications->perPage(),
                'total'        => $notifications->total(),
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    /**
     * Nombre de notifications non lues (pour le badge de la cloche).
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $user = $request->user();

        $unreadCount = SystemNotification::forUser($user)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'status'       => 'success',
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * Résumé temps réel des alertes & rappels du Dashboard.
     */
    public function alertsSummary(Request $request): JsonResponse
    {
        $user = $request->user();
        $paroisseId = $user->paroisse_configuration_id ?? 1;

        $summary = NotificationManagerService::getAlertsSummary($paroisseId);

        return response()->json([
            'status' => 'success',
            'data'   => $summary,
        ]);
    }

    /**
     * Marquer une notification comme lue.
     */
    public function markAsRead(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        
        $notification = SystemNotification::forUser($user)->where('id', $id)->firstOrFail();
        $notification->markAsRead();

        return response()->json([
            'status'  => 'success',
            'message' => 'Notification marquée comme lue.',
            'data'    => new SystemNotificationResource($notification),
        ]);
    }

    /**
     * Marquer toutes les notifications comme lues.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();

        $affected = SystemNotification::forUser($user)
            ->where('is_read', false)
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return response()->json([
            'status'   => 'success',
            'message'  => 'Toutes les notifications ont été marquées comme lues.',
            'affected' => $affected,
        ]);
    }

    /**
     * Supprimer une notification.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();

        $notification = SystemNotification::forUser($user)->where('id', $id)->firstOrFail();
        $notification->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Notification supprimée.',
        ]);
    }

    /**
     * Nettoyer toutes les notifications lues.
     */
    public function clearRead(Request $request): JsonResponse
    {
        $user = $request->user();

        $deleted = SystemNotification::forUser($user)
            ->where('is_read', true)
            ->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Notifications lues nettoyées avec succès.',
            'deleted' => $deleted,
        ]);
    }
}
