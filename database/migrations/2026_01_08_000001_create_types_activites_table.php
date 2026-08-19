<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('types_activites', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')->constrained('paroisse_configurations')->cascadeOnDelete();
            $table->string('nom');
            $table->string('code')->nullable();
            $table->string('couleur_agenda', 20)->default('#3B82F6');
            $table->text('description')->nullable();
            $table->enum('statut', ['actif', 'inactif'])->default('actif');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['paroisse_configuration_id', 'statut'], 'types_activites_paroisse_statut_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('types_activites');
    }
};
