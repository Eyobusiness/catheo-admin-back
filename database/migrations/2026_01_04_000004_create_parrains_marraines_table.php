<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parrains_marraines', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')->constrained('paroisse_configurations')->cascadeOnDelete();
            $table->foreignId('catechumene_id')->constrained('catechumenes')->cascadeOnDelete();
            $table->enum('type', ['parrain', 'marraine']);
            $table->string('nom_prenoms');
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->string('domicile')->nullable();
            $table->string('paroisse_origine')->nullable();
            $table->string('representant_nom')->nullable();
            $table->string('representant_contact')->nullable();
            $table->boolean('sacrement_confirmation')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['paroisse_configuration_id', 'catechumene_id'], 'parrains_paroisse_catechumene_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parrains_marraines');
    }
};
