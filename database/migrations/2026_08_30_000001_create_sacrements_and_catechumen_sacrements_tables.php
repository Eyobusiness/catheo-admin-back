<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sacrements')) {
            Schema::create('sacrements', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->string('code', 50)->unique();
                $table->string('nom', 100);
                $table->string('libelle', 100)->nullable();
                $table->text('description')->nullable();
                $table->integer('ordre')->default(1);
                $table->string('statut', 20)->default('actif');
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->softDeletes();
                $table->timestamps();
            });

            // Données initiales pour les 3 sacrements majeurs de l'initiation chrétienne
            DB::table('sacrements')->insert([
                [
                    'id'          => 1,
                    'uuid'        => (string) Str::uuid(),
                    'code'        => 'BAPTEME',
                    'nom'         => 'Baptême',
                    'libelle'     => 'Baptême',
                    'description' => 'Sacrement de l\'initiation chrétienne faisant entrer dans la communauté des chrétiens.',
                    'ordre'       => 1,
                    'statut'      => 'actif',
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ],
                [
                    'id'          => 2,
                    'uuid'        => (string) Str::uuid(),
                    'code'        => 'PREMIERE_COMMUNION',
                    'nom'         => 'Première Communion',
                    'libelle'     => 'Première Communion',
                    'description' => 'Première réception de l\'Eucharistie (Corps et Sang du Christ).',
                    'ordre'       => 2,
                    'statut'      => 'actif',
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ],
                [
                    'id'          => 3,
                    'uuid'        => (string) Str::uuid(),
                    'code'        => 'CONFIRMATION',
                    'nom'         => 'Confirmation',
                    'libelle'     => 'Confirmation',
                    'description' => 'Sacrement conférant l\'Esprit Saint pour fortifier la foi chrétienne.',
                    'ordre'       => 3,
                    'statut'      => 'actif',
                    'created_at'  => now(),
                    'updated_at'  => now(),
                ],
            ]);
        }

        if (!Schema::hasTable('catechumen_sacrements')) {
            Schema::create('catechumen_sacrements', function (Blueprint $table) {
                $table->id();
                $table->uuid('uuid')->unique();
                $table->unsignedBigInteger('paroisse_configuration_id')->index();
                $table->unsignedBigInteger('catechumene_id')->index();
                $table->unsignedBigInteger('sacrement_id')->index();
                $table->unsignedBigInteger('annee_catechese_id')->nullable()->index();
                $table->string('statut', 30)->default('preparation')->index(); // preparation, valide
                $table->date('date_sacrement')->nullable()->index();
                $table->string('lieu')->nullable();
                $table->string('paroisse_nom')->nullable();
                $table->string('celebrant')->nullable();
                $table->string('numero_registre')->nullable()->index();
                $table->string('num_carnet')->nullable();
                $table->text('observations')->nullable();
                $table->timestamp('validated_at')->nullable();
                $table->unsignedBigInteger('validated_by')->nullable()->index();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->unsignedBigInteger('deleted_by')->nullable();
                $table->softDeletes();
                $table->timestamps();

                $table->foreign('paroisse_configuration_id')->references('id')->on('paroisse_configurations')->onDelete('cascade');
                $table->foreign('catechumene_id')->references('id')->on('catechumenes')->onDelete('cascade');
                $table->foreign('sacrement_id')->references('id')->on('sacrements')->onDelete('cascade');
                $table->foreign('annee_catechese_id')->references('id')->on('annee_catecheses')->onDelete('set null');
                $table->foreign('validated_by')->references('id')->on('users')->onDelete('set null');

                $table->index(['catechumene_id', 'sacrement_id', 'statut'], 'cat_sacr_statut_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('catechumen_sacrements');
        Schema::dropIfExists('sacrements');
    }
};
