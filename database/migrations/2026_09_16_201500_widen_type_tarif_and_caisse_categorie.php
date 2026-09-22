<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            // Elargir le type_tarif et le statut pour permettre tous les types de tarifs (ex: sacrement_bapteme, etc.)
            DB::statement("ALTER TABLE `tarifs` MODIFY COLUMN `type_tarif` VARCHAR(100) NOT NULL DEFAULT 'inscription'");
            DB::statement("ALTER TABLE `tarifs` MODIFY COLUMN `statut` VARCHAR(50) NOT NULL DEFAULT 'actif'");

            // Elargir categorie dans caisse_paroissiale pour supporter les categories dynamiques provenant des tarifs
            DB::statement("ALTER TABLE `caisse_paroissiale` MODIFY COLUMN `categorie` VARCHAR(100) NOT NULL DEFAULT 'inscription'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE `tarifs` MODIFY COLUMN `type_tarif` ENUM('inscription','manuel','uniforme','examen','retraite','autre') NOT NULL DEFAULT 'inscription'");
            DB::statement("ALTER TABLE `tarifs` MODIFY COLUMN `statut` ENUM('actif','inactif') NOT NULL DEFAULT 'actif'");
            DB::statement("ALTER TABLE `caisse_paroissiale` MODIFY COLUMN `categorie` ENUM('inscription','don','cotisation','depense_fournitures','depense_evenement','remboursement','autre') NOT NULL DEFAULT 'inscription'");
        }
    }
};
