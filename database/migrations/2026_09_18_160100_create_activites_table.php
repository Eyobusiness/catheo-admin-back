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
        Schema::create('activites', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->constrained('organisations')->cascadeOnDelete();
            $table->string('code', 50)->nullable();
            $table->string('titre', 255);
            $table->text('description')->nullable();
            $table->string('type_activite', 100)->nullable(); // ex: pelerinage, recollection, kermesse, formation, sortie, celebration
            $table->dateTime('date_debut');
            $table->dateTime('date_fin')->nullable();
            $table->string('lieu', 255)->nullable();
            $table->foreignId('responsable_id')->nullable()->constrained('membres')->nullOnDelete();
            $table->string('statut', 30)->default('brouillon'); // brouillon, planifiee, en_cours, terminee, annulee
            $table->decimal('taux_execution', 5, 2)->default(0.00); // 0.00% à 100.00%
            $table->text('observation')->nullable();

            // Audit & SoftDeletes
            $table->timestamps();
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->softDeletes();

            $table->index(['organisation_id', 'statut']);
            $table->index(['date_debut', 'date_fin']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('activites');
    }
};
