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
        Schema::create('organisations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')
                ->constrained('paroisse_configurations')
                ->cascadeOnDelete();
            $table->foreignId('produit_id')
                ->constrained('produits')
                ->restrictOnDelete();
            $table->string('type_organisation', 50); // OPPE, OPPJ, OPPA
            $table->string('code', 100)->nullable();
            $table->string('nom');
            $table->text('description')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->text('adresse')->nullable();
            $table->string('responsable_nom')->nullable();
            $table->string('responsable_telephone')->nullable();
            $table->string('responsable_email')->nullable();
            $table->string('statut', 50)->default('actif');
            $table->date('date_activation')->nullable();
            $table->date('date_desactivation')->nullable();
            $table->timestamps();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->softDeletes();

            $table->index(['paroisse_configuration_id', 'statut'], 'org_paroisse_statut_idx');
        });

        // Contrainte d'unicite (paroisse_configuration_id, type_organisation) compatible avec SoftDeletes
        // Permet la re-creation apres suppression logique sans conflit MySQL 1062
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `organisations` ADD COLUMN `active_unique_key` VARCHAR(150) GENERATED ALWAYS AS (IF(`deleted_at` IS NULL, CONCAT(`paroisse_configuration_id`, '_', `type_organisation`), NULL)) VIRTUAL");
            DB::statement("ALTER TABLE `organisations` ADD UNIQUE INDEX `organisations_paroisse_type_active_unique` (`active_unique_key`)");
        } else {
            // Compatibilité SQLite (tests)
            DB::statement("CREATE UNIQUE INDEX organisations_paroisse_type_active_unique ON organisations(paroisse_configuration_id, type_organisation) WHERE deleted_at IS NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organisations');
    }
};
