<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tarif_pelerinages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('campagne_pelerinage_id')->constrained('campagne_pelerinages')->onDelete('cascade');

            $table->string('code')->index();
            $table->string('libelle');
            $table->text('description')->nullable();
            $table->decimal('montant', 12, 2);
            $table->string('devise', 3)->default('XOF');
            $table->string('statut')->default('actif'); // actif, inactif

            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['campagne_pelerinage_id', 'statut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tarif_pelerinages');
    }
};
