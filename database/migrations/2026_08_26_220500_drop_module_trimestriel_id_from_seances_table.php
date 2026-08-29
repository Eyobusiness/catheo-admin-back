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
        Schema::table('seances', function (Blueprint $table) {
            if (Schema::hasColumn('seances', 'module_trimestriel_id')) {
                // Drop foreign key if not sqlite
                if (DB::getDriverName() !== 'sqlite') {
                    $table->dropForeign(['module_trimestriel_id']);
                }
                $table->dropColumn('module_trimestriel_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seances', function (Blueprint $table) {
            if (!Schema::hasColumn('seances', 'module_trimestriel_id')) {
                $table->foreignId('module_trimestriel_id')->nullable()->constrained('modules_trimestriels')->nullOnDelete();
            }
        });
    }
};
