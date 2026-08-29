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
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')
                ->constrained('paroisse_configurations')
                ->cascadeOnDelete();
            $table->foreignId('annee_catechese_id')
                ->constrained('annee_catecheses')
                ->cascadeOnDelete();
            $table->foreignId('niveau_id')
                ->constrained('niveaux')
                ->cascadeOnDelete();
            $table->string('nom');
            $table->integer('capacite_max')->default(30);
            $table->string('statut')->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['paroisse_configuration_id', 'annee_catechese_id', 'niveau_id', 'nom'], 'classes_paroisse_annee_niveau_nom_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
