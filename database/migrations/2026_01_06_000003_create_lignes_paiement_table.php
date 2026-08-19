<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lignes_paiement', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')->constrained('paroisse_configurations')->cascadeOnDelete();
            $table->foreignId('paiement_id')->constrained('paiements')->cascadeOnDelete();
            $table->foreignId('tarif_id')->nullable()->constrained('tarifs')->nullOnDelete();
            $table->string('designation');
            $table->decimal('montant', 12, 2);
            $table->integer('quantite')->default(1);
            $table->decimal('sous_total', 12, 2);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['paroisse_configuration_id', 'paiement_id'], 'lignes_paroisse_paiement_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lignes_paiement');
    }
};
