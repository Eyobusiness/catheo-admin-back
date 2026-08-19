<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulletins_trimestriels', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')->constrained('paroisse_configurations')->cascadeOnDelete();
            $table->foreignId('inscription_annuelle_id')->constrained('inscriptions_annuelles')->cascadeOnDelete();
            $table->foreignId('module_trimestriel_id')->constrained('modules_trimestriels')->cascadeOnDelete();
            $table->decimal('moyenne_trimestrielle', 4, 2)->nullable();
            $table->integer('rang')->nullable();
            $table->integer('assiduite_total_absences')->default(0);
            $table->text('appreciation_generale')->nullable();
            $table->enum('statut', ['brouillon', 'valide', 'cloture'])->default('brouillon');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['paroisse_configuration_id', 'inscription_annuelle_id', 'module_trimestriel_id'], 'bulletins_paroisse_insc_mod_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulletins_trimestriels');
    }
};
