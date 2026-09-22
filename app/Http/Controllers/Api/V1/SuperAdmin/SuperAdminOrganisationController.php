<?php

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\SuperAdmin\StoreResponsableOrganisationRequest;
use App\Http\Resources\Api\V1\Organisation\OrganisationUserResource;
use App\Models\Organisation;
use App\Models\Profil;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SuperAdminOrganisationController extends Controller
{
    /**
     * Liste de toutes les organisations de la plateforme.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Organisation::with(['produit', 'paroisse', 'users'])
            ->withCount(['membres', 'activites', 'users'])
            ->latest('id');

        if ($request->filled('type_organisation') && $request->type_organisation !== 'tous') {
            $query->where('type_organisation', $request->type_organisation);
        }

        if ($request->filled('statut') && $request->statut !== 'tous') {
            $query->where('statut', $request->statut);
        }

        if ($request->filled('paroisse_id')) {
            $query->where('paroisse_configuration_id', $request->paroisse_id);
        }

        $perPage = (int) $request->input('per_page', 15);
        $result = $query->paginate($perPage);

        return response()->json([
            'status'  => 'success',
            'message' => 'Liste des organisations récupérée avec succès.',
            'data'    => $result->items(),
            'meta'    => [
                'current_page' => $result->currentPage(),
                'last_page'    => $result->lastPage(),
                'per_page'     => $result->perPage(),
                'total'        => $result->total(),
            ],
        ]);
    }

    /**
     * Fiche d'une organisation.
     */
    public function show(Organisation $organisation): JsonResponse
    {
        $organisation->load(['produit', 'paroisse', 'users.profil', 'membres', 'activites']);

        return response()->json([
            'status'  => 'success',
            'message' => 'Détails de l\'organisation récupérés avec succès.',
            'data'    => $organisation,
        ]);
    }

    /**
     * Provisionner le premier responsable d'une organisation après activation.
     */
    public function createResponsable(StoreResponsableOrganisationRequest $request, Organisation $organisation): JsonResponse
    {
        $data = $request->validated();

        // Profil par défaut pour le responsable selon le type d'organisation
        $profilId = $data['profil_id'] ?? null;
        if (!$profilId) {
            $codeProfil = "RESPONSABLE_{$organisation->type_organisation}";
            $profil = Profil::where('code', $codeProfil)->first();
            if ($profil) {
                $profilId = $profil->id;
            }
        }

        $user = User::create([
            'name'                      => $data['name'],
            'email'                     => $data['email'],
            'telephone'                 => $data['telephone'] ?? null,
            'password'                  => Hash::make($data['password'] ?? 'Responsable123!'),
            'paroisse_configuration_id' => $organisation->paroisse_configuration_id,
            'organisation_id'           => $organisation->id,
            'profil_id'                 => $profilId,
            'user_type'                 => 'admin',
            'statut'                    => 'actif',
        ]);

        // Mettre à jour les informations du responsable sur la fiche organisation
        $organisation->update([
            'responsable_nom'       => $user->name,
            'responsable_email'     => $user->email,
            'responsable_telephone' => $user->telephone,
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Premier responsable de l\'organisation créé avec succès.',
            'data'    => new OrganisationUserResource($user->load('profil')),
        ], 201);
    }
}
