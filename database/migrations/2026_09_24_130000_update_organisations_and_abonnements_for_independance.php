<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $driver = DB::getDriverName();

        // 1. Mise à jour de la table `organisations`
        Schema::table('organisations', function (Blueprint $table) {
            if (!Schema::hasColumn('organisations', 'mode')) {
                $table->string('mode', 20)->default('liee')->after('type_organisation'); // 'liee' ou 'independant'
            }
        });

        // Rendre paroisse_configuration_id nullable dans organisations
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `organisations` MODIFY `paroisse_configuration_id` BIGINT UNSIGNED NULL");
        } else {
            Schema::table('organisations', function (Blueprint $table) {
                $table->unsignedBigInteger('paroisse_configuration_id')->nullable()->change();
            });
            // Re-créer l'index partiel SQLite avec la clause WHERE deleted_at IS NULL qui saute lors de la recréation de table
            DB::statement("DROP INDEX IF EXISTS organisations_paroisse_type_active_unique");
            DB::statement("CREATE UNIQUE INDEX organisations_paroisse_type_active_unique ON organisations(paroisse_configuration_id, type_organisation) WHERE deleted_at IS NULL");
        }

        // 2. Mise à jour de la table `abonnements`
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `abonnements` MODIFY `paroisse_configuration_id` BIGINT UNSIGNED NULL");
        } else {
            Schema::table('abonnements', function (Blueprint $table) {
                $table->unsignedBigInteger('paroisse_configuration_id')->nullable()->change();
            });
        }

        Schema::table('abonnements', function (Blueprint $table) {
            if (!Schema::hasColumn('abonnements', 'organisation_id')) {
                $table->foreignId('organisation_id')
                    ->nullable()
                    ->after('paroisse_configuration_id')
                    ->constrained('organisations')
                    ->nullOnDelete();

                $table->index(['organisation_id', 'statut'], 'abos_organisation_statut_idx');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        Schema::table('abonnements', function (Blueprint $table) {
            if (Schema::hasColumn('abonnements', 'organisation_id')) {
                $table->dropForeign(['organisation_id']);
                $table->dropIndex('abos_organisation_statut_idx');
                $table->dropColumn('organisation_id');
            }
        });

        Schema::table('organisations', function (Blueprint $table) {
            if (Schema::hasColumn('organisations', 'mode')) {
                $table->dropColumn('mode');
            }
        });
    }
};
