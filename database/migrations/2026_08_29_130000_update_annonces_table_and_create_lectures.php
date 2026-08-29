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
        Schema::table('annonces', function (Blueprint $table) {
            if (!Schema::hasColumn('annonces', 'cible_type')) {
                $table->string('cible_type', 50)->default('Tous')->after('contenu');
            }
            if (!Schema::hasColumn('annonces', 'cible_id')) {
                $table->text('cible_id')->nullable()->after('cible_type');
            }
            if (!Schema::hasColumn('annonces', 'cible_ids')) {
                $table->json('cible_ids')->nullable()->after('cible_id');
            }
            if (!Schema::hasColumn('annonces', 'cible_nom')) {
                $table->string('cible_nom', 255)->nullable()->after('cible_ids');
            }
            if (!Schema::hasColumn('annonces', 'ceb_id')) {
                $table->foreignId('ceb_id')->nullable()->after('classe_id')->constrained('cebs')->nullOnDelete();
            }
            if (!Schema::hasColumn('annonces', 'mouvement_id')) {
                $table->foreignId('mouvement_id')->nullable()->after('ceb_id')->constrained('mouvements')->nullOnDelete();
            }
            if (!Schema::hasColumn('annonces', 'canal')) {
                $table->string('canal', 50)->default('in_app')->after('mouvement_id');
            }
            if (!Schema::hasColumn('annonces', 'date_diffusion')) {
                $table->date('date_diffusion')->nullable()->after('date_publication');
            }
            if (!Schema::hasColumn('annonces', 'heure_diffusion')) {
                $table->string('heure_diffusion', 10)->nullable()->after('date_diffusion');
            }
            if (!Schema::hasColumn('annonces', 'priorite')) {
                $table->string('priorite', 20)->default('normale')->after('heure_diffusion');
            }
            if (!Schema::hasColumn('annonces', 'created_by')) {
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('annonces', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            }
        });

        if (!Schema::hasTable('annonce_lectures')) {
            Schema::create('annonce_lectures', function (Blueprint $table) {
                $table->id();
                $table->foreignId('annonce_id')->constrained('annonces')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('animateur_id')->nullable()->constrained('animateurs')->nullOnDelete();
                $table->foreignId('catechumene_id')->nullable()->constrained('catechumenes')->nullOnDelete();
                $table->timestamp('lu_at')->useCurrent();
                $table->timestamps();

                $table->index(['annonce_id', 'user_id']);
                $table->index(['annonce_id', 'animateur_id']);
                $table->index(['annonce_id', 'catechumene_id']);
            });
        }

        // Change statut on MySQL if necessary
        if (\Illuminate\Support\Facades\DB::getDriverName() !== 'sqlite') {
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `annonces` MODIFY `statut` VARCHAR(50) NOT NULL DEFAULT 'publiee'");
            \Illuminate\Support\Facades\DB::statement("ALTER TABLE `annonces` MODIFY `cible` VARCHAR(50) NULL DEFAULT 'tous'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('annonce_lectures');

        Schema::table('annonces', function (Blueprint $table) {
            $columnsToDrop = [
                'cible_type',
                'cible_id',
                'cible_ids',
                'cible_nom',
                'ceb_id',
                'mouvement_id',
                'canal',
                'date_diffusion',
                'heure_diffusion',
                'priorite',
                'created_by',
                'updated_by',
            ];

            foreach ($columnsToDrop as $column) {
                if (Schema::hasColumn('annonces', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
