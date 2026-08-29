<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('paroisse_configurations', function (Blueprint $table) {
            if (Schema::hasColumn('paroisse_configurations', 'nom') && !Schema::hasColumn('paroisse_configurations', 'nom_paroisse')) {
                $table->renameColumn('nom', 'nom_paroisse');
            }

            if (!Schema::hasColumn('paroisse_configurations', 'logo_paroisse')) {
                $table->string('logo_paroisse')->nullable()->after('adresse');
            }

            if (!Schema::hasColumn('paroisse_configurations', 'logo_catechese')) {
                $table->string('logo_catechese')->nullable()->after('logo_paroisse');
            }
        });

        // Transférer l'ancien logo_path vers logo_paroisse si logo_path existe
        if (Schema::hasColumn('paroisse_configurations', 'logo_path') && Schema::hasColumn('paroisse_configurations', 'logo_paroisse')) {
            DB::table('paroisse_configurations')
                ->whereNull('logo_paroisse')
                ->whereNotNull('logo_path')
                ->update(['logo_paroisse' => DB::raw('logo_path')]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('paroisse_configurations', function (Blueprint $table) {
            if (Schema::hasColumn('paroisse_configurations', 'nom_paroisse') && !Schema::hasColumn('paroisse_configurations', 'nom')) {
                $table->renameColumn('nom_paroisse', 'nom');
            }

            if (Schema::hasColumn('paroisse_configurations', 'logo_paroisse')) {
                $table->dropColumn('logo_paroisse');
            }

            if (Schema::hasColumn('paroisse_configurations', 'logo_catechese')) {
                $table->dropColumn('logo_catechese');
            }
        });
    }
};
