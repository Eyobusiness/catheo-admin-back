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
        Schema::create('modules_trimestriels', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')
                ->constrained('paroisse_configurations')
                ->cascadeOnDelete();
            $table->foreignId('annee_catechese_id')
                ->constrained('annee_catecheses')
                ->cascadeOnDelete();
            $table->string('nom');
            $table->integer('numero_trimestre')->default(1);
            $table->date('date_debut')->nullable();
            $table->date('date_fin')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('modules_trimestriels');
    }
};
