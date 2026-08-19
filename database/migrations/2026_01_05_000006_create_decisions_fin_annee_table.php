<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('decisions_fin_annee', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')->constrained('paroisse_configurations')->cascadeOnDelete();
            $table->foreignId('inscription_annuelle_id')->constrained('inscriptions_annuelles')->cascadeOnDelete();
            $table->decimal('moyenne_annuelle', 4, 2)->nullable();
            $table->enum('decision', ['admis', 'redouble', 'exclu', 'sacrement_valide'])->default('admis');
            $table->string('mention')->nullable();
            $table->boolean('sacrement_recu')->default(false);
            $table->date('date_decision');
            $table->text('observations')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['paroisse_configuration_id', 'inscription_annuelle_id'], 'decisions_paroisse_inscription_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('decisions_fin_annee');
    }
};
