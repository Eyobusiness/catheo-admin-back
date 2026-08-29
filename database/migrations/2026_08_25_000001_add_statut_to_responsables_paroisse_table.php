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
        if (Schema::hasTable('responsables_paroisse')) {
            Schema::table('responsables_paroisse', function (Blueprint $table) {
                if (!Schema::hasColumn('responsables_paroisse', 'statut')) {
                    $table->string('statut')->default('actif')->after('ordre_affichage');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('responsables_paroisse')) {
            Schema::table('responsables_paroisse', function (Blueprint $table) {
                if (Schema::hasColumn('responsables_paroisse', 'statut')) {
                    $table->dropColumn('statut');
                }
            });
        }
    }
};
