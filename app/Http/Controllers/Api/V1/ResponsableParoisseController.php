<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ResponsableParoisseResource;
use App\Models\ResponsableParoisse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ResponsableParoisseController extends Controller
{
    /**
     * Liste des responsables de la paroisse du tenant connecté.
     */
    public function index(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $responsables = ResponsableParoisse::where('paroisse_configuration_id', $paroisseId)
            ->orderBy('ordre_affichage')
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => ResponsableParoisseResource::collection($responsables),
        ]);
    }

    /**
     * Ajouter un responsable de paroisse (Modal Ajouter un nouveau responsable).
     */
    public function store(Request $request): JsonResponse
    {
        $paroisseId = $request->user()->paroisse_configuration_id;

        $validated = $request->validate([
            'nom_prenoms' => ['required', 'string', 'max:255'],
            'fonction' => ['required', 'string', 'max:255'],
            'titre' => ['nullable', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'signature' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'signature_path' => ['nullable', 'string', 'max:550'],
            'ordre_affichage' => ['nullable', 'integer'],
        ]);

        $validated['paroisse_configuration_id'] = $paroisseId;

        if ($request->hasFile('signature')) {
            $validated['signature_path'] = $request->file('signature')->store('signatures', 'public');
        }

        $responsable = ResponsableParoisse::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Responsable ajouté avec succès.',
            'data' => new ResponsableParoisseResource($responsable),
        ], 201);
    }

    /**
     * Afficher un responsable par son UUID.
     */
    public function show(Request $request, ResponsableParoisse $responsable): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $responsable->paroisse_configuration_id);

        return response()->json([
            'status' => 'success',
            'data' => new ResponsableParoisseResource($responsable),
        ]);
    }

    /**
     * Mettre à jour un responsable de paroisse.
     */
    public function update(Request $request, ResponsableParoisse $responsable): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $responsable->paroisse_configuration_id);

        $validated = $request->validate([
            'nom_prenoms' => ['sometimes', 'required', 'string', 'max:255'],
            'fonction' => ['sometimes', 'required', 'string', 'max:255'],
            'titre' => ['nullable', 'string', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'string', 'email', 'max:255'],
            'signature' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'signature_path' => ['nullable', 'string', 'max:550'],
            'ordre_affichage' => ['nullable', 'integer'],
        ]);

        if ($request->hasFile('signature')) {
            if ($responsable->signature_path && Storage::disk('public')->exists($responsable->signature_path)) {
                Storage::disk('public')->delete($responsable->signature_path);
            }
            $validated['signature_path'] = $request->file('signature')->store('signatures', 'public');
        }

        $responsable->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Responsable mis à jour avec succès.',
            'data' => new ResponsableParoisseResource($responsable),
        ]);
    }

    /**
     * Supprimer un responsable de paroisse.
     */
    public function destroy(Request $request, ResponsableParoisse $responsable): JsonResponse
    {
        $this->authorizeTenant($request->user()->paroisse_configuration_id, $responsable->paroisse_configuration_id);

        if ($responsable->signature_path && Storage::disk('public')->exists($responsable->signature_path)) {
            Storage::disk('public')->delete($responsable->signature_path);
        }

        $responsable->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Responsable supprimé avec succès.',
        ]);
    }

    private function authorizeTenant(?int $userParoisseId, int $targetParoisseId): void
    {
        if ($userParoisseId && $userParoisseId !== $targetParoisseId) {
            abort(response()->json(['status' => 'error', 'message' => 'Accès refusé.'], 403));
        }
    }
}
