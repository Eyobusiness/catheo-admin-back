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
        Schema::create('apparence_configurations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')
                ->constrained('paroisse_configurations')
                ->cascadeOnDelete();
            $table->string('couleur_principale')->default('#4F46E5');
            $table->string('couleur_secondaire')->default('#D97706');
            $table->string('police_caracteres')->default('Inter');
            $table->string('logo_url')->nullable();
            $table->text('entete_document')->nullable();
            $table->text('pied_page_document')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('apparence_configurations');
    }
};
