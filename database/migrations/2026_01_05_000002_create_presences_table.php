<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presences', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')->constrained('paroisse_configurations')->cascadeOnDelete();
            $table->foreignId('seance_id')->constrained('seances')->cascadeOnDelete();
            $table->foreignId('catechumene_id')->constrained('catechumenes')->cascadeOnDelete();
            $table->enum('statut_presence', ['present', 'absent', 'retard', 'excuse'])->default('present');
            $table->string('motif_absence')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['paroisse_configuration_id', 'seance_id', 'catechumene_id'], 'presences_paroisse_seance_cat_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presences');
    }
};
