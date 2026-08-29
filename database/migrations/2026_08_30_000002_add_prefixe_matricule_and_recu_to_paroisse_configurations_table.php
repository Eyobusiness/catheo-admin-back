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
        Schema::table('paroisse_configurations', function (Blueprint $table) {
            if (!Schema::hasColumn('paroisse_configurations', 'prefixe_matricule')) {
                $table->string('prefixe_matricule', 20)->nullable()->after('code_paroisse');
            }

            if (!Schema::hasColumn('paroisse_configurations', 'prefixe_recu')) {
                $table->string('prefixe_recu', 20)->nullable()->after('prefixe_matricule');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('paroisse_configurations', function (Blueprint $table) {
            if (Schema::hasColumn('paroisse_configurations', 'prefixe_recu')) {
                $table->dropColumn('prefixe_recu');
            }
            if (Schema::hasColumn('paroisse_configurations', 'prefixe_matricule')) {
                $table->dropColumn('prefixe_matricule');
            }
        });
    }
};
