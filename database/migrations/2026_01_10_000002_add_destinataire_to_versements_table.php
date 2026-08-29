<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('versements', function (Blueprint $table) {
            if (!Schema::hasColumn('versements', 'destinataire')) {
                $table->string('destinataire')->nullable()->after('effectue_par');
            }
        });
    }

    public function down(): void
    {
        Schema::table('versements', function (Blueprint $table) {
            if (Schema::hasColumn('versements', 'destinataire')) {
                $table->dropColumn('destinataire');
            }
        });
    }
};
