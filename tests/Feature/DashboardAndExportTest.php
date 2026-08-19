<?php

namespace Tests\Feature;

use App\Models\ParoisseConfiguration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardAndExportTest extends TestCase
{
    use RefreshDatabase;

    protected User $adminUser;
    protected ParoisseConfiguration $paroisse;
    protected string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\InitialSetupSeeder::class);

        $this->adminUser = User::where('email', 'admin.stpaul@catheo.ci')->first();
        $this->paroisse = ParoisseConfiguration::where('code_paroisse', 'PAR-STPAUL-01')->first();
        $this->token = $this->adminUser->createToken('TestDevice')->plainTextToken;
    }

    /**
     * Test du résumé complet du Tableau de Bord (Dashboard UI Angular).
     */
    public function test_can_get_dashboard_summary(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/dashboard/summary');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'kpis' => [
                        'total_catechumenes',
                        'total_sections',
                        'total_classes',
                        'total_animateurs',
                        'preinscriptions_en_attente',
                    ],
                    'repartition_sections',
                    'repartition_niveaux',
                    'situation_financiere' => [
                        'montant_attendu',
                        'montant_encaisse',
                        'reste_a_payer',
                        'taux_recouvrement',
                    ],
                    'effectifs_classes',
                    'preparation_sacrements' => [
                        'bapteme_candidats',
                        'premiere_communion_candidats',
                        'confirmation_candidats',
                    ],
                    'alertes' => [
                        'preinscriptions_non_validees',
                        'paiements_en_retard',
                        'catechumenes_non_affectes',
                        'documents_manquants',
                    ],
                    'activites_recentes',
                ],
            ]);
    }

    /**
     * Test de récupération des KPI principaux du Tableau de Bord.
     */
    public function test_can_get_dashboard_kpis(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->getJson('/api/v1/dashboard/kpis');

        $response->assertStatus(200);
    }

    /**
     * Test d'exportation de la liste des catéchumènes en CSV.
     */
    public function test_can_export_catechumenes_csv(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/exports/catechumenes', [
                'format' => 'csv',
            ]);

        $response->assertStatus(200)
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    /**
     * Test d'exportation de la liste des catéchumènes en PDF.
     */
    public function test_can_export_catechumenes_pdf(): void
    {
        $response = $this->withHeader('Authorization', 'Bearer ' . $this->token)
            ->postJson('/api/v1/exports/catechumenes', [
                'format' => 'pdf',
            ]);

        $response->assertStatus(200)
            ->assertHeader('content-type', 'text/html; charset=UTF-8');
    }
}
