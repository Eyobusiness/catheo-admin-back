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
        Schema::create('action_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_uuid')->nullable()->index();
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();
            $table->string('profil')->nullable();
            $table->foreignId('paroisse_configuration_id')->nullable()->constrained('paroisse_configurations')->nullOnDelete();
            $table->foreignId('organisation_id')->nullable()->constrained('organisations')->nullOnDelete();
            $table->string('action', 100)->index(); // login, logout, create, update, delete, restore, force_delete, status_change, etc.
            $table->string('module', 100)->index(); // Paroisse, Organisation, Utilisateur, Produit, Formule, Abonnement, etc.
            $table->unsignedBigInteger('entite_id')->nullable();
            $table->string('entite_uuid')->nullable()->index();
            $table->text('description')->nullable();
            $table->json('anciennes_valeurs')->nullable();
            $table->json('nouvelles_valeurs')->nullable();
            $table->string('ip_address', 50)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['created_at', 'action', 'module'], 'action_audit_created_action_module_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('action_audit_logs');
    }
};
