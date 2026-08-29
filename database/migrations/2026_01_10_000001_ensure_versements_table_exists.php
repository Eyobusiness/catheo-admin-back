<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        if (Schema::hasTable('versements_cure') && !Schema::hasTable('versements')) {
            Schema::rename('versements_cure', 'versements');
        } elseif (!Schema::hasTable('versements')) {
            Schema::create('versements', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('paroisse_configuration_id')->nullable()->constrained('paroisse_configurations')->cascadeOnDelete();
                $table->foreignId('annee_catechese_id')->nullable()->constrained('annee_catecheses')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('reference');
                $table->string('periode_concernee');
                $table->decimal('montant_verse', 12, 2);
                $table->string('mode_remise')->default('especes');
                $table->string('effectue_par')->nullable();
                $table->string('statut')->default('valide');
                $table->timestamps();
                $table->softDeletes();

                $table->index(['paroisse_configuration_id', 'statut'], 'versements_paroisse_statut_idx');
            });
        }

        // Cleanup obsolete tables if present in MySQL
        if (Schema::hasTable('dons_cotisations')) {
            Schema::dropIfExists('dons_cotisations');
        }
        if (Schema::hasTable('groupes')) {
            Schema::dropIfExists('groupes');
        }
        if (Schema::hasTable('activites')) {
            Schema::dropIfExists('activites');
        }
        if (Schema::hasTable('types_activites')) {
            Schema::dropIfExists('types_activites');
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        // No-op
    }
};
