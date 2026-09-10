<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ApparenceResource;
use App\Models\ApparenceConfiguration;
use App\Models\CatecheseConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApparenceConfigurationController extends Controller
{
    /**
     * Obtenir la configuration d'apparence de la paroisse du tenant connecté.
     */
    public function show(Request $request): JsonResponse
    {
        $currentUser = $request->user() ?? auth('sanctum')->user();
        $paroisseId = $currentUser?->paroisse_configuration_id 
            ?? $request->input('paroisse_configuration_id')
            ?? $request->input('paroisse_id')
            ?? $request->header('X-Paroisse-Id')
            ?? $request->header('X-Paroisse-Configuration-Id');

        if (!$paroisseId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Aucune configuration de catéchèse trouvée.',
            ], 404);
        }

        $apparence = ApparenceConfiguration::firstOrCreate(
            ['paroisse_configuration_id' => (int) $paroisseId],
            [
                'couleur_principale' => '#4F46E5',
                'couleur_secondaire' => '#D97706',
                'police_caracteres'  => 'Inter',
            ]
        );

        return response()->json([
            'status' => 'success',
            'data'   => new ApparenceResource($apparence),
        ]);
    }

    /**
     * Mettre à jour la configuration d'apparence (Couleurs & Police).
     */
    public function update(Request $request): JsonResponse
    {
        $currentUser = $request->user() ?? auth('sanctum')->user();
        $paroisseId = $currentUser?->paroisse_configuration_id 
            ?? $request->input('paroisse_configuration_id')
            ?? $request->input('paroisse_id')
            ?? $request->header('X-Paroisse-Id')
            ?? $request->header('X-Paroisse-Configuration-Id');

        if (!$paroisseId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Aucune configuration de catéchèse trouvée.',
            ], 404);
        }

        if ($currentUser && $currentUser->paroisse_configuration_id && (int) $currentUser->paroisse_configuration_id !== (int) $paroisseId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Accès refusé. Vous ne pouvez modifier que l\'apparence de votre paroisse.',
            ], 403);
        }

        $validated = $request->validate([
            'couleur_principale' => ['sometimes', 'required', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'couleur_secondaire' => ['sometimes', 'required', 'string', 'regex:/^#([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/'],
            'police_caracteres'  => ['sometimes', 'required', 'string', 'in:Inter,Roboto,Outfit,Poppins,Nunito,DM Sans'],
            'entete_document'    => ['nullable', 'string'],
            'pied_page_document' => ['nullable', 'string'],
        ]);

        $apparence = ApparenceConfiguration::firstOrCreate(
            ['paroisse_configuration_id' => (int) $paroisseId],
            [
                'couleur_principale' => '#4F46E5',
                'couleur_secondaire' => '#D97706',
                'police_caracteres'  => 'Inter',
            ]
        );

        $apparence->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Configuration d\'apparence mise à jour avec succès.',
            'data'    => new ApparenceResource($apparence),
        ]);
    }

    /**
     * Restaurer les valeurs par défaut (Bouton Restaurer les valeurs par défaut).
     */
    public function reset(Request $request): JsonResponse
    {
        $currentUser = $request->user() ?? auth('sanctum')->user();
        $paroisseId = $currentUser?->paroisse_configuration_id 
            ?? $request->input('paroisse_configuration_id')
            ?? $request->input('paroisse_id')
            ?? $request->header('X-Paroisse-Id')
            ?? $request->header('X-Paroisse-Configuration-Id');

        if (!$paroisseId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Aucune configuration de catéchèse trouvée.',
            ], 404);
        }

        if ($currentUser && $currentUser->paroisse_configuration_id && (int) $currentUser->paroisse_configuration_id !== (int) $paroisseId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Accès refusé.',
            ], 403);
        }

        $apparence = ApparenceConfiguration::firstOrCreate(
            ['paroisse_configuration_id' => (int) $paroisseId]
        );

        $apparence->update([
            'couleur_principale' => '#4F46E5',
            'couleur_secondaire' => '#D97706',
            'police_caracteres'  => 'Inter',
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Valeurs d\'apparence par défaut restaurées avec succès.',
            'data'    => new ApparenceResource($apparence),
        ]);
    }
}
