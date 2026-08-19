<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tables concernées par les colonnes d'audit système.
     */
    protected array $tables = [
        'paroisse_configurations',
        'profils',
        'users',
        'responsables_paroisse',
        'apparence_configurations',
        'annee_catecheses',
        'sections',
        'niveaux',
        'classes',
        'groupes',
        'cebs',
        'mouvements',
        'animateurs',
        'affectations_animateurs',
        'modules_trimestriels',
        'campagnes_preinscriptions',
        'preinscriptions',
        'catechumenes',
        'parrains_marraines',
        'inscriptions_annuelles',
        'mutations_catechumenes',
        'seances',
        'presences',
        'evaluations',
        'notes',
        'bulletins_trimestriels',
        'decisions_fin_annee',
        'tarifs',
        'paiements',
        'lignes_paiement',
        'dons_cotisations',
        'caisse_paroissiale',
        'operations_paiements',
        'versements_cure',
        'annonces',
        'notifications_log',
        'audit_logs',
        'types_activites',
        'activites',
        'calendriers',
        'sauvegardes',
    ];

    /**
     * Execution de l'ajout des colonnes audit.
     */
    public function up(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    if (!Schema::hasColumn($tableName, 'deleted_at')) {
                        $table->softDeletes();
                    }
                    if (!Schema::hasColumn($tableName, 'created_by')) {
                        $table->uuid('created_by')->nullable()->after('updated_at');
                    }
                    if (!Schema::hasColumn($tableName, 'updated_by')) {
                        $table->uuid('updated_by')->nullable()->after('created_by');
                    }
                    if (!Schema::hasColumn($tableName, 'deleted_by')) {
                        $table->uuid('deleted_by')->nullable()->after('updated_by');
                    }
                });
            }
        }
    }

    /**
     * Annulation de l'ajout des colonnes audit.
     */
    public function down(): void
    {
        foreach ($this->tables as $tableName) {
            if (Schema::hasTable($tableName)) {
                Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                    $columnsToDrop = [];
                    if (Schema::hasColumn($tableName, 'created_by')) {
                        $columnsToDrop[] = 'created_by';
                    }
                    if (Schema::hasColumn($tableName, 'updated_by')) {
                        $columnsToDrop[] = 'updated_by';
                    }
                    if (Schema::hasColumn($tableName, 'deleted_by')) {
                        $columnsToDrop[] = 'deleted_by';
                    }
                    if (!empty($columnsToDrop)) {
                        $table->dropColumn($columnsToDrop);
                    }
                });
            }
        }
    }
};
