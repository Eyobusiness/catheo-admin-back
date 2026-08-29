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
        if (Schema::hasTable('responsables_paroisse')) {
            Schema::table('responsables_paroisse', function (Blueprint $table) {
                $columnsToDrop = [];
                $targetColumns = ['titre', 'email', 'signature_path', 'ordre_affichage'];

                foreach ($targetColumns as $col) {
                    if (Schema::hasColumn('responsables_paroisse', $col)) {
                        $columnsToDrop[] = $col;
                    }
                }

                if (!empty($columnsToDrop)) {
                    $table->dropColumn($columnsToDrop);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('responsables_paroisse')) {
            Schema::table('responsables_paroisse', function (Blueprint $table) {
                if (!Schema::hasColumn('responsables_paroisse', 'titre')) {
                    $table->string('titre')->nullable()->after('paroisse_configuration_id');
                }
                if (!Schema::hasColumn('responsables_paroisse', 'email')) {
                    $table->string('email')->nullable()->after('telephone');
                }
                if (!Schema::hasColumn('responsables_paroisse', 'signature_path')) {
                    $table->string('signature_path')->nullable()->after('fonction');
                }
                if (!Schema::hasColumn('responsables_paroisse', 'ordre_affichage')) {
                    $table->integer('ordre_affichage')->default(0)->after('signature_path');
                }
            });
        }
    }
};
