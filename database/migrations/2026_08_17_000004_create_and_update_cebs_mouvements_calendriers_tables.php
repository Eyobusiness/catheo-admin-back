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
        // 1. Adapter la table CEBS
        Schema::table('cebs', function (Blueprint $table) {
            if (!Schema::hasColumn('cebs', 'responsable')) {
                $table->string('responsable', 150)->nullable()->after('nom');
            }
            if (!Schema::hasColumn('cebs', 'adresse')) {
                $table->text('adresse')->nullable()->after('telephone');
            }
            if (!Schema::hasColumn('cebs', 'description')) {
                $table->text('description')->nullable()->after('adresse');
            }
            if (!Schema::hasColumn('cebs', 'statut')) {
                $table->string('statut', 20)->default('Active')->after('description');
            }
            if (Schema::hasColumn('cebs', 'code')) {
                $table->string('code')->nullable()->change();
            }
        });

        // 2. Adapter la table MOUVEMENTS
        Schema::table('mouvements', function (Blueprint $table) {
            if (!Schema::hasColumn('mouvements', 'responsable')) {
                $table->string('responsable', 150)->nullable()->after('nom');
            }
            if (!Schema::hasColumn('mouvements', 'telephone')) {
                $table->string('telephone', 20)->nullable()->after('responsable');
            }
            if (!Schema::hasColumn('mouvements', 'statut')) {
                $table->string('statut', 20)->default('Active')->after('description');
            }
            if (Schema::hasColumn('mouvements', 'code')) {
                $table->string('code')->nullable()->change();
            }
        });

        // 3. Adapter inscriptions_annuelles pour porter ceb_id et mouvement_id
        Schema::table('inscriptions_annuelles', function (Blueprint $table) {
            if (!Schema::hasColumn('inscriptions_annuelles', 'ceb_id')) {
                $table->foreignId('ceb_id')->nullable()->after('classe_id')->constrained('cebs')->nullOnDelete();
            }
            if (!Schema::hasColumn('inscriptions_annuelles', 'mouvement_id')) {
                $table->foreignId('mouvement_id')->nullable()->after('ceb_id')->constrained('mouvements')->nullOnDelete();
            }
        });

        // 4. Créer la table CALENDRIERS
        if (!Schema::hasTable('calendriers')) {
            Schema::create('calendriers', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->foreignId('paroisse_configuration_id')
                    ->constrained('paroisse_configurations')
                    ->cascadeOnDelete();
                $table->foreignId('annee_catechese_id')
                    ->constrained('annee_catecheses')
                    ->cascadeOnDelete();
                $table->string('titre', 150);
                $table->string('type', 150);
                $table->date('date');
                $table->time('heure_debut')->nullable();
                $table->time('heure_fin')->nullable();
                $table->string('lieu', 150)->nullable();
                $table->enum('cible_type', ['TOUS', 'ANIMATEURS', 'SECTION', 'NIVEAU', 'CLASSE', 'CEB', 'MOUVEMENT'])->default('TOUS');
                $table->unsignedBigInteger('cible_id')->nullable();
                $table->text('description')->nullable();
                $table->enum('statut', ['Planifié', 'Réalisé', 'Annulé'])->default('Planifié');
                $table->timestamps();
                $table->softDeletes();
                $table->uuid('created_by')->nullable();
                $table->uuid('updated_by')->nullable();
                $table->uuid('deleted_by')->nullable();

                $table->index(['paroisse_configuration_id', 'annee_catechese_id', 'date'], 'calendriers_paroisse_date_index');
            });
        } else {
            Schema::table('calendriers', function (Blueprint $table) {
                if (!Schema::hasColumn('calendriers', 'created_by')) {
                    $table->uuid('created_by')->nullable()->after('updated_at');
                }
                if (!Schema::hasColumn('calendriers', 'updated_by')) {
                    $table->uuid('updated_by')->nullable()->after('created_by');
                }
                if (!Schema::hasColumn('calendriers', 'deleted_by')) {
                    $table->uuid('deleted_by')->nullable()->after('updated_by');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calendriers');

        Schema::table('inscriptions_annuelles', function (Blueprint $table) {
            if (Schema::hasColumn('inscriptions_annuelles', 'mouvement_id')) {
                $table->dropConstrainedForeignId('mouvement_id');
            }
            if (Schema::hasColumn('inscriptions_annuelles', 'ceb_id')) {
                $table->dropConstrainedForeignId('ceb_id');
            }
        });
    }
};
