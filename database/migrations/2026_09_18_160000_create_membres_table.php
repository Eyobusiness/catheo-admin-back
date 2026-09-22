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
        Schema::create('membres', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('nom', 100);
            $table->string('prenoms', 150);
            $table->string('sexe', 10)->default('M'); // M ou F
            $table->date('date_naissance')->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('quartier', 150)->nullable();
            $table->text('adresse')->nullable();
            $table->string('fonction', 100)->nullable(); // ex: Membre, Trésorier, Secrétaire, Animateur
            $table->date('date_entree')->nullable();
            $table->string('statut', 30)->default('actif'); // actif, inactif, suspendu
            $table->string('photo_path', 255)->nullable();
            $table->text('observation')->nullable();

            // Audit & SoftDeletes
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();

            $table->index(['organisation_id', 'statut']);
            $table->index(['nom', 'prenoms']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('membres');
    }
};
