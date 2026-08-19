<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('preinscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')->constrained('paroisse_configurations')->cascadeOnDelete();
            $table->foreignId('campagne_preinscription_id')->constrained('campagnes_preinscriptions')->cascadeOnDelete();
            $table->foreignId('annee_catechese_id')->constrained('annee_catecheses')->cascadeOnDelete();
            $table->foreignId('section_souhaite_id')->nullable()->constrained('sections')->nullOnDelete();
            $table->foreignId('niveau_souhaite_id')->nullable()->constrained('niveaux')->nullOnDelete();
            $table->string('code_dossier')->unique();
            $table->enum('type_demande', ['nouvelle_inscription', 'reinscription'])->default('nouvelle_inscription');
            
            // Fiche Identité
            $table->string('nom');
            $table->string('prenoms');
            $table->enum('sexe', ['M', 'F']);
            $table->date('date_naissance');
            $table->string('lieu_naissance')->nullable();
            $table->string('adresse')->nullable();
            $table->string('telephone')->nullable();    
            $table->string('photo_url')->nullable();

            // Informations Parents / Tuteur
            $table->string('nom_pere')->nullable();
            $table->string('telephone_pere')->nullable();
            $table->string('nom_mere')->nullable();
            $table->string('telephone_mere')->nullable();
            $table->string('nom_tuteur')->nullable();
            $table->string('telephone_tuteur')->nullable();
           

            // Baptême & Sacrements
            $table->boolean('est_baptise')->default(false);
            $table->date('date_bapteme')->nullable();
            $table->string('lieu_bapteme')->nullable();
            $table->string('paroisse_bapteme')->nullable();

            // Parrain / Marraine
            $table->string('nom_parrain')->nullable();
            $table->string('sexe_parrain')->nullable();
            $table->string('telephone_parrain')->nullable();

            // Documents joints
            $table->string('acte_naissance_url')->nullable();
            

            // Statut validation admin
            $table->enum('statut', ['en_attente', 'validee', 'rejetee'])->default('en_attente');
            $table->text('notes_validation')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['paroisse_configuration_id', 'statut'], 'preinscriptions_paroisse_statut_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preinscriptions');
    }
};
