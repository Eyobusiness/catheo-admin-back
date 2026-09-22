<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiement_pelerinages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('inscription_pelerinage_id')->constrained('inscription_pelerinages')->onDelete('cascade');

            $table->string('reference')->unique();
            $table->decimal('montant', 12, 2);
            $table->string('devise', 3)->default('XOF');
            $table->string('mode_paiement')->default('especes'); // especes, wave, orange_money, mtn_money, moov_money, cheque, virement
            $table->timestamp('date_paiement')->useCurrent();
            $table->string('statut')->default('valide'); // valide, annule, rembourse
            $table->string('reference_transaction')->nullable();
            $table->text('observation')->nullable();

            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['inscription_pelerinage_id', 'statut'], 'idx_pai_pel_statut');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiement_pelerinages');
    }
};
