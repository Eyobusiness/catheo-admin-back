<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catechumenes', function (Blueprint $table) {
            $table->date('date_naissance')->nullable()->change();
        });

        if (Schema::hasTable('preinscriptions') && Schema::hasColumn('preinscriptions', 'date_naissance')) {
            Schema::table('preinscriptions', function (Blueprint $table) {
                $table->date('date_naissance')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        Schema::table('catechumenes', function (Blueprint $table) {
            $table->date('date_naissance')->nullable(false)->change();
        });

        if (Schema::hasTable('preinscriptions') && Schema::hasColumn('preinscriptions', 'date_naissance')) {
            Schema::table('preinscriptions', function (Blueprint $table) {
                $table->date('date_naissance')->nullable(false)->change();
            });
        }
    }
};
