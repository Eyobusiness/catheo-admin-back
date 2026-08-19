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
        Schema::create('affectations_animateurs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')
                ->constrained('paroisse_configurations')
                ->cascadeOnDelete();
            $table->foreignId('animateur_id')
                ->constrained('animateurs')
                ->cascadeOnDelete();
            $table->foreignId('annee_catechese_id')
                ->constrained('annee_catecheses')
                ->cascadeOnDelete();
            $table->foreignId('classe_id')
                ->nullable()
                ->constrained('classes')
                ->cascadeOnDelete();
            $table->string('role_animateur')->default('principal');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affectations_animateurs');
    }
};
