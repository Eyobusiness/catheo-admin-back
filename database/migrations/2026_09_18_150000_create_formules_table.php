<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('formules', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('produit_id')->constrained('produits')->cascadeOnDelete();
            $table->string('code', 50); // STANDARD, PRO, PREMIUM, GRATUIT
            $table->string('nom');
            $table->text('description')->nullable();
            $table->string('periodicite', 20)->default('annuelle'); // mensuelle, annuelle
            $table->decimal('montant', 12, 2)->default(0.00);
            $table->string('devise', 10)->default('XOF');
            $table->boolean('est_gratuite')->default(false);
            $table->string('statut', 20)->default('actif');
            $table->integer('ordre')->default(0);
            $table->timestamps();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();
            $table->softDeletes();

            $table->index(['produit_id', 'statut'], 'formules_produit_statut_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('formules');
    }
};
