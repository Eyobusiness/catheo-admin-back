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
        if (!Schema::hasTable('system_notifications')) {
            Schema::create('system_notifications', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('paroisse_configuration_id')->constrained('paroisse_configurations')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('role_destinataire', 50)->nullable()->default('ALL'); // ALL, ADMIN, ANIMATEUR, etc.
                
                // Classification
                $table->enum('type', ['alerte', 'activite', 'rappel', 'info'])->default('activite');
                $table->string('action', 50)->nullable(); // POST, PUT, DELETE, NOTE, PRESENCE, PAIEMENT, ALERTE
                $table->string('titre', 255);
                $table->text('message');
                
                // Polymorphic reference to source model
                $table->string('source_type', 100)->nullable(); // Catechumene, Paiement, Note, Presence, Classe, etc.
                $table->unsignedBigInteger('source_id')->nullable();
                $table->string('route_url', 255)->nullable(); // Frontend redirection path
                
                // Visual cues
                $table->string('icon', 50)->default('bell');
                $table->string('couleur', 50)->default('primary'); // primary, success, warning, danger, info
                
                // Extra metadata payload
                $table->json('donnees_additionnelles')->nullable();
                
                // Read tracking
                $table->boolean('is_read')->default(false);
                $table->timestamp('read_at')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                
                $table->timestamps();

                $table->index(['paroisse_configuration_id', 'type', 'is_read'], 'sys_notif_paroisse_type_read_idx');
                $table->index(['paroisse_configuration_id', 'created_at'], 'sys_notif_paroisse_created_idx');
                $table->index(['source_type', 'source_id'], 'sys_notif_source_idx');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_notifications');
    }
};
