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
        Schema::table('presences', function (Blueprint $table) {
            if (!Schema::hasColumn('presences', 'remarque')) {
                $table->string('remarque', 255)->nullable()->after('motif_absence');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            if (Schema::hasColumn('presences', 'remarque')) {
                $table->dropColumn('remarque');
            }
        });
    }
};
