<?php

namespace App\Http\Controllers\Api\V1\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Organisation\OrganisationContextResource;
use App\Models\ActionAuditLog;
use App\Models\CatecheseConfiguration;
use App\Models\Organisation;
use App\Services\SuperAdmin\ActionAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganisationProfileController extends Controller
{
    /**
     * Récupère le contexte certifié de l'organisation courante.
     */
    public function context(Request $request): JsonResponse
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        return response()->json([
            'status'  => 'success',
            'message' => 'Contexte organisationnel certifié.',
            'data'    => new OrganisationContextResource($organisation->load(['produit', 'paroisse'])),
        ]);
    }

    /**
     * Met à jour les coordonnées et informations de l'organisation (Logo, Paroisse, Mode, etc.).
     */
    public function update(Request $request): JsonResponse
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        $validated = $request->validate([
            'nom'                   => 'sometimes|required|string|max:255',
            'description'           => 'nullable|string',
            'telephone'             => 'nullable|string|max:30',
            'email'                 => 'nullable|email|max:150',
            'adresse'               => 'nullable|string',
            'responsable'           => 'nullable|string|max:255',
            'responsable_nom'       => 'nullable|string|max:255',
            'responsable_telephone' => 'nullable|string|max:30',
            'responsable_email'     => 'nullable|email|max:150',
            'mode'                  => 'sometimes|in:liee,independant',
            'paroisse_id'           => 'nullable',
            'logo'                  => 'nullable|image|max:2048',
        ]);

        $anciennesValeurs = $organisation->only([
            'nom', 'description', 'telephone', 'email', 'adresse',
            'responsable_nom', 'responsable_telephone', 'responsable_email',
            'mode', 'paroisse_configuration_id', 'logo_path',
        ]);

        // Mapping responsable -> responsable_nom
        if (!empty($validated['responsable']) && empty($validated['responsable_nom'])) {
            $validated['responsable_nom'] = $validated['responsable'];
        }
        unset($validated['responsable']);

        // Mapping paroisse_id -> paroisse_configuration_id
        if (array_key_exists('paroisse_id', $validated)) {
            $validated['paroisse_configuration_id'] = !empty($validated['paroisse_id']) ? (int) $validated['paroisse_id'] : null;
            unset($validated['paroisse_id']);
        }

        if (isset($validated['mode']) && $validated['mode'] === 'independant') {
            $validated['paroisse_configuration_id'] = null;
        }

        // Gestion du logo
        if ($request->hasFile('logo') && $request->file('logo')->isValid()) {
            $logoFile = $request->file('logo');
            $logoName = 'org_' . $organisation->uuid . '_' . time() . '.' . $logoFile->getClientOriginalExtension();
            $logoFile->storeAs('organisations/logos', $logoName, 'public');
            $validated['logo_path'] = $logoName;
        }
        unset($validated['logo']);

        $organisation->update($validated);

        try {
            ActionAuditService::log(
                action: 'update',
                module: 'Organisation',
                description: "Mise à jour des informations de l'organisation {$organisation->nom}",
                entite: $organisation,
                anciennesValeurs: $anciennesValeurs,
                nouvellesValeurs: $organisation->only(array_keys($validated)),
                paroisseId: $organisation->paroisse_configuration_id,
                organisationId: $organisation->id,
                request: $request
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("ActionAuditService failed during org profile update: " . $e->getMessage());
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Informations de l\'organisation mises à jour avec succès.',
            'data'    => new OrganisationContextResource($organisation->fresh(['produit', 'paroisse'])),
        ]);
    }

    /**
     * Journal d'audit et historique des actions de l'organisation.
     */
    public function auditLogs(Request $request): JsonResponse
    {
        /** @var Organisation $organisation */
        $organisation = $request->attributes->get('organisation');

        $query = ActionAuditLog::where('organisation_id', $organisation->id);

        if ($request->filled('action')) {
            $query->where('action', $request->action);
        }

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('description', 'like', "%{$s}%")
                  ->orWhere('user_name', 'like', "%{$s}%")
                  ->orWhere('action', 'like', "%{$s}%")
                  ->orWhere('module', 'like', "%{$s}%");
            });
        }

        $perPage = (int) $request->input('per_page', 25);
        $logs = $query->latest()->paginate($perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Journal d\'audit de l\'organisation récupéré avec succès.',
            'data'    => $logs->items(),
            'meta'    => [
                'current_page' => $logs->currentPage(),
                'last_page'    => $logs->lastPage(),
                'per_page'     => $logs->perPage(),
                'total'        => $logs->total(),
            ],
        ]);
    }

    /**
     * Liste des paroisses actives pour le rattachement en mode liée.
     */
    public function paroissesList(): JsonResponse
    {
        $paroisses = CatecheseConfiguration::select('id', 'nom_paroisse', 'code_paroisse', 'ville', 'commune', 'diocese')
            ->where('statut', 'actif')
            ->orderBy('nom_paroisse')
            ->get();

        return response()->json([
            'status'  => 'success',
            'message' => 'Liste des paroisses disponibles récupérée avec succès.',
            'data'    => $paroisses,
        ]);
    }
}