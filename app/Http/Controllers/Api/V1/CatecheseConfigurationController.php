<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\CatecheseConfigurationResource;
use App\Models\CatecheseConfiguration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CatecheseConfigurationController extends Controller
{
    /**
     * Obtenir la configuration de la catéchèse / paroisse pour l'établissement connecté.
     */
    public function show(Request $request): JsonResponse
    {
        $currentUser = $request->user() ?? auth('sanctum')->user();
        $paroisseId = $currentUser?->paroisse_configuration_id 
            ?? $request->input('paroisse_configuration_id')
            ?? $request->input('paroisse_id')
            ?? $request->header('X-Paroisse-Id')
            ?? $request->header('X-Paroisse-Configuration-Id')
            ?? $request->input('code_paroisse');

        if (!$paroisseId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Aucune configuration de catéchèse rattachée au compte actuel ou spécifiée.',
            ], 404);
        }

        $config = is_numeric($paroisseId)
            ? CatecheseConfiguration::find((int) $paroisseId)
            : CatecheseConfiguration::where('uuid', $paroisseId)->orWhere('code_paroisse', $paroisseId)->first();

        if (!$config) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Configuration de catéchèse introuvable.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => new CatecheseConfigurationResource($config),
        ]);
    }

    /**
     * Mettre à jour la configuration de la catéchèse / paroisse.
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
                'message' => 'Aucune configuration de catéchèse rattachée au compte actuel ou spécifiée.',
            ], 404);
        }

        if ($currentUser && $currentUser->paroisse_configuration_id && (int) $currentUser->paroisse_configuration_id !== (int) $paroisseId) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Accès refusé. Vous ne pouvez modifier que la configuration de votre paroisse.',
            ], 403);
        }

        $config = is_numeric($paroisseId)
            ? CatecheseConfiguration::find((int) $paroisseId)
            : CatecheseConfiguration::where('uuid', $paroisseId)->orWhere('code_paroisse', $paroisseId)->first();

        if (!$config) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Configuration de catéchèse introuvable.',
            ], 404);
        }

        $validated = $request->validate([
            'nom_paroisse'             => ['sometimes', 'nullable', 'string', 'max:255'],
            'nom'                      => ['sometimes', 'nullable', 'string', 'max:255'],
            'code_paroisse'            => ['nullable', 'string', 'max:50'],
            'prefixe_matricule'        => ['sometimes', 'nullable', 'string', 'regex:/^[a-zA-Z]+$/', 'max:10'],
            'prefixe_recu'             => ['sometimes', 'nullable', 'string', 'regex:/^[a-zA-Z]+$/', 'max:10'],
            'diocese'                  => ['sometimes', 'nullable', 'string', 'max:255'],
            'doyenne'                  => ['sometimes', 'nullable', 'string', 'max:255'],
            'ville'                    => ['sometimes', 'nullable', 'string', 'max:255'],
            'commune'                  => ['sometimes', 'nullable', 'string', 'max:255'],
            'telephone'                => ['sometimes', 'nullable', 'string', 'max:50'],
            'email'                    => ['sometimes', 'nullable', 'string', 'email', 'max:255'],
            'site_web'                 => ['nullable', 'string', 'max:255'],
            'adresse'                  => ['sometimes', 'nullable', 'string'],
            'cure_nom'                 => ['nullable', 'string', 'max:255'],
            'coordination_nom'         => ['nullable', 'string', 'max:255'],
            'statut'                   => ['nullable', 'string', 'in:actif,suspendu,inactif'],
            'logo_paroisse'            => ['nullable'],
            'logo_catechese'           => ['nullable'],
            'supprimer_logo_paroisse'  => ['nullable'],
            'supprimer_logo_catechese' => ['nullable'],
        ]);

        if (isset($validated['prefixe_matricule'])) {
            $validated['prefixe_matricule'] = strtoupper(trim($validated['prefixe_matricule']));
        }
        if (isset($validated['prefixe_recu'])) {
            $validated['prefixe_recu'] = strtoupper(trim($validated['prefixe_recu']));
        }

        // Harmonisation nom_paroisse / nom
        if (!empty($validated['nom']) && empty($validated['nom_paroisse'])) {
            $validated['nom_paroisse'] = $validated['nom'];
        }
        unset($validated['nom']);

        // ─────────────────────────────────────────────────────────────
        // Gestion du logo Paroisse
        // ─────────────────────────────────────────────────────────────
        if ($request->hasFile('logo_paroisse')) {
            $file = $request->file('logo_paroisse');
            if ($file->isValid()) {
                // Supprimer l'ancien fichier s'il existe
                if ($config->logo_paroisse) {
                    $oldPath = str_contains($config->logo_paroisse, '/')
                        ? $config->logo_paroisse
                        : 'catechese/logos/paroisse/' . $config->logo_paroisse;
                    if (Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }
                $filename = $file->hashName();
                $file->storeAs('catechese/logos/paroisse', $filename, 'public');
                $validated['logo_paroisse'] = $filename;
                $validated['logo_path'] = $filename;
            }
        } elseif ($request->boolean('supprimer_logo_paroisse') || $request->input('logo_paroisse') === 'DELETE') {
            if ($config->logo_paroisse) {
                $oldPath = str_contains($config->logo_paroisse, '/')
                    ? $config->logo_paroisse
                    : 'catechese/logos/paroisse/' . $config->logo_paroisse;
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }
            $validated['logo_paroisse'] = null;
            $validated['logo_path'] = null;
        } else {
            // Aucun nouveau fichier envoyé : ne pas écraser le logo existant
            unset($validated['logo_paroisse']);
        }

        // ─────────────────────────────────────────────────────────────
        // Gestion du logo Catéchèse
        // ─────────────────────────────────────────────────────────────
        if ($request->hasFile('logo_catechese')) {
            $file = $request->file('logo_catechese');
            if ($file->isValid()) {
                // Supprimer l'ancien fichier s'il existe
                if ($config->logo_catechese) {
                    $oldPath = str_contains($config->logo_catechese, '/')
                        ? $config->logo_catechese
                        : 'catechese/logos/catechese/' . $config->logo_catechese;
                    if (Storage::disk('public')->exists($oldPath)) {
                        Storage::disk('public')->delete($oldPath);
                    }
                }
                $filename = $file->hashName();
                $file->storeAs('catechese/logos/catechese', $filename, 'public');
                $validated['logo_catechese'] = $filename;
            }
        } elseif ($request->boolean('supprimer_logo_catechese') || $request->input('logo_catechese') === 'DELETE') {
            if ($config->logo_catechese) {
                $oldPath = str_contains($config->logo_catechese, '/')
                    ? $config->logo_catechese
                    : 'catechese/logos/catechese/' . $config->logo_catechese;
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }
            $validated['logo_catechese'] = null;
        } else {
            // Aucun nouveau fichier envoyé : ne pas écraser le logo existant
            unset($validated['logo_catechese']);
        }

        // Nettoyage des clés temporaires
        unset($validated['supprimer_logo_paroisse'], $validated['supprimer_logo_catechese']);

        // Sauvegarde en base de données
        $config->update($validated);
        $config->refresh();

        return response()->json([
            'status' => 'success',
            'message' => 'Configuration de la catéchèse mise à jour avec succès.',
            'data' => new CatecheseConfigurationResource($config),
        ]);
    }
}
