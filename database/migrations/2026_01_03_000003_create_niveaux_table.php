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
        Schema::create('niveaux', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')
                ->constrained('paroisse_configurations')
                ->cascadeOnDelete();
            $table->foreignId('section_id')
                ->constrained('sections')
                ->cascadeOnDelete();
            $table->string('nom');
            $table->string('code')->nullable();
            $table->text('description')->nullable();
            $table->string('statut')->default('actif'); // actif, inactif
            $table->integer('duree_annees')->default(1);
            $table->integer('ordre_affichage')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('niveaux');
    }
};
