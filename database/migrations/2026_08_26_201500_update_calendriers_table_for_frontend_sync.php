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
        Schema::table('calendriers', function (Blueprint $table) {
            if (!Schema::hasColumn('calendriers', 'cible_ids')) {
                $table->json('cible_ids')->nullable()->after('cible_id');
            }
            if (!Schema::hasColumn('calendriers', 'cible_nom')) {
                $table->string('cible_nom', 255)->nullable()->after('cible_ids');
            }
        });

        // Change cible_type & cible_id to standard strings if on MySQL
        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `calendriers` MODIFY `cible_type` VARCHAR(50) NOT NULL DEFAULT 'Tous'");
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `calendriers` MODIFY `cible_id` TEXT NULL");
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `calendriers` MODIFY `statut` VARCHAR(50) NOT NULL DEFAULT 'Planifié'");
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `calendriers` MODIFY `titre` VARCHAR(255) NOT NULL");
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `calendriers` MODIFY `type` VARCHAR(100) NOT NULL");
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `calendriers` MODIFY `lieu` VARCHAR(255) NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calendriers', function (Blueprint $table) {
            if (Schema::hasColumn('calendriers', 'cible_nom')) {
                $table->dropColumn('cible_nom');
            }
            if (Schema::hasColumn('calendriers', 'cible_ids')) {
                $table->dropColumn('cible_ids');
            }
        });
    }
};
