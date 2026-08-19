<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dons_cotisations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')->constrained('paroisse_configurations')->cascadeOnDelete();
            $table->foreignId('annee_catechese_id')->constrained('annee_catecheses')->cascadeOnDelete();
            $table->foreignId('mouvement_id')->nullable()->constrained('mouvements')->nullOnDelete();
            $table->foreignId('ceb_id')->nullable()->constrained('cebs')->nullOnDelete();
            $table->string('donateur_nom');
            $table->enum('type_don', ['don_especes', 'don_nature', 'cotisation_ceb', 'cotisation_mouvement'])->default('don_especes');
            $table->decimal('montant', 12, 2)->default(0.00);
            $table->text('description')->nullable();
            $table->date('date_reception');
            $table->string('numero_recu')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['paroisse_configuration_id', 'date_reception'], 'dons_paroisse_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dons_cotisations');
    }
};
