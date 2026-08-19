<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MenuResource;
use App\Models\Menu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    /**
     * Liste hiérarchique de tous les menus et sous-menus configurés dans Catheo.
     */
    public function index(Request $request): JsonResponse
    {
        $menus = Menu::roots()
            ->where('is_active', true)
            ->with(['sousMenus' => function ($q) {
                $q->where('is_active', true)->orderBy('ordre', 'asc');
            }])
            ->orderBy('ordre', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data'   => MenuResource::collection($menus),
        ]);
    }
}
