<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activites', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')->constrained('paroisse_configurations')->cascadeOnDelete();
            $table->foreignId('annee_catechese_id')->constrained('annee_catecheses')->cascadeOnDelete();
            $table->foreignId('type_activite_id')->constrained('types_activites')->cascadeOnDelete();
            $table->string('titre');
            $table->text('description')->nullable();
            $table->string('lieu')->nullable();
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->time('heure_debut')->nullable();
            $table->time('heure_fin')->nullable();
            $table->enum('statut', ['planifiee', 'en_cours', 'terminee', 'annulee'])->default('planifiee');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['paroisse_configuration_id', 'annee_catechese_id', 'date_debut'], 'activites_paroisse_date_index');
        });

        // Table pivot Activité <-> Section
        Schema::create('activite_section', function (Blueprint $table) {
            $table->foreignId('activite_id')->constrained('activites')->cascadeOnDelete();
            $table->foreignId('section_id')->constrained('sections')->cascadeOnDelete();
            $table->primary(['activite_id', 'section_id']);
        });

        // Table pivot Activité <-> Niveau
        Schema::create('activite_niveau', function (Blueprint $table) {
            $table->foreignId('activite_id')->constrained('activites')->cascadeOnDelete();
            $table->foreignId('niveau_id')->constrained('niveaux')->cascadeOnDelete();
            $table->primary(['activite_id', 'niveau_id']);
        });

        // Table pivot Activité <-> Classe
        Schema::create('activite_classe', function (Blueprint $table) {
            $table->foreignId('activite_id')->constrained('activites')->cascadeOnDelete();
            $table->foreignId('classe_id')->constrained('classes')->cascadeOnDelete();
            $table->primary(['activite_id', 'classe_id']);
        });

        // Table pivot Activité <-> Animateur
        Schema::create('activite_animateur', function (Blueprint $table) {
            $table->foreignId('activite_id')->constrained('activites')->cascadeOnDelete();
            $table->foreignId('animateur_id')->constrained('animateurs')->cascadeOnDelete();
            $table->primary(['activite_id', 'animateur_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activite_animateur');
        Schema::dropIfExists('activite_classe');
        Schema::dropIfExists('activite_niveau');
        Schema::dropIfExists('activite_section');
        Schema::dropIfExists('activites');
    }
};
