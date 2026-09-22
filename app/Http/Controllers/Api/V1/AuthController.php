<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\ChangePasswordRequest;
use App\Http\Requests\Api\V1\Auth\ForgotPasswordRequest;
use App\Http\Requests\Api\V1\Auth\LoginRequest;
use App\Http\Requests\Api\V1\Auth\ResetPasswordRequest;
use App\Http\Requests\Api\V1\Auth\VerifyResetCodeRequest;
use App\Http\Resources\Api\V1\AnimateurResource;
use App\Http\Resources\Api\V1\AnneeCatecheseResource;
use App\Http\Resources\Api\V1\CatechumeneResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Mail\PasswordResetCodeMail;
use App\Models\Animateur;
use App\Models\AnneeCatechese;
use App\Models\Catechumene;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * 1. Authentification Administration & Personnel (Table `users`).
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $loginInput = trim($request->input('login', $request->input('email', '')));
        $password = $request->input('password');

        if (empty($loginInput)) {
            throw ValidationException::withMessages([
                'login' => ['L\'identifiant (email, téléphone ou nom d\'utilisateur) est obligatoire.'],
            ]);
        }

        $user = User::where('email', $loginInput)
            ->orWhere('telephone', $loginInput)
            ->orWhere('username', $loginInput)
            ->first();

        if (!$user || !Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'login' => ['Identifiants incorrects.'],
                'email' => ['Identifiants incorrects.'],
            ]);
        }

        if ($user->statut === 'inactif' || $user->statut === 'suspendu') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Ce compte administrateur a été désactivé ou suspendu.',
            ], 403);
        }

        if ($user->organisation_id && $user->organisation && $user->organisation->statut !== 'actif') {
            return response()->json([
                'status'  => 'error',
                'message' => "L'organisation rattachée à cet utilisateur est {$user->organisation->statut}.",
            ], 403);
        }

        $user->update(['dernier_login_at' => now()]);
        $token = $user->createToken($request->get('device_name', 'CatheoAdminToken'))->plainTextToken;
        $anneeCourante = AnneeCatechese::getAnneeCourante($user->paroisse_configuration_id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Connexion réussie.',
            'data'    => [
                'token'          => $token,
                'token_type'     => 'Bearer',
                'user_type'      => 'admin',
                'user'           => new UserResource($user->load(['paroisse', 'profil'])),
                'annee_courante' => $anneeCourante ? new AnneeCatecheseResource($anneeCourante) : null,
                'menus'          => $user->getAccessibleMenus(),
            ],
        ]);
    }

    /**
     * 2. Authentification Animateurs de Catéchèse (Table `animateurs`).
     */
    public function loginAnimateur(Request $request): JsonResponse
    {
        $request->validate([
            'login'    => 'required|string',
            'password' => 'required|string',
        ], [
            'login.required'    => 'Le numéro de téléphone ou l\'email est obligatoire.',
            'password.required' => 'Le mot de passe est obligatoire.',
        ]);

        $loginInput = trim($request->input('login'));
        $password = $request->input('password');

        $animateur = Animateur::where('telephone', $loginInput)
            ->orWhere('email', $loginInput)
            ->first();

        if (!$animateur || !$animateur->password || !Hash::check($password, $animateur->password)) {
            throw ValidationException::withMessages([
                'login' => ['Identifiants incorrects pour l\'espace Animateur.'],
            ]);
        }

        if ($animateur->statut === 'inactif') {
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
                'annee_courante' => $anneeCourante ? new AnneeCatecheseResource($anneeCourante) : null,
                'menus'          => $animateur->getAccessibleMenus(),
            ],
        ]);
    }

    /**
     * 3. Authentification Parents & Catéchumènes (Table `catechumenes`).
     */
    public function loginParent(Request $request): JsonResponse
    {
        $request->validate([
            'login'    => 'required|string',
            'password' => 'required|string',
        ], [
            'login.required'    => 'Le matricule (code catéchumène) ou numéro de téléphone parent est obligatoire.',
            'password.required' => 'Le mot de passe ou code d\'accès est obligatoire.',
        ]);

        $loginInput = trim($request->input('login'));
        $password = $request->input('password');

        $catechumene = Catechumene::where('matricule', $loginInput)
            ->orWhere('telephone_tuteur', $loginInput)
            ->orWhere('telephone_pere', $loginInput)
            ->orWhere('telephone_mere', $loginInput)
            ->orWhere('telephone', $loginInput)
            ->first();

        $isPassValid = $catechumene->password 
            ? Hash::check($password, $catechumene->password) 
            : ($password === '12345678');

        if (!$catechumene || !$isPassValid) {
            throw ValidationException::withMessages([
                'login' => ['Identifiants incorrects pour l\'espace Parent / Catéchumène.'],
            ]);
        }


        if ($catechumene->statut === 'abandon') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Le dossier catéchumène associé n\'est plus actif.',
            ], 403);
        }

        $catechumene->update(['dernier_login_at' => now()]);
        $token = $catechumene->createToken($request->get('device_name', 'CatheoParentToken'))->plainTextToken;
        $anneeCourante = AnneeCatechese::getAnneeCourante($catechumene->paroisse_configuration_id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Connexion parent réussie.',
            'data'    => [
                'token'          => $token,
                'token_type'     => 'Bearer',
                'user_type'      => 'parent',
                'user'           => new CatechumeneResource($catechumene->load(['paroisse', 'ceb', 'inscriptionsAnnuelles'])),
                'annee_courante' => $anneeCourante ? new AnneeCatecheseResource($anneeCourante) : null,
                'menus'          => $catechumene->getAccessibleMenus(),
            ],
        ]);
    }

    /**
     * Profil de l'utilisateur / compte actuellement connecté (Polymorphique Sanctum).
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $anneeCourante = AnneeCatechese::getAnneeCourante($user->paroisse_configuration_id);

        if ($user instanceof Animateur) {
            return response()->json([
                'status' => 'success',
                'data'   => [
                    'user_type'      => 'animateur',
                    'user'           => new AnimateurResource($user->load(['paroisse', 'affectations'])),
                    'annee_courante' => $anneeCourante ? new AnneeCatecheseResource($anneeCourante) : null,
                    'menus'          => $user->getAccessibleMenus(),
                ],
            ]);
        }

        if ($user instanceof Catechumene) {
            return response()->json([
                'status' => 'success',
                'data'   => [
                    'user_type'      => 'parent',
                    'user'           => new CatechumeneResource($user->load(['paroisse', 'ceb', 'inscriptionsAnnuelles'])),
                    'annee_courante' => $anneeCourante ? new AnneeCatecheseResource($anneeCourante) : null,
                    'menus'          => $user->getAccessibleMenus(),
                ],
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data'   => [
                'user_type'      => 'admin',
                'user'           => new UserResource($user->load(['paroisse', 'profil'])),
                'annee_courante' => $anneeCourante ? new AnneeCatecheseResource($anneeCourante) : null,
                'menus'          => $user->getAccessibleMenus(),
            ],
        ]);
    }

    /**
     * Rafraîchissement de token (Polymorphique Sanctum).
     */
    public function refresh(Request $request): JsonResponse
    {
        $user = $request->user();
        $request->user()->currentAccessToken()?->delete();

        $tokenName = match (true) {
            $user instanceof Animateur   => 'CatheoAnimateurToken',
            $user instanceof Catechumene => 'CatheoParentToken',
            default                      => 'CatheoAdminToken',
        };

        $newToken = $user->createToken($request->get('device_name', $tokenName))->plainTextToken;
        $anneeCourante = AnneeCatechese::getAnneeCourante($user->paroisse_configuration_id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Token rafraîchi avec succès.',
            'data'    => [
                'token'          => $newToken,
                'token_type'     => 'Bearer',
                'user_type'      => ($user instanceof Animateur) ? 'animateur' : (($user instanceof Catechumene) ? 'parent' : 'admin'),
                'user'           => ($user instanceof Animateur) ? new AnimateurResource($user->load(['paroisse', 'affectations'])) : (($user instanceof Catechumene) ? new CatechumeneResource($user->load(['paroisse', 'ceb', 'inscriptionsAnnuelles'])) : new UserResource($user->load(['paroisse', 'profil']))),
                'annee_courante' => $anneeCourante ? new AnneeCatecheseResource($anneeCourante) : null,
                'menus'          => $user->getAccessibleMenus(),
            ],
        ]);
    }

    /**
     * Changement sécurisé de mot de passe (Polymorphique Sanctum).
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        if (!Hash::check($request->input('current_password'), $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Le mot de passe actuel est incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->input('password')),
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Mot de passe modifié avec succès.',
        ]);
    }

    /**
     * Étape 1 : Demande de réinitialisation de mot de passe (Envoi code OTP).
     */
    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $email = strtolower(trim($request->input('email')));
        $user = User::where('email', $email)->first() 
             ?? Animateur::where('email', $email)->first();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Aucun compte trouvé avec cette adresse email.',
            ], 404);
        }

        $code = sprintf('%06d', random_int(100000, 999999));

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token'      => Hash::make($code),
                'created_at' => now(),
            ]
        );

        try {
            $name = $user->name ?? $user->nom_complet ?? 'Utilisateur';
            Mail::to($user->email)->send(new PasswordResetCodeMail($code, $name));
        } catch (\Throwable $e) {
            Log::error('Erreur lors de l\'envoi du code de réinitialisation : ' . $e->getMessage());
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Un code de réinitialisation à 6 chiffres a été envoyé à votre adresse email.',
            'data'    => [
                'email'              => $email,
                'expires_in_minutes' => 15,
            ],
        ]);
    }

    /**
     * Étape 2 : Vérification code OTP.
     */
    public function verifyResetCode(VerifyResetCodeRequest $request): JsonResponse
    {
        $email = strtolower(trim($request->input('email')));
        $code = trim($request->input('code'));

        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (!$record) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Aucune demande de réinitialisation active trouvée pour cet email.',
            ], 422);
        }

        $createdAt = Carbon::parse($record->created_at);
        if ($createdAt->addMinutes(15)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return response()->json([
                'status'  => 'error',
                'message' => 'Le code de réinitialisation a expiré. Veuillez faire une nouvelle demande.',
            ], 422);
        }

        if (!Hash::check($code, $record->token) && $code !== $record->token) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Le code de réinitialisation à 6 chiffres est invalide.',
            ], 422);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Code de réinitialisation valide.',
            'data'    => [
                'email'      => $email,
                'code_valid' => true,
            ],
        ]);
    }

    /**
     * Étape 3 : Réinitialisation définitive du mot de passe.
     */
    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $email = strtolower(trim($request->input('email')));
        $code = trim($request->input('code'));
        $password = $request->input('password');

        $record = DB::table('password_reset_tokens')->where('email', $email)->first();

        if (!$record) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Aucune demande de réinitialisation active trouvée pour cet email.',
            ], 422);
        }

        $createdAt = Carbon::parse($record->created_at);
        if ($createdAt->addMinutes(15)->isPast()) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();

            return response()->json([
                'status'  => 'error',
                'message' => 'Le code de réinitialisation a expiré. Veuillez faire une nouvelle demande.',
            ], 422);
        }

        if (!Hash::check($code, $record->token) && $code !== $record->token) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Le code de réinitialisation à 6 chiffres est invalide.',
            ], 422);
        }

        $account = User::where('email', $email)->first()
                ?? Animateur::where('email', $email)->first();

        if (!$account) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Compte introuvable.',
            ], 404);
        }

        $account->update([
            'password'         => Hash::make($password),
            'dernier_login_at' => now(),
        ]);

        DB::table('password_reset_tokens')->where('email', $email)->delete();
        $account->tokens()->delete();

        $tokenName = ($account instanceof Animateur) ? 'CatheoAnimateurToken' : 'CatheoAdminToken';
        $token = $account->createToken($request->get('device_name', $tokenName))->plainTextToken;
        $anneeCourante = AnneeCatechese::getAnneeCourante($account->paroisse_configuration_id);

        return response()->json([
            'status'  => 'success',
            'message' => 'Votre mot de passe a été réinitialisé avec succès.',
            'data'    => [
                'token'          => $token,
                'token_type'     => 'Bearer',
                'user_type'      => ($account instanceof Animateur) ? 'animateur' : 'admin',
                'user'           => ($account instanceof Animateur) ? new AnimateurResource($account->load(['paroisse', 'affectations'])) : new UserResource($account->load(['paroisse', 'profil'])),
                'annee_courante' => $anneeCourante ? new AnneeCatecheseResource($anneeCourante) : null,
                'menus'          => $account->getAccessibleMenus(),
            ],
        ]);
    }

    /**
     * Déconnexion Utilisateur / Animateur / Parent (Révocation Token Sanctum).
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Déconnexion réussie.',
        ]);
    }
}
