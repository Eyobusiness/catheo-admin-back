<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catechumenes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')->constrained('paroisse_configurations')->cascadeOnDelete();
            $table->foreignId('ceb_id')->nullable()->constrained('cebs')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('matricule'); // Matricule unique par paroisse
            
            // Fiche Identité
            $table->string('nom');
            $table->string('prenoms');
            $table->enum('sexe', ['M', 'F']);
            $table->date('date_naissance');
            $table->string('lieu_naissance')->nullable();
            $table->string('adresse')->nullable();
            $table->string('domicile')->nullable();
            $table->string('profession')->nullable();
            $table->string('classe_scolaire')->nullable();
            $table->string('telephone')->nullable();
            $table->string('photo_path')->nullable();

            // Informations Parents / Tuteur
            $table->string('nom_pere')->nullable();
            $table->string('origine_pere')->nullable();
            $table->string('telephone_pere')->nullable();
            $table->string('nom_mere')->nullable();
            $table->string('origine_mere')->nullable();
            $table->string('telephone_mere')->nullable();
            $table->string('nom_tuteur')->nullable();
            $table->string('telephone_tuteur')->nullable();

            // Baptême & Sacrements
            $table->boolean('est_baptise')->default(false);
            $table->string('num_carnet_bapteme')->nullable();
            $table->date('date_bapteme')->nullable();
            $table->string('lieu_bapteme')->nullable();
            $table->string('diocese_bapteme')->nullable();
            $table->string('ville_bapteme')->nullable();
            $table->string('paroisse_bapteme')->nullable();
            $table->date('date_premiere_communion')->nullable();
            $table->string('paroisse_premiere_communion')->nullable();
            $table->date('date_confirmation')->nullable();
            $table->string('paroisse_confirmation')->nullable();
            $table->string('ministre_confirmation')->nullable();

            $table->enum('statut', ['actif', 'abandon', 'transfere', 'complete'])->default('actif');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['paroisse_configuration_id', 'matricule'], 'catechumenes_paroisse_code_unique');
            $table->index(['paroisse_configuration_id', 'nom', 'prenoms'], 'catechumenes_paroisse_nom_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catechumenes');
    }
};
