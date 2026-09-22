<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('regles_progression_pastorale')) {
            Schema::create('regles_progression_pastorale', function (Blueprint $table) {
                $table->id();
                $table->foreignId('paroisse_configuration_id')->nullable()->constrained('paroisse_configurations')->nullOnDelete();
                $table->string('code_section_source', 50);
                $table->string('niveau_source', 100);
                $table->string('decision', 50)->default('ADMIS');
                $table->string('code_section_destination', 50)->nullable();
                $table->string('niveau_destination', 100)->nullable();
                $table->boolean('est_fin_parcours')->default(false);
                $table->boolean('actif')->default(true);
                $table->integer('ordre_priorite')->default(0);
                $table->timestamps();

                $table->index(['paroisse_configuration_id', 'code_section_source', 'niveau_source', 'decision'], 'idx_regle_lookup');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regles_progression_pastorale');
    }
};