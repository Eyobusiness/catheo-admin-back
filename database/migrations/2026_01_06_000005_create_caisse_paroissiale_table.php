<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('caisse_paroissiale', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')->constrained('paroisse_configurations')->cascadeOnDelete();
            $table->foreignId('annee_catechese_id')->constrained('annee_catecheses')->cascadeOnDelete();
            $table->enum('type_mouvement', ['entree', 'sortie', 'recette', 'depense', 'remboursement']);
            $table->enum('categorie', ['inscription', 'don', 'cotisation', 'depense_fournitures', 'depense_evenement', 'remboursement', 'autre'])->default('inscription');
            $table->decimal('montant', 12, 2);
            $table->string('reference_document')->nullable();
            $table->string('libelle');
            $table->date('date_mouvement');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['paroisse_configuration_id', 'date_mouvement', 'type_mouvement'], 'caisse_paroisse_date_type_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('caisse_paroissiale');
    }
};
