<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campagnes_preinscriptions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')->constrained('paroisse_configurations')->cascadeOnDelete();
            $table->foreignId('annee_catechese_id')->constrained('annee_catecheses')->cascadeOnDelete();
            $table->string('titre');
            $table->date('date_debut');
            $table->date('date_fin');
            $table->enum('statut', ['ouverte', 'fermee', 'suspendue'])->default('ouverte');
            $table->text('description')->nullable();
            $table->json('sections_autorisees')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['paroisse_configuration_id', 'annee_catechese_id'], 'campagnes_paroisse_annee_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campagnes_preinscriptions');
    }
};
