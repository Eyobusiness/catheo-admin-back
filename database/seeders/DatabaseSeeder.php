<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Par défaut : base de test propre avec Sainte Monique.
     *
     * Pour la démo complète avec fausses données, remplacer par :
     *   $this->call(InitialSetupSeeder::class);
     *   $this->call(FakeDataSeeder::class);
     */
    public function run(): void
    {
        // ── Base de test propre (par défaut) ──────────────────────────────────
        $this->call(ProduitSeeder::class);
        $this->call(MenuSeeder::class);
        $this->call(CleanTestDatabaseSeeder::class);

        // ── Démo complète (commenté) ──────────────────────────────────────────
        // $this->call(InitialSetupSeeder::class);
        // $this->call(FakeDataSeeder::class);
    }
}

