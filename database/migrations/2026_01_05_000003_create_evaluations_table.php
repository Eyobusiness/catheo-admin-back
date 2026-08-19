<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')->constrained('paroisse_configurations')->cascadeOnDelete();
            $table->foreignId('annee_catechese_id')->constrained('annee_catecheses')->cascadeOnDelete();
            $table->foreignId('module_trimestriel_id')->constrained('modules_trimestriels')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->string('titre');
            $table->text('description')->nullable();
            $table->string('type_eval')->default('interrogation'); // interrogation, composition, examen, oral, devoir, comportement
            $table->decimal('coefficient', 3, 1)->default(1.0);
            $table->decimal('note_max', 4, 2)->default(20.00);
            $table->date('date_evaluation');
            $table->string('statut')->default('actif'); // actif, inactif
            $table->timestamps();
            $table->softDeletes();

            $table->index(['paroisse_configuration_id', 'classe_id', 'module_trimestriel_id'], 'evals_paroisse_classe_module_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
