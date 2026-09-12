<?php

namespace Tests\Feature;

use App\Mail\PasswordResetCodeMail;
use App\Models\AffectationAnimateur;
use App\Models\Animateur;
use App\Models\AnneeCatechese;
use App\Models\CatecheseConfiguration;
use App\Models\Catechumene;
use App\Models\Classe;
use App\Models\InscriptionAnnuelle;
use App\Models\Niveau;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AnimateurAuthAndClasseTest extends TestCase
{
    use RefreshDatabase;

    protected CatecheseConfiguration $paroisse;
    protected AnneeCatechese $anneeActive;
    protected Animateur $animateur;
    protected Classe $classe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSetupSeeder::class);

        $this->paroisse = CatecheseConfiguration::first();
        $this->anneeActive = AnneeCatechese::getAnneeCourante($this->paroisse->id)
            ?? AnneeCatechese::first();

        // Créer un animateur de test dédié
        $this->animateur = Animateur::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'numero'                    => 'ANIM-2026-001',
            'nom'                       => 'Kouassi',
            'prenoms'                   => 'Jean-Eudes',
            'sexe'                      => 'M',
            'telephone'                 => '+2250700000001',
            'email'                     => 'jean.kouassi@catheo.ci',
            'password'                  => Hash::make('AnimateurPass123!'),
            'statut'                    => 'actif',
        ]);

        // Créer une section et niveau pour la classe
        $section = Section::firstOrCreate(
            ['code' => 'ENF', 'paroisse_configuration_id' => $this->paroisse->id],
            ['nom' => 'Section Enfants']
        );

        $niveau = Niveau::firstOrCreate(
            ['nom' => '1ère Année Communion', 'paroisse_configuration_id' => $this->paroisse->id],
            ['section_id' => $section->id]
        );

        // Créer une classe pour l'année active
        $this->classe = Classe::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'annee_catechese_id'        => $this->anneeActive->id,
            'niveau_id'                 => $niveau->id,
            'nom'                       => 'Classe Saint-Michel',
            'capacite_max'              => 35,
            'statut'                    => 'active',
        ]);
    }

    /**
     * 1. Login avec bons identifiants (numéro d'animateur + mot de passe).
     */
    public function test_animateur_can_login_with_numero_and_password(): void
    {
        $response = $this->postJson('/api/v1/animateur/login', [
            'numero'   => 'ANIM-2026-001',
            'password' => 'AnimateurPass123!',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Connexion animateur réussie.',
                'data'    => [
                    'user_type' => 'animateur',
                    'user'      => [
                        'numero'      => 'ANIM-2026-001',
                        'nom'         => 'Kouassi',
                        'prenoms'     => 'Jean-Eudes',
                    ],
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'token',
                    'token_type',
                    'user_type',
                    'user',
                    'menus',
                ],
            ]);

        $this->assertNotNull($this->animateur->fresh()->dernier_login_at);
    }

    /**
     * 2. Login avec le téléphone ou email comme identifiant de repli.
     */
    public function test_animateur_can_login_with_telephone_or_email(): void
    {
        // Via téléphone
        $responsePhone = $this->postJson('/api/v1/animateur/login', [
            'login'    => '+2250700000001',
            'password' => 'AnimateurPass123!',
        ]);
        $responsePhone->assertStatus(200);

        // Via email
        $responseEmail = $this->postJson('/api/v1/animateur/login', [
            'numero'   => 'jean.kouassi@catheo.ci',
            'password' => 'AnimateurPass123!',
        ]);
        $responseEmail->assertStatus(200);
    }

    /**
     * 3. Login avec mauvais numéro d'animateur -> 422.
     */
    public function test_login_fails_with_invalid_numero(): void
    {
        $response = $this->postJson('/api/v1/animateur/login', [
            'numero'   => 'INEXISTANT-999',
            'password' => 'AnimateurPass123!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['numero']);
    }

    /**
     * 4. Login avec mauvais mot de passe -> 422.
     */
    public function test_login_fails_with_wrong_password(): void
    {
        $response = $this->postJson('/api/v1/animateur/login', [
            'numero'   => 'ANIM-2026-001',
            'password' => 'MauvaisMotDePasse!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['numero']);
    }

    /**
     * 5. Animateur inactif -> 403.
     */
    public function test_inactive_animateur_cannot_login(): void
    {
        $this->animateur->update(['statut' => 'inactif']);

        $response = $this->postJson('/api/v1/animateur/login', [
            'numero'   => 'ANIM-2026-001',
            'password' => 'AnimateurPass123!',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'status'  => 'error',
                'message' => 'Votre compte animateur est inactif. Veuillez contacter le bureau de coordination.',
            ]);
    }

    /**
     * 6. Accès /me avec token valide.
     */
    public function test_authenticated_animateur_can_get_me_profile(): void
    {
        $token = $this->animateur->createToken('TestToken')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/animateur/me');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => [
                    'user_type' => 'animateur',
                    'user'      => [
                        'id'     => $this->animateur->uuid,
                        'numero' => 'ANIM-2026-001',
                    ],
                ],
            ]);
    }

    /**
     * 7. Token invalide ou absent -> 401.
     */
    public function test_unauthenticated_request_to_me_returns_401(): void
    {
        $response = $this->getJson('/api/v1/animateur/me');
        $response->assertStatus(401);
    }

    /**
     * 8. Déconnexion /logout révoque le token Sanctum.
     */
    public function test_animateur_can_logout_and_token_is_revoked(): void
    {
        $token = $this->animateur->createToken('TestToken')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/animateur/logout');

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Déconnexion réussie.',
            ]);

        // Vérifier que le token a bien été supprimé de la base de données
        $this->assertCount(0, $this->animateur->fresh()->tokens);

        // Oublier le guard en mémoire pour tester la prochaine requête
        app('auth')->forgetGuards();

        $subsequentResponse = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/animateur/me');

        $subsequentResponse->assertStatus(401);
    }

    /**
     * 9. Modification sécurisée de mot de passe connecté.
     */
    public function test_animateur_can_change_password_when_authenticated(): void
    {
        $token = $this->animateur->createToken('TestToken')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->postJson('/api/v1/animateur/change-password', [
                'current_password'      => 'AnimateurPass123!',
                'password'              => 'NouveauPass456!',
                'password_confirmation' => 'NouveauPass456!',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Mot de passe modifié avec succès.',
            ]);

        // Vérifier que le nouveau mot de passe fonctionne pour un login
        $loginResponse = $this->postJson('/api/v1/animateur/login', [
            'numero'   => 'ANIM-2026-001',
            'password' => 'NouveauPass456!',
        ]);

        $loginResponse->assertStatus(200);
    }

    /**
     * 10. Récupération de /ma-classe avec affectation active et élèves.
     */
    public function test_animateur_can_retrieve_assigned_classe_for_active_year(): void
    {
        // Créer l'affectation active
        $affectation = AffectationAnimateur::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'animateur_id'              => $this->animateur->id,
            'annee_catechese_id'        => $this->anneeActive->id,
            'classe_id'                 => $this->classe->id,
            'role_animateur'            => 'titulaire',
        ]);

        // Inscrire un catéchumène dans cette classe
        $catechumene = Catechumene::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'matricule'                 => 'CIM-2026-001',
            'nom'                       => 'Yao',
            'prenoms'                   => 'Marc',
            'sexe'                      => 'M',
            'date_naissance'            => '2015-05-12',
            'statut'                    => 'actif',
        ]);

        InscriptionAnnuelle::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'catechumene_id'            => $catechumene->id,
            'annee_catechese_id'        => $this->anneeActive->id,
            'classe_id'                 => $this->classe->id,
            'niveau_id'                 => $this->classe->niveau_id,
            'statut'                    => 'inscrit',
        ]);

        $token = $this->animateur->createToken('TestToken')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/animateur/ma-classe');

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'data'   => [
                    'animateur'   => [
                        'numero' => 'ANIM-2026-001',
                    ],
                    'affectation' => [
                        'role_animateur' => 'titulaire',
                    ],
                    'classe'      => [
                        'nom'             => 'Classe Saint-Michel',
                        'effectif_actuel' => 1,
                    ],
                    'total_eleves' => 1,
                ],
            ])
            ->assertJsonStructure([
                'data' => [
                    'animateur',
                    'annee_catechese',
                    'affectation',
                    'classe',
                    'niveau',
                    'section',
                    'total_eleves',
                    'eleves' => [
                        '*' => ['inscription_id', 'catechumene_id', 'matricule', 'nom', 'prenoms', 'statut'],
                    ],
                ],
            ]);
    }

    /**
     * 11. Récupération de /ma-classe sans affectation active -> 404.
     */
    public function test_animateur_without_affectation_receives_404(): void
    {
        $token = $this->animateur->createToken('TestToken')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $token)
            ->getJson('/api/v1/animateur/ma-classe');

        $response->assertStatus(404)
            ->assertJson([
                'status'  => 'error',
                'message' => 'Votre affectation pour l\'année de catéchèse active n\'a pas été trouvée.',
            ]);
    }

    /**
     * 12. Séparation stricte : Un administrateur (User) ne peut pas accéder aux routes de l'espace animateur -> 403.
     */
    public function test_admin_user_cannot_access_animateur_specific_routes(): void
    {
        $adminUser = User::first();
        $adminToken = $adminUser->createToken('AdminToken')->plainTextToken;

        $response = $this->withHeader('Authorization', 'Bearer ' . $adminToken)
            ->getJson('/api/v1/animateur/me');

        $response->assertStatus(403)
            ->assertJson([
                'status'  => 'error',
                'message' => 'Accès réservé exclusivement aux animateurs de catéchèse.',
            ]);
    }

    /**
     * 13. Mot de passe oublié : l'animateur reçoit bien un code OTP par email et peut réinitialiser son mot de passe.
     */
    public function test_animateur_can_reset_password_via_email_otp_flow(): void
    {
        Mail::fake();

        // Étape 1 : Demande de code OTP
        $forgotResponse = $this->postJson('/api/v1/animateur/forgot-password', [
            'email' => 'jean.kouassi@catheo.ci',
        ]);

        $forgotResponse->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Un code de réinitialisation à 6 chiffres a été envoyé à votre adresse email.',
            ]);

        Mail::assertSent(PasswordResetCodeMail::class);

        // Récupérer le code généré en base pour le test
        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', 'jean.kouassi@catheo.ci')
            ->first();
        $this->assertNotNull($resetRecord);

        // Injecter un code connu
        $code = '654321';
        DB::table('password_reset_tokens')
            ->where('email', 'jean.kouassi@catheo.ci')
            ->update([
                'token'      => Hash::make($code),
                'created_at' => now(),
            ]);

        // Étape 2 : Vérification du code
        $verifyResponse = $this->postJson('/api/v1/animateur/verify-code', [
            'email' => 'jean.kouassi@catheo.ci',
            'code'  => $code,
        ]);

        $verifyResponse->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Code de réinitialisation valide.',
            ]);

        // Étape 3 : Réinitialisation finale du mot de passe
        $resetResponse = $this->postJson('/api/v1/animateur/reset-password', [
            'email'                 => 'jean.kouassi@catheo.ci',
            'code'                  => $code,
            'password'              => 'ToutNouveauPass789!',
            'password_confirmation' => 'ToutNouveauPass789!',
        ]);

        $resetResponse->assertStatus(200)
            ->assertJson([
                'status'  => 'success',
                'message' => 'Votre mot de passe a été réinitialisé avec succès.',
            ]);

        // Vérifier la connexion avec le tout nouveau mot de passe
        $finalLogin = $this->postJson('/api/v1/animateur/login', [
            'numero'   => 'ANIM-2026-001',
            'password' => 'ToutNouveauPass789!',
        ]);

        $finalLogin->assertStatus(200);
    }
}
