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
        Schema::table('animateurs', function (Blueprint $table) {
            if (Schema::hasColumn('animateurs', 'user_id')) {
                // Drop foreign key if exists
                try {
                    $table->dropForeign(['user_id']);
                } catch (\Throwable $e) {
                    // Ignore if already dropped
                }
                $table->dropColumn('user_id');
            }

            if (Schema::hasColumn('animateurs', 'matricule')) {
                $table->dropColumn('matricule');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('animateurs', function (Blueprint $table) {
            if (!Schema::hasColumn('animateurs', 'user_id')) {
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()->after('paroisse_configuration_id');
            }
            if (!Schema::hasColumn('animateurs', 'matricule')) {
                $table->string('matricule')->nullable()->after('user_id');
            }
        });
    }
};
