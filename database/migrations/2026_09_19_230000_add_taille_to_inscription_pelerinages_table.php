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
        Schema::table('inscription_pelerinages', function (Blueprint $table) {
            if (!Schema::hasColumn('inscription_pelerinages', 'taille')) {
                $table->string('taille', 10)->nullable()->after('sexe');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inscription_pelerinages', function (Blueprint $table) {
            if (Schema::hasColumn('inscription_pelerinages', 'taille')) {
                $table->dropColumn('taille');
            }
        });
    }
};
