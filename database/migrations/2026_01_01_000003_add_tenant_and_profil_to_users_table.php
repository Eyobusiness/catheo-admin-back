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
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->unique()->after('id');

            $table->foreignId('paroisse_configuration_id')
                ->nullable()
                ->after('id')
                ->constrained('paroisse_configurations')
                ->nullOnDelete();
            
            $table->foreignId('profil_id')
                ->nullable()
                ->after('paroisse_configuration_id')
                ->constrained('profils')
                ->nullOnDelete();

            $table->string('user_type')->default('admin')->after('profil_id'); // admin, animateur, parent
            $table->string('username')->nullable()->unique()->after('user_type'); // Matricule catéchumène ou identifiant unique
            $table->string('telephone')->nullable()->after('email');
            $table->string('statut')->default('actif')->after('password');
            $table->timestamp('dernier_login_at')->nullable()->after('statut');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['paroisse_configuration_id']);
            $table->dropColumn('paroisse_configuration_id');
            $table->dropForeign(['profil_id']);
            $table->dropColumn('profil_id');
            $table->dropColumn(['telephone', 'statut', 'dernier_login_at']);
            $table->dropSoftDeletes();
        });
    }
};
