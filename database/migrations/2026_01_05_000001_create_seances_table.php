<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seances', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')->constrained('paroisse_configurations')->cascadeOnDelete();
            $table->foreignId('annee_catechese_id')->constrained('annee_catecheses')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('module_trimestriel_id')->nullable()->constrained('modules_trimestriels')->nullOnDelete();
            $table->string('titre');
            $table->date('date_seance');
            $table->time('heure_debut')->nullable();
            $table->time('heure_fin')->nullable();
            $table->enum('statut', ['planifiee', 'effectuee', 'annulee'])->default('planifiee');
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['paroisse_configuration_id', 'classe_id', 'date_seance'], 'seances_paroisse_classe_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seances');
    }
};
