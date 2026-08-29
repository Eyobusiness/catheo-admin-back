<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = ['modeles_documents', 'documents_generes'];
        foreach ($tables as $t) {
            if (Schema::hasTable($t)) {
                Schema::table($t, function (Blueprint $table) use ($t) {
                    if (!Schema::hasColumn($t, 'created_by')) {
                        $table->uuid('created_by')->nullable();
                    }
                    if (!Schema::hasColumn($t, 'updated_by')) {
                        $table->uuid('updated_by')->nullable();
                    }
                    if (!Schema::hasColumn($t, 'deleted_by')) {
                        $table->uuid('deleted_by')->nullable();
                    }
                });
            }
        }
    }

    public function down(): void
    {
        // No-op
    }
};
