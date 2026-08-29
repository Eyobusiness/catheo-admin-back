<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarifs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')->constrained('paroisse_configurations')->cascadeOnDelete();
            $table->foreignId('annee_catechese_id')->constrained('annee_catecheses')->cascadeOnDelete();
            $table->foreignId('niveau_id')->nullable()->constrained('niveaux')->nullOnDelete();
            $table->string('intitule');
            $table->string('description')->nullable();
            $table->decimal('montant', 12, 2);
            $table->date('periode_debut')->nullable();
            $table->date('periode_fin')->nullable();
            $table->boolean('est_obligatoire')->default(true);
            $table->string('type_tarif')->default('inscription');
            $table->string('statut')->default('actif');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['paroisse_configuration_id', 'annee_catechese_id'], 'tarifs_paroisse_annee_index');
        });

        Schema::create('tarif_niveau', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tarif_id')->constrained('tarifs')->cascadeOnDelete();
            $table->foreignId('niveau_id')->constrained('niveaux')->cascadeOnDelete();
            $table->unique(['tarif_id', 'niveau_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarif_niveau');
        Schema::dropIfExists('tarifs');
    }
};
