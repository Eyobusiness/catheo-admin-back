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
        Schema::create('factures', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('echeance_abonnement_id')->constrained('echeances_abonnement')->cascadeOnDelete();
            $table->string('reference', 50)->unique();
            $table->date('date_facture');
            $table->date('date_echeance');
            $table->decimal('montant_ht', 12, 2)->default(0.00);
            $table->decimal('taux_tva', 5, 2)->default(0.00);
            $table->decimal('montant_tva', 12, 2)->default(0.00);
            $table->decimal('montant_total', 12, 2);
            $table->string('devise', 10)->default('XOF');
            $table->string('statut', 30)->default('en_attente'); // en_attente, payee, annulee
            $table->text('description')->nullable();
            $table->text('observation')->nullable();
            $table->timestamps();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->softDeletes();

            $table->index(['echeance_abonnement_id', 'statut'], 'factures_ech_statut_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('factures');
    }
};
