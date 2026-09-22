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
        Schema::create('paiements_abonnement', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('echeance_abonnement_id')->constrained('echeances_abonnement')->cascadeOnDelete();
            $table->string('reference', 50)->unique();
            $table->decimal('montant', 12, 2);
            $table->string('devise', 10)->default('XOF');
            $table->string('mode_paiement', 30)->default('especes'); // especes, virement, mobile_money, cheque, autre
            $table->date('date_paiement');
            $table->string('statut', 30)->default('valide'); // en_attente, valide, annule, rembourse
            $table->string('reference_transaction')->nullable();
            $table->text('observation')->nullable();
            $table->timestamps();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->softDeletes();

            $table->index(['echeance_abonnement_id', 'statut'], 'pay_ech_statut_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paiements_abonnement');
    }
};
