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
        Schema::create('sauvegardes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')
                ->constrained('paroisse_configurations')
                ->cascadeOnDelete();
            $table->string('nom_fichier');
            $table->string('chemin_fichier');
            $table->unsignedBigInteger('taille_octets')->default(0);
            $table->string('cree_par')->default('Système (Auto)');
            $table->string('type')->default('manuel'); // manuel, automatique
            $table->string('statut')->default('termine'); // termine, en_cours, echec
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sauvegardes');
    }
};
