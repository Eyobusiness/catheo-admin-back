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
        Schema::create('abonnements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')->constrained('paroisse_configurations')->cascadeOnDelete();
            $table->foreignId('formule_id')->constrained('formules')->restrictOnDelete();
            $table->string('reference', 50)->unique();
            $table->date('date_debut');
            $table->date('date_fin')->nullable();
            $table->string('statut', 30)->default('en_attente'); // en_attente, actif, suspendu, expire, resilie
            $table->decimal('montant', 12, 2); // Snapshot montant formule
            $table->string('devise', 10)->default('XOF');
            $table->boolean('renouvellement_automatique')->default(true);
            $table->date('date_resiliation')->nullable();
            $table->text('motif_resiliation')->nullable();
            $table->text('observation')->nullable();
            $table->timestamps();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->softDeletes();

            $table->index(['paroisse_configuration_id', 'statut'], 'abos_paroisse_statut_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('abonnements');
    }
};
