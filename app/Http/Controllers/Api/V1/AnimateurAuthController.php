<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AnimateurResource;
use App\Http\Resources\Api\V1\AnneeCatecheseResource;
use App\Http\Resources\Api\V1\MaClasseResource;
use App\Models\AffectationAnimateur;
use App\Models\Animateur;
use App\Models\AnneeCatechese;
use App\Models\InscriptionAnnuelle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AnimateurAuthController extends Controller
{
    /**
     * Connexion dédiée pour les Animateurs de Catéchèse.
     *
     * Accepte :
     * - `numero` (ou `login`, `telephone`, `email`)
     * - `password`
     */
    public function login(Request $request): JsonResponse
    {
        $request->validate([
            'numero'    => ['nullable', 'string'],
            'login'     => ['nullable', 'string'],
            'telephone' => ['nullable', 'string'],
            'email'     => ['nullable', 'string'],
            'password'  => ['required', 'string'],
        ], [
            'password.required' => 'Le mot de passe est obligatoire.',
        ]);

        $identifier = trim(
            $request->input('numero') 
            ?? $request->input('login') 
            ?? $request->input('telephone') 
            ?? $request->input('email') 
            ?? ''
        );

        if ($identifier === '') {
            throw ValidationException::withMessages([
                'numero' => ['Le numéro d\'animateur, numéro de téléphone ou email est obligatoire.'],
            ]);
        }

        $password = $request->input('password');

        $animateur = Animateur::where(function ($q) use ($identifier) {
            if (Schema::hasColumn('animateurs', 'numero')) {
                $q->where('numero', $identifier);
            }
            $q->orWhere('telephone', $identifier)
              ->orWhere('email', $identifier);
        })->first();

        // Vérification de la paroisse si précisée
        $paroisseId = $request->header('X-Paroisse-Id') 
            ?? $request->input('paroisse_configuration_id');

        if ($animateur && $paroisseId && (int) $animateur->paroisse_configuration_id !== (int) $paroisseId) {
            throw ValidationException::withMessages([
                'numero' => ['Cet animateur n\'appartient pas à la paroisse demandée.'],
            ]);
        }

        if (!$animateur || !$animateur->password || !Hash::check($password, $animateur->password)) {
            throw ValidationException::withMessages([
                'numero' => ['Identifiants incorrects pour l\'espace Animateur.'],
            ]);
        }

        if (strtolower(trim($animateur->statut ?? 'actif')) === 'inactif') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Votre compte animateur est inactif. Veuillez contacter le bureau de coordination.',
            ], 403);
        }

        $animateur->update(['dernier_login_at' => now()]);
        $token = $animateur->createToken($request->get('device_name', 'CatheoAnimateurToken'))->plainTextToken;
        $anneeCourante = AnneeCatechese::getAnneeCourante($animateur->paroisse_configuration_id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Connexion animateur réussie.',
            'data'    => [
                'token'          => $token,
                'token_type'     => 'Bearer',
                'user_type'      => 'animateur',
                'user'           => new AnimateurResource($animateur->load(['paroisse', 'affectations'])),
                'animateur'      => new AnimateurResource($animateur->load(['paroisse', 'affectations'])),
                'annee_courante' => $anneeCourante ? new AnneeCatecheseResource($anneeCourante) : null,
                'menus'          => $animateur->getAccessibleMenus(),
            ],
        ]);
    }

    /**
     * Profil de l'animateur actuellement authentifié.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var Animateur $animateur */
        $animateur = $request->user();
        $anneeCourante = AnneeCatechese::getAnneeCourante($animateur->paroisse_configuration_id);

        return response()->json([
            'status' => 'success',
            'data'   => [
                'user_type'      => 'animateur',
                'user'           => new AnimateurResource($animateur->load(['paroisse', 'affectations'])),
                'animateur'      => new AnimateurResource($animateur->load(['paroisse', 'affectations'])),
                'annee_courante' => $anneeCourante ? new AnneeCatecheseResource($anneeCourante) : null,
                'menus'          => $animateur->getAccessibleMenus(),
            ],
        ]);
    }

    /**
     * Déconnexion sécurisée (révocation du token Sanctum actuel).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Déconnexion réussie.',
        ]);
    }

    /**
     * Changement de mot de passe par l'animateur connecté.
     */
    
    /**
     * Mise à jour du profil par l'animateur connecté.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var Animateur $animateur */
        $animateur = $request->user();

        $validated = $request->validate([
            'nom'        => ['sometimes', 'required', 'string', 'max:255'],
            'prenoms'    => ['nullable', 'string', 'max:255'],
            'numero'     => ['sometimes', 'nullable', 'string', 'max:50'],
            'telephone'  => ['nullable', 'string', 'max:30'],
            'email'      => ['nullable', 'string', 'email', 'max:255'],
            'profession' => ['nullable', 'string', 'max:255'],
        ], [
            'nom.required' => 'Le nom est obligatoire.',
            'email.email'  => 'L\'adresse email saisie est invalide.',
        ]);

        $animateur->update($validated);
        $animateur->refresh();

        return response()->json([
            'status'  => 'success',
            'message' => 'Profil mis à jour avec succès.',
            'data'    => [
                'animateur' => new AnimateurResource($animateur->load(['paroisse', 'affectations'])),
            ],
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password'      => ['required', 'string'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ], [
            'current_password.required'      => 'Le mot de passe actuel est obligatoire.',
            'password.required'              => 'Le nouveau mot de passe est obligatoire.',
            'password.min'                   => 'Le nouveau mot de passe doit contenir au moins 8 caractères.',
            'password.confirmed'             => 'La confirmation du mot de passe ne correspond pas.',
            'password_confirmation.required' => 'La confirmation du mot de passe est obligatoire.',
        ]);

        /** @var Animateur $animateur */
        $animateur = $request->user();

        if (!Hash::check($request->input('current_password'), $animateur->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Le mot de passe actuel est incorrect.'],
            ]);
        }

        $animateur->update([
            'password' => Hash::make($request->input('password')),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Mot de passe modifié avec succès.',
        ]);
    }

    /**
     * Récupération automatique de la classe de l'animateur connecté pour l'année active.
     */
    public function maClasse(Request $request): JsonResponse
    {
        /** @var Animateur $animateur */
        $animateur = $request->user();

        // 1. Récupération de l'année pastorale avec fallback intelligent
        $annee = $request->attributes->get('working_annee')
            ?? AnneeCatechese::getAnneeCourante($animateur->paroisse_configuration_id);

        if (!$annee) {
            $annee = AnneeCatechese::where('paroisse_configuration_id', $animateur->paroisse_configuration_id)
                ->orderBy('date_debut', 'desc')
                ->first();
        }

        // 2. Recherche de l'affectation active de l'animateur
        $affectation = null;
        if ($annee) {
            $affectation = AffectationAnimateur::where('animateur_id', $animateur->id)
                ->where('annee_catechese_id', $annee->id)
                ->where('paroisse_configuration_id', $animateur->paroisse_configuration_id)
                ->with(['classe.niveau.section'])
                ->latest()
                ->first();
        }

        // Fallback : si non trouvée avec l'année courante, chercher la toute dernière affectation de l'animateur
        if (!$affectation) {
            $affectation = AffectationAnimateur::where('animateur_id', $animateur->id)
                ->where('paroisse_configuration_id', $animateur->paroisse_configuration_id)
                ->with(['classe.niveau.section', 'anneeCatechese'])
                ->latest()
                ->first();

            if ($affectation && $affectation->anneeCatechese) {
                $annee = $affectation->anneeCatechese;
            }
        }

        if (!$affectation || !$affectation->classe) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Votre affectation pour l\'année de catéchèse active n\'a pas été trouvée.',
            ], 404);
        }

        $classe = $affectation->classe;

        // 3. Récupération des catéchumènes inscrits dans cette classe
        $eleves = InscriptionAnnuelle::where('classe_id', $classe->id)
            ->where('paroisse_configuration_id', $animateur->paroisse_configuration_id)
            ->when($annee, function ($q) use ($annee) {
                $q->where('annee_catechese_id', $annee->id);
            })
            ->with(['catechumene'])
            ->get();

        // Si la liste est vide avec le filtre année, récupérer tous les élèves inscrits dans cette classe
        if ($eleves->isEmpty()) {
            $eleves = InscriptionAnnuelle::where('classe_id', $classe->id)
                ->where('paroisse_configuration_id', $animateur->paroisse_configuration_id)
                ->with(['catechumene'])
                ->get();
        }

        return response()->json([
            'status' => 'success',
            'data'   => new MaClasseResource([
                'animateur'       => $animateur,
                'annee_catechese' => $annee ?? (object)['uuid' => null, 'libelle' => 'Année en cours', 'statut' => 'active'],
                'affectation'     => $affectation,
                'classe'          => $classe,
                'eleves'          => $eleves,
            ]),
        ]);
    }
}
