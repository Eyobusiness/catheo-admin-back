<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations_paiements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')->constrained('paroisse_configurations')->cascadeOnDelete();
            $table->foreignId('annee_catechese_id')->constrained('annee_catecheses')->cascadeOnDelete();
            $table->foreignId('catechumene_id')->nullable()->constrained('catechumenes')->nullOnDelete();
            $table->foreignId('tarif_id')->nullable()->constrained('tarifs')->nullOnDelete();
            $table->string('reference'); // OP-2026-001
            $table->string('libelle');
            $table->decimal('montant', 12, 2);
            $table->decimal('montant_paye', 12, 2)->default(0);
            $table->date('echeance')->nullable();
            $table->enum('statut', ['en_attente', 'partiellement_paye', 'paye', 'annule'])->default('en_attente');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['paroisse_configuration_id', 'statut'], 'op_paiements_paroisse_statut_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operations_paiements');
    }
};
