<?php

namespace Tests\Feature;

use App\Models\AnneeCatechese;
use App\Models\CatecheseConfiguration;
use App\Models\Catechumene;
use App\Models\Classe;
use App\Models\DocumentGenere;
use App\Models\InscriptionAnnuelle;
use App\Models\ModeleDocument;
use App\Models\Niveau;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentOfficielTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected CatecheseConfiguration $paroisse;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\InitialSetupSeeder::class);

        $this->adminUser = User::where('email', 'admin.stpaul@catheo.ci')->first();
        $this->paroisse = CatecheseConfiguration::where('code_paroisse', 'PAR-STPAUL-01')->first();
        $this->token = $this->adminUser->createToken('TestDevice')->plainTextToken;
    }

    public function test_can_list_and_create_modele_document(): void
    {
        // 1. Liste des modèles pré-seedés
        $listRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/modeles-documents');

        $listRes->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertGreaterThanOrEqual(1, count($listRes->json('data')));

        // 2. Créer un modèle personnalisé
        $createRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/modeles-documents', [
                'titre'         => 'Attestation de Récollection Pastorale',
                'code'          => 'ATTEST_RECOLLECTION',
                'type_document' => 'attestation',
                'description'   => 'Attestation de participation à la retraite pastorale annuelle.',
                'contenu'       => '<p>Le catéchumène <strong>{{nom_complet}}</strong> a participé à la récollection.</p>',
            ]);

        $createRes->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.titre', 'Attestation de Récollection Pastorale')
            ->assertJsonPath('data.code', 'ATTEST_RECOLLECTION');
    }

    public function test_can_update_and_toggle_status_modele_document(): void
    {
        $modele = ModeleDocument::where('code', 'CERT_BAPTEME')->first();

        // 1. Modifier le modèle
        $updateRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->putJson('/api/v1/modeles-documents/' . $modele->uuid, [
                'description' => 'Description mise à jour pour le certificat.',
            ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.description', 'Description mise à jour pour le certificat.');

        // 2. Basculer le statut
        $toggleRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->patchJson('/api/v1/modeles-documents/' . $modele->uuid . '/toggle-status');

        $toggleRes->assertStatus(200)
            ->assertJsonPath('data.statut', 'inactif');
    }

    public function test_cannot_delete_system_modele_document(): void
    {
        $systemModele = ModeleDocument::where('is_system', true)->first();

        $delRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/v1/modeles-documents/' . $systemModele->uuid);

        $delRes->assertStatus(422)
            ->assertJsonPath('status', 'error');
    }

    public function test_can_get_system_variables(): void
    {
        $res = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/modeles-documents/variables-systeme');

        $res->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertNotEmpty($res->json('data'));
    }

    public function test_can_generer_document_officiel_for_catechumene(): void
    {
        $modele = ModeleDocument::where('code', 'CERT_BAPTEME')->first();
        $annee = AnneeCatechese::first();

        $cat = Catechumene::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'matricule'                 => 'CAT-2026-9901',
            'nom'                       => 'YAO',
            'prenoms'                   => 'Kouassi Emmanuel',
            'date_naissance'            => '2015-05-12',
            'lieu_naissance'            => 'Bouaké',
            'sexe'                      => 'M',
            'date_bapteme'              => '2024-04-06',
            'lieu_bapteme'              => 'Cathédrale Saint-Paul',
            'statut'                    => 'actif',
        ]);

        $res = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/documents-generes', [
                'modele_document_id' => $modele->uuid,
                'catechumene_id'     => $cat->uuid,
                'annee_catechese_id' => $annee->uuid,
            ]);

        $res->assertStatus(201)
            ->assertJsonPath('status', 'success');

        $docUuid = $res->json('data.id');
        $this->assertNotNull($docUuid);
        $this->assertStringContainsString('YAO Kouassi Emmanuel', $res->json('data.contenu'));

        // Voir le document généré
        $showRes = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/documents-generes/' . $docUuid);

        $showRes->assertStatus(200)
            ->assertJsonPath('data.catechumene.matricule', 'CAT-2026-9901');
    }

    public function test_can_generer_documents_en_masse(): void
    {
        $modele = ModeleDocument::where('code', 'ATTEST_CATECHESE')->first();
        $annee = AnneeCatechese::first();
        $niveau = Niveau::first();
        $classe = Classe::first();

        $cat1 = Catechumene::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'matricule'                 => 'CAT-2026-7701',
            'nom'                       => 'KOUADIO',
            'prenoms'                   => 'Aya Sarah',
            'date_naissance'            => '2016-03-15',
            'sexe'                      => 'F',
            'statut'                    => 'actif',
        ]);

        $cat2 = Catechumene::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'matricule'                 => 'CAT-2026-7702',
            'nom'                       => 'N\'GUESSAN',
            'prenoms'                   => 'Koffi Paul',
            'date_naissance'            => '2016-08-20',
            'sexe'                      => 'M',
            'statut'                    => 'actif',
        ]);

        InscriptionAnnuelle::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'catechumene_id'            => $cat1->id,
            'annee_catechese_id'        => $annee->id,
            'niveau_id'                 => $niveau->id,
            'classe_id'                 => $classe->id,
            'statut_inscription'        => 'validee',
        ]);

        InscriptionAnnuelle::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'catechumene_id'            => $cat2->id,
            'annee_catechese_id'        => $annee->id,
            'niveau_id'                 => $niveau->id,
            'classe_id'                 => $classe->id,
            'statut_inscription'        => 'validee',
        ]);

        $res = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/documents-generes/masse', [
                'modele_document_id' => $modele->uuid,
                'classe_id'          => $classe->uuid,
                'annee_catechese_id' => $annee->uuid,
            ]);

        $res->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertGreaterThanOrEqual(2, $res->json('count'));
    }

    public function test_can_delete_document_genere(): void
    {
        $doc = DocumentGenere::create([
            'paroisse_configuration_id' => $this->paroisse->id,
            'reference_document'        => 'DOC-TEST-001',
            'titre'                     => 'Attestation Test',
            'type_document'             => 'attestation',
            'date_generation'           => now()->toDateString(),
            'statut'                    => 'valide',
        ]);

        $res = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->deleteJson('/api/v1/documents-generes/' . $doc->uuid);

        $res->assertStatus(200)
            ->assertJsonPath('status', 'success');

        $this->assertSoftDeleted('documents_generes', ['id' => $doc->id]);
    }
}
