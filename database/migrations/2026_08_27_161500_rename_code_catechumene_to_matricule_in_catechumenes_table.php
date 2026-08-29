<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catechumenes', function (Blueprint $table) {
            if (Schema::hasColumn('catechumenes', 'code_catechumene')) {
                $table->renameColumn('code_catechumene', 'matricule');
            }
        });
    }

    public function down(): void
    {
        Schema::table('catechumenes', function (Blueprint $table) {
            if (Schema::hasColumn('catechumenes', 'matricule')) {
                $table->renameColumn('matricule', 'code_catechumene');
            }
        });
    }
};
