<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    /**
     * Vérifie que l'utilisateur connecté est un Super Administrateur de la plateforme.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Non authentifié. Veuillez vous connecter avec un compte administrateur.',
            ], 401);
        }

        if (!method_exists($user, 'isSuperAdmin') || !$user->isSuperAdmin()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Accès refusé. Cet espace est strictement réservé au Super Administrateur de la plateforme.',
            ], 403);
        }

        return $next($request);
    }
}
