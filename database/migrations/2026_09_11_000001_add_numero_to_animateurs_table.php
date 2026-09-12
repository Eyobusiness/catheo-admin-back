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
            if (!Schema::hasColumn('animateurs', 'numero')) {
                $table->string('numero', 50)->nullable()->after('uuid')->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('animateurs', function (Blueprint $table) {
            if (Schema::hasColumn('animateurs', 'numero')) {
                $table->dropIndex(['numero']);
                $table->dropColumn('numero');
            }
        });
    }
};
