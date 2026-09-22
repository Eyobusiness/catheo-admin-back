<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campagne_pelerinages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('organisation_id')->constrained('organisations')->onDelete('cascade');
            $table->foreignId('activite_id')->nullable()->constrained('activites')->onDelete('set null');

            $table->string('code')->index();
            $table->string('nom');
            $table->text('description')->nullable();
            $table->string('lieu_depart');
            $table->string('destination');
            $table->date('date_depart');
            $table->time('heure_depart')->nullable();
            $table->date('date_fin'); // Règle stricte: date_fin (pas de date_retour)
            $table->time('heure_fin')->nullable(); // Règle stricte: heure_fin (pas de heure_retour)

            $table->date('date_debut_inscription')->nullable();
            $table->date('date_fin_inscription')->nullable();
            $table->unsignedInteger('capacite')->nullable();

            $table->string('statut')->default('brouillon'); // brouillon, ouverte, cloturee, annulee, terminee
            $table->text('observation')->nullable();

            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->string('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organisation_id', 'statut']);
            $table->index(['organisation_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campagne_pelerinages');
    }
};
