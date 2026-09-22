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
        Schema::create('echeances_abonnement', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('abonnement_id')->constrained('abonnements')->cascadeOnDelete();
            $table->string('reference', 50)->unique();
            $table->date('periode_debut');
            $table->date('periode_fin');
            $table->date('date_echeance');
            $table->decimal('montant', 12, 2);
            $table->string('devise', 10)->default('XOF');
            $table->string('statut', 30)->default('en_attente'); // en_attente, payee, en_retard, annulee
            $table->text('observation')->nullable();
            $table->timestamps();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->softDeletes();

            $table->index(['abonnement_id', 'statut'], 'ech_abo_statut_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('echeances_abonnement');
    }
};
