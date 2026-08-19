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
        Schema::table('catechumenes', function (Blueprint $table) {
            if (!Schema::hasColumn('catechumenes', 'situation_matrimoniale')) {
                $table->string('situation_matrimoniale')->nullable()->after('classe_scolaire');
            }
        });

        Schema::table('preinscriptions', function (Blueprint $table) {
            if (!Schema::hasColumn('preinscriptions', 'situation_matrimoniale')) {
                $table->string('situation_matrimoniale')->nullable()->after('photo_url');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catechumenes', function (Blueprint $table) {
            $table->dropColumn('situation_matrimoniale');
        });

        Schema::table('preinscriptions', function (Blueprint $table) {
            $table->dropColumn('situation_matrimoniale');
        });
    }
};
