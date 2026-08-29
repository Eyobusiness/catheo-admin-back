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
        if (!Schema::hasColumn('modules_trimestriels', 'statut')) {
            Schema::table('modules_trimestriels', function (Blueprint $table) {
                $table->string('statut', 30)->default('en_cours')->after('date_fin'); // en_cours, termine
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('modules_trimestriels', 'statut')) {
            Schema::table('modules_trimestriels', function (Blueprint $table) {
                $table->dropColumn('statut');
            });
        }
    }
};
