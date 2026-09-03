<?php

namespace Tests\Feature;

use App\Models\AnneeCatechese;
use App\Models\CatecheseConfiguration;
use App\Models\Catechumene;
use App\Models\Classe;
use App\Models\DocumentGenere;
use App\Models\ModeleDocument;
use App\Models\Niveau;
use App\Models\Paiement;
use App\Models\Section;
use App\Models\User;
use Database\Seeders\InitialSetupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImpressionJsonTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected CatecheseConfiguration $paroisse;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(InitialSetupSeeder::class);

        $this->adminUser = User::where('email', 'admin.stpaul@catheo.ci')->first();
        $this->paroisse = CatecheseConfiguration::where('code_paroisse', 'PAR-STPAUL-01')->first();
        $this->token = $this->adminUser->createToken('TestDevice')->plainTextToken;
    }

    /**
     * Test 1: Les endpoints d'impression non authentifiés retournent 401.
     */
    public function test_unauthenticated_requests_return_401(): void
    {
        $this->getJson('/api/v1/impressions/entete')->assertStatus(401);
        $this->getJson('/api/v1/impressions/fiche-notes')->assertStatus(401);
        $this->getJson('/api/v1/impressions/liste-presence')->assertStatus(401);
        $this->getJson('/api/v1/paiements/non-existant/recu')->assertStatus(401);
        $this->getJson('/api/v1/catechumenes/non-existant/fiche-impression')->assertStatus(401);
    }

    /**
     * Test 2: Entête d'impression standardisé en JSON.
     */
    public function test_can_fetch_entete_impression_json(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/impressions/entete');

        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/json')
            ->assertJsonStructure([
                'status',
                'data',
                'entete' => [
                    'diocese',
                    'doyenne',
                    'paroisse',
                    'nom_paroisse',
                    'nom',
                    'ville',
                    'commune',
                    'adresse',
                    'telephone',
                    'email',
                    'site_web',
                    'cure_nom',
                    'coordination_nom',
                    'logo_url',
                    'annee',
                    'annee_libelle',
                    'annee_pastorale',
                    'date_edition',
                    'heure_edition',
                ],
            ]);

        $this->assertEquals('success', $response->json('status'));
        $this->assertStringNotContainsString('application/pdf', $response->headers->get('content-type'));
    }

    /**
     * Test 3: Fiche de notes en JSON.
     */
    public function test_can_fetch_fiche_notes_json(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/impressions/fiche-notes');

        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/json')
            ->assertJsonStructure([
                'status',
                'entete',
                'document' => [
                    'titre',
                    'classe_nom',
                    'section_nom',
                    'niveau_nom',
                    'annee_pastorale',
                    'animateurs',
                    'total_eleves',
                ],
                'colonnes',
                'lignes',
            ]);

        $this->assertEquals('FICHE DE NOTES', $response->json('document.titre'));
    }

    /**
     * Test 4: Liste de présences en JSON.
     */
    public function test_can_fetch_liste_presence_json(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/impressions/liste-presence');

        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/json')
            ->assertJsonStructure([
                'status',
                'entete',
                'document' => [
                    'titre',
                    'classe_nom',
                    'dates_seances',
                    'total_eleves',
                ],
                'lignes',
            ]);
    }

    /**
     * Test 5: Registre et liste des catéchumènes en JSON.
     */
    public function test_can_fetch_liste_catechumenes_json(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/impressions/liste-catechumenes');

        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/json')
            ->assertJsonStructure([
                'status',
                'entete',
                'document' => [
                    'titre',
                    'total_eleves',
                    'effectif_garcons',
                    'effectif_filles',
                    'total_baptises',
                    'total_non_baptises',
                ],
                'colonnes',
                'lignes',
            ]);
    }

    /**
     * Test 6: Suivi sacramental en JSON.
     */
    public function test_can_fetch_suivi_sacramental_json(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/impressions/suivi-sacramental');

        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/json')
            ->assertJsonStructure([
                'status',
                'entete',
                'document' => [
                    'titre',
                    'sacrament',
                    'total_candidats',
                ],
                'colonnes',
                'lignes',
            ]);
    }

    /**
     * Test 7: Fiche bilan annuel en JSON.
     */
    public function test_can_fetch_fiche_bilan_annuel_json(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/impressions/fiche-bilan-annuel');

        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/json')
            ->assertJsonStructure([
                'status',
                'entete',
                'document' => [
                    'titre',
                    'total_eleves',
                ],
                'colonnes',
                'lignes',
            ]);
    }

    /**
     * Test 8: Fiches de renseignements sacrements (Baptême, 1ère Communion, Confirmation) en JSON.
     */
    public function test_can_fetch_fiches_renseignement_sacrements_json(): void
    {
        $endpoints = [
            '/api/v1/impressions/fiche-renseignement-bapteme',
            '/api/v1/impressions/fiche-renseignement-premiere-communion',
            '/api/v1/impressions/fiche-renseignement-confirmation',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
                ->getJson($endpoint);

            $response->assertStatus(200)
                ->assertHeader('content-type', 'application/json')
                ->assertJsonStructure([
                    'status',
                    'entete',
                    'document',
                    'fiches',
                ]);
        }
    }

    /**
     * Test 9: Reçu de paiement officiel en JSON (404 si introuvable, structure complète si trouvé).
     */
    public function test_paiement_recu_json_structure(): void
    {
        // 404 si uuid inexistant
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/paiements/00000000-0000-0000-0000-000000000000/recu')
            ->assertStatus(404);

        $paiement = Paiement::where('paroisse_configuration_id', $this->paroisse->id)->first();
        if ($paiement) {
            $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
                ->getJson("/api/v1/paiements/{$paiement->uuid}/recu");

            $response->assertStatus(200)
                ->assertHeader('content-type', 'application/json')
                ->assertJsonStructure([
                    'status',
                    'entete',
                    'paroisse',
                    'paiement',
                    'recu' => [
                        'numero_recu',
                        'date_paiement',
                        'date_paiement_fr',
                        'heure_paiement',
                        'mode_paiement',
                        'montant_total',
                        'devise',
                        'format_recommande',
                        'beneficiaire' => [
                            'nom_complet',
                            'matricule',
                        ],
                        'lignes',
                        'caissier',
                    ],
                ]);

            $this->assertEquals($paiement->numero_recu, $response->json('recu.numero_recu'));
        }
    }

    /**
     * Test 10: Fiche individuelle du catéchumène en JSON.
     */
    public function test_catechumene_fiche_impression_json_structure(): void
    {
        // 404 si catéchumène inexistant
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/catechumenes/00000000-0000-0000-0000-000000000000/fiche-impression')
            ->assertStatus(404);

        $cat = Catechumene::where('paroisse_configuration_id', $this->paroisse->id)->first();
        if ($cat) {
            $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
                ->getJson("/api/v1/catechumenes/{$cat->uuid}/fiche-impression");

            $response->assertStatus(200)
                ->assertHeader('content-type', 'application/json')
                ->assertJsonStructure([
                    'status',
                    'entete',
                    'paroisse',
                    'catechumene' => [
                        'id',
                        'matricule',
                        'nom',
                        'prenom',
                        'nom_complet',
                        'sexe',
                        'filiation' => ['pere', 'mere', 'tuteur'],
                        'sacrements' => ['bapteme', 'premiere_communion', 'confirmation'],
                    ],
                ]);

            $this->assertEquals($cat->matricule, $response->json('catechumene.matricule'));
        }
    }

    /**
     * Test 11: Isolation Multi-Tenant (Tentative d'accès au reçu d'une autre paroisse -> 403).
     */
    public function test_tenant_isolation_on_print_endpoints(): void
    {
        // Créer une autre paroisse
        $autreParoisse = CatecheseConfiguration::create([
            'code_paroisse' => 'PAR-AUTRE-99',
            'nom_paroisse'  => 'Autre Paroisse Test',
            'diocese'       => 'Diocèse de Yopougon',
            'statut'        => 'actif',
        ]);

        $autreCat = Catechumene::create([
            'paroisse_configuration_id' => $autreParoisse->id,
            'matricule'                 => 'CAT-TEST-AUTRE',
            'nom'                       => 'Etranger',
            'prenoms'                   => 'Jean',
            'sexe'                      => 'M',
            'date_naissance'            => '2010-01-01',
            'lieu_naissance'            => 'Abidjan',
            'adresse'                   => 'Yopougon',
            'statut'                    => 'actif',
        ]);

        // L'admin de St-Paul tente d'accéder à la fiche d'un catéchumène de l'autre paroisse -> 403
        $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson("/api/v1/catechumenes/{$autreCat->uuid}/fiche-impression")
            ->assertStatus(403);
    }

    /**
     * Test 12: Alias de compatibilité `/pdf` retournant du JSON valide.
     */
    public function test_legacy_pdf_urls_return_valid_json(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/impressions/fiche-notes/pdf');

        $response->assertStatus(200)
            ->assertHeader('content-type', 'application/json')
            ->assertJsonStructure([
                'status',
                'entete',
                'document',
                'colonnes',
                'lignes',
            ]);
    }
}
