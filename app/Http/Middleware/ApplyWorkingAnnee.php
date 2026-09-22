<?php

namespace App\Http\Middleware;

use App\Models\AnneeCatechese;
use App\Models\CatecheseConfiguration;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApplyWorkingAnnee
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Ne pas surcharger sur les requetes publiques ou d'authentification
        if ($request->is('api/v1/auth/*') || $request->is('api/v1/preinscriptions/public*')) {
            return $next($request);
        }

        $user = $request->user();
        $paroisseId = $user?->paroisse_configuration_id 
            ?? $request->input('paroisse_configuration_id')
            ?? $request->input('paroisse_id')
            ?? $request->header('X-Paroisse-Id')
            ?? $request->header('X-Paroisse-Configuration-Id');

        if (!$paroisseId) {
            return $next($request);
        }

        // Resoudre l'annee demandee via X-Annee-Id, X-Annee-Catechese-Id, annee_catechese_id ou fallback annee courante
        $annee = AnneeCatechese::resolveAnnee($request, (int) $paroisseId);

        if ($annee) {
            // Sauvegarder sur l'objet requete pour acces facile
            $request->attributes->set('working_annee', $annee);

            // N'injecter annee_catechese_id que si :
            // 1. La route n'est pas le listing general de configuration des annees pastorales
            // 2. Le parametre annee_catechese_id n'a pas ete explicitement defini sur 'all' ou 'tous'
            $path = $request->path();
            $isAnneeCatalogRoute = str_ends_with($path, 'annee-catecheses') && $request->isMethod('GET');
            $explicitAll = in_array(strtolower((string) $request->input('annee_catechese_id')), ['all', 'tous', '*']);

            if (!$isAnneeCatalogRoute && !$explicitAll) {
                if (!$request->filled('annee_catechese_id')) {
                    if ($request->isMethod('GET')) {
                        $request->merge([
                            'annee_catechese_id' => (string) ($annee->uuid ?: $annee->id),
                            'working_annee_id'   => $annee->id,
                            'working_annee_uuid' => $annee->uuid,
                        ]);
                    } elseif ($request->isMethod('POST')) {
                        $request->merge([
                            'annee_catechese_id' => $annee->id,
                            'working_annee_id'   => $annee->id,
                            'working_annee_uuid' => $annee->uuid,
                        ]);
                    } else {
                        // Pour PUT, PATCH, DELETE, ne pas injecter annee_catechese_id dans la payload
                        $request->merge([
                            'working_annee_id'   => $annee->id,
                            'working_annee_uuid' => $annee->uuid,
                        ]);
                    }
                } else {
                    $val = $request->input('annee_catechese_id');
                    $foundAnnee = is_numeric($val)
                        ? AnneeCatechese::find((int) $val)
                        : AnneeCatechese::where('uuid', $val)->first();
                    if ($foundAnnee) {
                        $request->merge([
                            'working_annee_id'   => $foundAnnee->id,
                            'working_annee_uuid' => $foundAnnee->uuid,
                        ]);
                    }
                }
            }
        }

        $response = $next($request);

        if ($annee) {
            $response->headers->set('X-Applied-Annee-Id', (string) $annee->id);
            $response->headers->set('X-Applied-Annee-Libelle', (string) $annee->libelle);
        }

        return $response;
    }
}
