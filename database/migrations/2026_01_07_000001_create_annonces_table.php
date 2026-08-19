<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annonces', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')->constrained('paroisse_configurations')->cascadeOnDelete();
            $table->foreignId('annee_catechese_id')->constrained('annee_catecheses')->cascadeOnDelete();
            $table->string('titre');
            $table->text('contenu');
            $table->enum('cible', ['tous', 'parents', 'animateurs', 'section', 'niveau', 'classe'])->default('tous');
            $table->foreignId('section_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->foreignId('niveau_id')->nullable()->constrained('niveaux')->nullOnDelete();
            $table->foreignId('classe_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->date('date_publication');
            $table->date('date_expiration')->nullable();
            $table->enum('statut', ['brouillon', 'publiee', 'archivee'])->default('publiee');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['paroisse_configuration_id', 'date_publication', 'statut'], 'annonces_paroisse_pub_statut_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('annonces');
    }
};
