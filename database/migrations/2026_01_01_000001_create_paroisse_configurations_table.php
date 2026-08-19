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
        Schema::create('paroisse_configurations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nom');
            $table->string('code_paroisse')->unique();
            $table->string('diocese')->nullable();
            $table->string('doyenne')->nullable();
            $table->string('ville')->nullable();
            $table->string('commune')->nullable();
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->string('site_web')->nullable();
            $table->text('adresse')->nullable();
            $table->string('logo_path')->nullable();
            $table->string('cure_nom')->nullable();
            $table->string('coordination_nom')->default('Coordination de la Catéchèse');
            $table->string('statut')->default('actif');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paroisse_configurations');
    }
};
