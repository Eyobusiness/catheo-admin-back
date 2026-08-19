<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')->constrained('paroisse_configurations')->cascadeOnDelete();
            $table->foreignId('annee_catechese_id')->constrained('annee_catecheses')->cascadeOnDelete();
            $table->foreignId('inscription_annuelle_id')->nullable()->constrained('inscriptions_annuelles')->nullOnDelete();
            $table->foreignId('catechumene_id')->nullable()->constrained('catechumenes')->nullOnDelete();
            $table->string('numero_recu');
            $table->decimal('montant_total', 12, 2);
            $table->enum('mode_paiement', ['especes', 'mobile_money', 'cheque', 'virement'])->default('especes');
            $table->string('reference_transaction')->nullable();
            $table->date('date_paiement');
            $table->enum('statut', ['valide', 'annule'])->default('valide');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['paroisse_configuration_id', 'numero_recu'], 'paiements_paroisse_recu_unique');
            $table->index(['paroisse_configuration_id', 'date_paiement'], 'paiements_paroisse_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements');
    }
};
