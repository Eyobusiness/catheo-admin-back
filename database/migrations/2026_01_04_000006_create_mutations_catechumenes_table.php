<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mutations_catechumenes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')->constrained('paroisse_configurations')->cascadeOnDelete();
            $table->foreignId('catechumene_id')->constrained('catechumenes')->cascadeOnDelete();
            $table->foreignId('annee_catechese_id')->constrained('annee_catecheses')->cascadeOnDelete();
            $table->string('paroisse_origine_nom');
            $table->string('paroisse_destination_nom');
            $table->text('motif')->nullable();
            $table->date('date_mutation');
            $table->enum('statut', ['demande', 'approuve', 'refuse'])->default('demande');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['paroisse_configuration_id', 'catechumene_id'], 'mutations_paroisse_catechumene_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mutations_catechumenes');
    }
};
