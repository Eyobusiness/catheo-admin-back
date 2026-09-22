<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscription_pelerinages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('campagne_pelerinage_id')->constrained('campagne_pelerinages')->onDelete('cascade');
            $table->foreignId('tarif_pelerinage_id')->constrained('tarif_pelerinages')->onDelete('restrict');
            $table->foreignId('catechumene_id')->nullable()->constrained('catechumenes')->onDelete('set null');

            $table->string('type_participant')->default('CATECHUMENE'); // CATECHUMENE, EXTERNE
            $table->string('reference')->unique();

            // Snapshot des données d'identité du participant
            $table->string('nom');
            $table->string('prenoms');
            $table->string('sexe', 1)->default('M'); // M, F
            $table->string('taille', 10)->nullable(); // M, S, X, L, XL, XXL, XXXL
            $table->date('date_naissance')->nullable();
            $table->string('telephone')->nullable();
            $table->string('email')->nullable();
            $table->string('adresse')->nullable();
            $table->string('contact_urgence_nom')->nullable();
            $table->string('contact_urgence_telephone')->nullable();

            // Montants et suivi des règlements
            $table->decimal('montant', 12, 2);
            $table->decimal('montant_paye', 12, 2)->default(0.00);
            $table->decimal('reste_a_payer', 12, 2)->default(0.00);

            // Statuts
            $table->string('statut_inscription')->default('en_attente'); // en_attente, partiellement_payee, payee, annulee
            $table->string('statut_participation')->default('prevue'); // prevue, presente, absente
            $table->timestamp('date_inscription')->useCurrent();

            // Badges et kits
            $table->boolean('badge_imprime')->default(false);
            $table->boolean('kit_remis')->default(false);
            $table->timestamp('date_remise_kit')->nullable();

            $table->text('observation')->nullable();

            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['campagne_pelerinage_id', 'statut_inscription'], 'idx_ins_pel_statut');
            $table->index(['campagne_pelerinage_id', 'catechumene_id'], 'idx_ins_pel_catechumene');
            $table->index(['campagne_pelerinage_id', 'statut_participation'], 'idx_ins_pel_participation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscription_pelerinages');
    }
};
