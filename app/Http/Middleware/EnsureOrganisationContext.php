<?php

namespace App\Http\Middleware;

use App\Models\Organisation;
use App\Services\SecurityContextService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureOrganisationContext
{
    public function __construct(
        protected SecurityContextService $securityService
    ) {}

    /**
     * Valide et impose le contexte organisationnel de manière certifiée par le backend.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Non authentifié. Veuillez vous connecter.',
            ], 401);
        }

        $context = $this->securityService->getContext($user, $request);

        /** @var Organisation|null $organisation */
        $organisation = $context['organisation'];

        if (!$organisation) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Accès refusé. Aucun contexte d\'organisation valide n\'a été détecté pour votre compte.',
            ], 403);
        }

        if ($organisation->statut !== 'actif') {
            return response()->json([
                'status'  => 'error',
                'message' => "Accès refusé. L'organisation [{$organisation->nom}] est actuellement {$organisation->statut}.",
            ], 403);
        }

        // Injection certifiée dans les attributs de la requête
        $request->attributes->set('organisation', $organisation);
        $request->attributes->set('security_context', $context);

        return $next($request);
    }
}
