<?php

namespace App\Http\Middleware;

use App\Models\Animateur;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAnimateur
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Non authentifié. Veuillez vous connecter avec vos identifiants Animateur.',
            ], 401);
        }

        if (!($user instanceof Animateur)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Accès réservé exclusivement aux animateurs de catéchèse.',
            ], 403);
        }

        if ($user->statut === 'inactif') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Votre compte animateur est inactif. Veuillez contacter le bureau de coordination.',
            ], 403);
        }

        return $next($request);
    }
}
