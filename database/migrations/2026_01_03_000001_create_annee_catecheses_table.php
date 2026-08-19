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
        Schema::create('annee_catecheses', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')
                ->constrained('paroisse_configurations')
                ->cascadeOnDelete();
            $table->string('libelle');
            $table->date('date_debut');
            $table->date('date_fin');
            $table->boolean('est_active')->default(false);
            $table->string('statut')->default('preparation');
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['paroisse_configuration_id', 'libelle']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('annee_catecheses');
    }
};
