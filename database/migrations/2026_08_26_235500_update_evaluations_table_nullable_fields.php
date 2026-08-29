<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE evaluations MODIFY module_trimestriel_id BIGINT UNSIGNED NULL');
            DB::statement('ALTER TABLE evaluations MODIFY classe_id BIGINT UNSIGNED NULL');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement('ALTER TABLE evaluations MODIFY module_trimestriel_id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE evaluations MODIFY classe_id BIGINT UNSIGNED NOT NULL');
        }
    }
};
