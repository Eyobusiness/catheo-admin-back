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
        // 1. Champs d'authentification pour les animateurs
        Schema::table('animateurs', function (Blueprint $table) {
            if (!Schema::hasColumn('animateurs', 'password')) {
                $table->string('password')->nullable()->after('email');
            }
            if (!Schema::hasColumn('animateurs', 'remember_token')) {
                $table->rememberToken()->after('password');
            }
            if (!Schema::hasColumn('animateurs', 'dernier_login_at')) {
                $table->timestamp('dernier_login_at')->nullable()->after('statut');
            }
        });

        // 2. Champs d'authentification pour les parents / catéchumènes
        Schema::table('catechumenes', function (Blueprint $table) {
            if (!Schema::hasColumn('catechumenes', 'password')) {
                $table->string('password')->nullable()->after('telephone_tuteur');
            }
            if (!Schema::hasColumn('catechumenes', 'remember_token')) {
                $table->rememberToken()->after('password');
            }
            if (!Schema::hasColumn('catechumenes', 'dernier_login_at')) {
                $table->timestamp('dernier_login_at')->nullable()->after('statut');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('animateurs', function (Blueprint $table) {
            $table->dropColumn(['password', 'remember_token', 'dernier_login_at']);
        });

        Schema::table('catechumenes', function (Blueprint $table) {
            $table->dropColumn(['password', 'remember_token', 'dernier_login_at']);
        });
    }
};
