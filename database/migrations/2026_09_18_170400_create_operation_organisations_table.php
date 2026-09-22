<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operation_organisations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->constrained('organisations')->onDelete('cascade');
            $table->foreignId('campagne_pelerinage_id')->nullable()->constrained('campagne_pelerinages')->onDelete('set null');
            $table->foreignId('inscription_pelerinage_id')->nullable()->constrained('inscription_pelerinages')->onDelete('set null');
            $table->foreignId('paiement_pelerinage_id')->nullable()->constrained('paiement_pelerinages')->onDelete('set null');

            $table->string('reference')->unique();
            $table->string('type_operation')->default('entree'); // entree, sortie
            $table->decimal('montant', 12, 2);
            $table->string('devise', 3)->default('XOF');
            $table->string('libelle');
            $table->string('mode_reglement')->nullable();
            $table->timestamp('date_operation')->useCurrent();
            $table->string('statut')->default('valide'); // valide, annule

            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organisation_id', 'type_operation'], 'idx_op_org_type');
            $table->index(['organisation_id', 'date_operation'], 'idx_op_org_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operation_organisations');
    }
};
