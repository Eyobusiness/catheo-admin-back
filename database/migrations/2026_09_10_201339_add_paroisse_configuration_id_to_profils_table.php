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
        Schema::table('profils', function (Blueprint $table) {
            $table->foreignId('paroisse_configuration_id')
                ->nullable()
                ->after('uuid')
                ->constrained('paroisse_configurations')
                ->nullOnDelete();
        });

        // Rétro-affectation des profils existants aux paroisses correspondantes
        \DB::table('profils')->where('code', 'ADMIN_CIM01')->update(['paroisse_configuration_id' => 5]);
        \DB::table('profils')->whereIn('code', ['ADMIN', 'UTILISATEUR'])->update(['paroisse_configuration_id' => 3]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('profils', function (Blueprint $table) {
            $table->dropForeign(['paroisse_configuration_id']);
            $table->dropColumn('paroisse_configuration_id');
        });
    }
};
