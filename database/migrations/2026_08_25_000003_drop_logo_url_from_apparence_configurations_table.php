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
        if (Schema::hasTable('apparence_configurations')) {
            Schema::table('apparence_configurations', function (Blueprint $table) {
                if (Schema::hasColumn('apparence_configurations', 'logo_url')) {
                    $table->dropColumn('logo_url');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('apparence_configurations')) {
            Schema::table('apparence_configurations', function (Blueprint $table) {
                if (!Schema::hasColumn('apparence_configurations', 'logo_url')) {
                    $table->string('logo_url', 550)->nullable()->after('police_caracteres');
                }
            });
        }
    }
};
