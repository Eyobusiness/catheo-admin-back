<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de contrôle des permissions CRUD, RESTORE et FORCE_DELETE par clé ou référence menu.
 *
 * Utilisation dans les routes :
 *   ->middleware('permission:catechumenes.create')
 *   ->middleware('permission:catechumenes.view')
 *   ->middleware('permission:catechumenes.edit')
 *   ->middleware('permission:catechumenes.delete')
 *   ->middleware('permission:catechumenes.restore')
 *   ->middleware('permission:catechumenes.force_delete')
 *
 * Le Super Admin (SUPER_ADMIN avec permissions ['*']) a toujours accès.
 */
class CheckPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Non authentifié.',
            ], 401);
        }

        // Vérification de permission via la méthode unifiée du modèle User (ou autorisation par défaut pour les acteurs mobiles)
        if (!method_exists($user, 'hasPermission') || $user->hasPermission($permission)) {
            return $next($request);
        }

        return response()->json([
            'status'  => 'error',
            'message' => "Accès refusé. Vous ne disposez pas de la permission requise : [{$permission}].",
            'required_permission' => $permission,
        ], 403);
    }
}
