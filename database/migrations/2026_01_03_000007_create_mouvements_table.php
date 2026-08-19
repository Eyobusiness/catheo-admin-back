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
        Schema::create('mouvements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')
                ->constrained('paroisse_configurations')
                ->cascadeOnDelete();
            $table->string('nom', 150);
            $table->string('code')->nullable();
            $table->string('responsable', 150)->nullable();
            $table->string('telephone', 20)->nullable();
            $table->text('description')->nullable();
            $table->string('statut', 20)->default('Active'); // Active, Inactive
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['paroisse_configuration_id', 'nom'], 'mouvements_paroisse_nom_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mouvements');
    }
};
