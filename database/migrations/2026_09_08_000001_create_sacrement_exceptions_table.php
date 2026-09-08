<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sacrement_exceptions')) {
            Schema::create('sacrement_exceptions', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('paroisse_configuration_id')->index();
                $table->unsignedBigInteger('catechumene_id')->index();
                $table->unsignedBigInteger('sacrement_id')->index();
                $table->unsignedBigInteger('annee_catechese_id')->nullable()->index();
                $table->string('motif', 100);
                $table->string('autorise_par', 150)->nullable();
                $table->text('observation')->nullable();
                $table->date('date_derogation')->nullable();
                $table->string('statut', 30)->default('actif');
                $table->char('created_by', 36)->nullable();
                $table->char('updated_by', 36)->nullable();
                $table->char('deleted_by', 36)->nullable();
                $table->softDeletes();
                $table->timestamps();

                $table->foreign('paroisse_configuration_id')->references('id')->on('paroisse_configurations')->onDelete('cascade');
                $table->foreign('catechumene_id')->references('id')->on('catechumenes')->onDelete('cascade');
                $table->foreign('sacrement_id')->references('id')->on('sacrements')->onDelete('cascade');
                $table->foreign('annee_catechese_id')->references('id')->on('annee_catecheses')->onDelete('set null');

                $table->index(['paroisse_configuration_id', 'sacrement_id', 'statut'], 'sacr_exc_paroisse_statut_idx');
                $table->index(['catechumene_id', 'sacrement_id', 'annee_catechese_id'], 'cat_sacr_exc_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sacrement_exceptions');
    }
};
