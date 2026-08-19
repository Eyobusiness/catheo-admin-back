<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ParoisseResource;
use App\Models\ParoisseConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ParoisseConfigurationController extends Controller
{
    /**
     * Obtenir la configuration de la paroisse du tenant connecté (Informations Institutionnelles).
     */
    public function show(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        if (!$currentUser->paroisse_configuration_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Aucune paroisse rattachée au compte actuel.',
            ], 404);
        }

        $paroisse = $currentUser->paroisse;

        return response()->json([
            'status' => 'success',
            'data' => new ParoisseResource($paroisse),
        ]);
    }

    /**
     * Mettre à jour la configuration de la paroisse (Informations Institutionnelles).
     */
    public function update(Request $request): JsonResponse
    {
        $currentUser = $request->user();

        if (!$currentUser->paroisse_configuration_id) {
            return response()->json([
                'status' => 'error',
                'message' => 'Aucune paroisse rattachée au compte actuel.',
            ], 404);
        }

        $validated = $request->validate([
            'nom' => ['sometimes', 'required', 'string', 'max:255'],
            'code_paroisse' => ['nullable', 'string', 'max:50'],
            'diocese' => ['sometimes', 'required', 'string', 'max:255'],
            'doyenne' => ['sometimes', 'required', 'string', 'max:255'],
            'telephone' => ['sometimes', 'required', 'string', 'max:50'],
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255'],
            'site_web' => ['nullable', 'string', 'max:255'],
            'adresse' => ['sometimes', 'required', 'string'],
            'cure_nom' => ['nullable', 'string', 'max:255'],
            'statut' => ['nullable', 'string', 'in:actif,suspendu,inactif'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg', 'max:2048'],
            'logo_path' => ['nullable', 'string', 'max:550'],
        ]);

        $paroisse = $currentUser->paroisse;

        if ($request->hasFile('logo')) {
            if ($paroisse->logo_path && Storage::disk('public')->exists($paroisse->logo_path)) {
                Storage::disk('public')->delete($paroisse->logo_path);
            }
            $validated['logo_path'] = $request->file('logo')->store('paroisses/logos', 'public');
        }

        $paroisse->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Informations institutionnelles de la paroisse mises à jour avec succès.',
            'data' => new ParoisseResource($paroisse),
        ]);
    }
}
