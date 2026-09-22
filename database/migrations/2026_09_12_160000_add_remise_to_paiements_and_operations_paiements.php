<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('paiements') && !Schema::hasColumn('paiements', 'remise')) {
            Schema::table('paiements', function (Blueprint $table) {
                $table->decimal('remise', 12, 2)->default(0)->after('montant_total');
            });
        }

        if (Schema::hasTable('operations_paiements') && !Schema::hasColumn('operations_paiements', 'remise')) {
            Schema::table('operations_paiements', function (Blueprint $table) {
                $table->decimal('remise', 12, 2)->default(0)->after('montant');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('paiements') && Schema::hasColumn('paiements', 'remise')) {
            Schema::table('paiements', function (Blueprint $table) {
                $table->dropColumn('remise');
            });
        }

        if (Schema::hasTable('operations_paiements') && Schema::hasColumn('operations_paiements', 'remise')) {
            Schema::table('operations_paiements', function (Blueprint $table) {
                $table->dropColumn('remise');
            });
        }
    }
};
