<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modeles_documents', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')->nullable()->constrained('paroisse_configurations')->cascadeOnDelete();
            $table->string('titre'); // ex: Certificat de Baptême
            $table->string('code')->nullable(); // ex: CERT_BAPTEME
            $table->string('type_document')->default('certificat'); // certificat, attestation, convocation, carte, fiche, autre
            $table->text('description')->nullable();
            $table->longText('contenu')->nullable(); // HTML Template with {{variables}}
            $table->json('variables_disponibles')->nullable();
            $table->string('signature_nom')->nullable();
            $table->string('signature_titre')->nullable();
            $table->string('statut')->default('actif'); // actif, inactif
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->uuid('deleted_by')->nullable();

            $table->index(['paroisse_configuration_id', 'type_document'], 'modeles_doc_paroisse_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modeles_documents');
    }
};
