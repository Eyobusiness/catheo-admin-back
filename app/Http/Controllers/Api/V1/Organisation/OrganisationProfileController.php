<?php

namespace App\Http\Controllers\Api\V1\Organisation;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\Organisation\OrganisationContextResource;
use App\Models\Organisation;
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
     * Met à jour les coordonnées et informations de l'organisation.
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
            'responsable_nom'       => 'nullable|string|max:255',
            'responsable_telephone' => 'nullable|string|max:30',
            'responsable_email'     => 'nullable|email|max:150',
        ]);

        $organisation->update($validated);

        return response()->json([
            'status'  => 'success',
            'message' => 'Informations de l\'organisation mises à jour avec succès.',
            'data'    => new OrganisationContextResource($organisation->fresh(['produit', 'paroisse'])),
        ]);
    }
}
