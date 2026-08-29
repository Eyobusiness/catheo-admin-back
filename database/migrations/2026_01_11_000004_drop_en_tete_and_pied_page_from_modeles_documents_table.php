<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('modeles_documents')) {
            Schema::table('modeles_documents', function (Blueprint $table) {
                $columns = [];
                if (Schema::hasColumn('modeles_documents', 'en_tete_active')) {
                    $columns[] = 'en_tete_active';
                }
                if (Schema::hasColumn('modeles_documents', 'pied_page_active')) {
                    $columns[] = 'pied_page_active';
                }
                if (!empty($columns)) {
                    $table->dropColumn($columns);
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('modeles_documents')) {
            Schema::table('modeles_documents', function (Blueprint $table) {
                if (!Schema::hasColumn('modeles_documents', 'en_tete_active')) {
                    $table->boolean('en_tete_active')->default(true);
                }
                if (!Schema::hasColumn('modeles_documents', 'pied_page_active')) {
                    $table->boolean('pied_page_active')->default(true);
                }
            });
        }
    }
};
