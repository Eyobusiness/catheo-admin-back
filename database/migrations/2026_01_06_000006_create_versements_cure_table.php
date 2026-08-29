<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('versements', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')->constrained('paroisse_configurations')->cascadeOnDelete();
            $table->foreignId('annee_catechese_id')->constrained('annee_catecheses')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference'); // VRS-2026-001
            $table->string('periode_concernee'); // ex: Juin 2026
            $table->decimal('montant_verse', 12, 2);
            $table->string('mode_remise')->default('especes');
            $table->string('effectue_par')->nullable();
            $table->string('destinataire')->nullable();
            $table->string('statut')->default('valide');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['paroisse_configuration_id', 'statut'], 'versements_paroisse_statut_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('versements');
    }
};
