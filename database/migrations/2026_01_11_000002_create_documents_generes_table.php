<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents_generes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')->nullable()->constrained('paroisse_configurations')->cascadeOnDelete();
            $table->foreignId('modele_document_id')->nullable()->constrained('modeles_documents')->nullOnDelete();
            $table->foreignId('catechumene_id')->nullable()->constrained('catechumenes')->nullOnDelete();
            $table->foreignId('annee_catechese_id')->nullable()->constrained('annee_catecheses')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference_document')->unique(); // DOC-2026-0001
            $table->string('titre'); // ex: Certificat de Baptême - KOUASSI Jean
            $table->string('type_document')->default('certificat');
            $table->longText('contenu')->nullable(); // Final rendered HTML
            $table->json('metadonnees')->nullable(); // Snapshot of tags/values
            $table->date('date_generation');
            $table->string('statut')->default('valide'); // valide, annule
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->index(['paroisse_configuration_id', 'type_document'], 'doc_gen_paroisse_type_idx');
            $table->index(['catechumene_id', 'date_generation'], 'doc_gen_cat_date_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents_generes');
    }
};
