<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscriptions_annuelles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')->constrained('paroisse_configurations')->cascadeOnDelete();
            $table->foreignId('catechumene_id')->constrained('catechumenes')->cascadeOnDelete();
            $table->foreignId('annee_catechese_id')->constrained('annee_catecheses')->cascadeOnDelete();
            $table->foreignId('niveau_id')->constrained('niveaux')->cascadeOnDelete();
            $table->foreignId('classe_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('ceb_id')->nullable()->constrained('cebs')->nullOnDelete();
            $table->foreignId('mouvement_id')->nullable()->constrained('mouvements')->nullOnDelete();
            $table->string('code_inscription')->nullable();
            $table->date('date_inscription');
            $table->enum('statut_inscription', ['inscrit', 'valide', 'en_attente', 'abandon'])->default('valide');
            $table->boolean('frais_inscription_payes')->default(false);
            $table->text('observation')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['paroisse_configuration_id', 'annee_catechese_id', 'catechumene_id'], 'inscriptions_paroisse_annee_catechumene_unique');
            $table->index(['paroisse_configuration_id', 'annee_catechese_id', 'niveau_id'], 'inscriptions_paroisse_annee_niveau_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscriptions_annuelles');
    }
};
