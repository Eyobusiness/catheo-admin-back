<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications_log', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')->constrained('paroisse_configurations')->cascadeOnDelete();
            $table->enum('canal', ['sms', 'email', 'in_app'])->default('sms');
            $table->string('destinataire');
            $table->string('sujet')->nullable();
            $table->text('message');
            $table->enum('statut_envoi', ['en_attente', 'envoye', 'echec'])->default('en_attente');
            $table->text('erreur_message')->nullable();
            $table->timestamp('date_envoi')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['paroisse_configuration_id', 'canal', 'statut_envoi'], 'notifs_paroisse_canal_statut_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_log');
    }
};
