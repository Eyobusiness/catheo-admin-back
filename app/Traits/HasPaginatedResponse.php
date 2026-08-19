<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Trait HasPaginatedResponse
 *
 * Fournit une méthode standardisée pour retourner une réponse paginée JSON :
 *
 *   {
 *     "status": "success",
 *     "data": [...],
 *     "meta": {
 *       "current_page": 1,
 *       "last_page": 5,
 *       "per_page": 15,
 *       "total": 72,
 *       "from": 1,
 *       "to": 15
 *     },
 *     "links": {
 *       "first": "http://...",
 *       "last": "http://...",
 *       "prev": null,
 *       "next": "http://..."
 *     }
 *   }
 *
 * Utilisation dans un Controller :
 *   use App\Traits\HasPaginatedResponse;
 *   ...
 *   return $this->paginatedResponse($paginatedQuery, ResourceClass::class);
 */
trait HasPaginatedResponse
{
    /**
     * Retourne une réponse JSON paginée standardisée.
     *
     * @param  LengthAwarePaginator  $paginator   Le résultat paginé (->paginate())
     * @param  string|null           $resource    La classe Resource (ex: CatechumeneResource::class), ou null
     * @return \Illuminate\Http\JsonResponse
     */
    protected function paginatedResponse(LengthAwarePaginator $paginator, ?string $resource = null): \Illuminate\Http\JsonResponse
    {
        $items = $resource
            ? $resource::collection($paginator->items())
            : $paginator->items();

        return response()->json([
            'status' => 'success',
            'data'   => $items,
            'meta'   => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
        ]);
    }

    /**
     * Extraire le per_page de la requête (borné entre 5 et 100).
     */
    protected function getPerPage(Request $request, int $default = 15): int
    {
        return max(5, min(100, (int) $request->get('per_page', $default)));
    }
}
