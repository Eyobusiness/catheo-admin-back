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
        Schema::table('classes', function (Blueprint $table) {
            $columnsToDrop = [];
            foreach (['code', 'lieu_rassemblement', 'jour_rencontre', 'heure_debut', 'heure_fin'] as $col) {
                if (Schema::hasColumn('classes', $col)) {
                    $columnsToDrop[] = $col;
                }
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->string('code')->nullable();
            $table->string('lieu_rassemblement')->nullable();
            $table->string('jour_rencontre')->nullable();
            $table->time('heure_debut')->nullable();
            $table->time('heure_fin')->nullable();
        });
    }
};
