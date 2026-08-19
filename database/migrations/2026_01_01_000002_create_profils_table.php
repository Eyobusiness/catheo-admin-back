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
        Schema::create('profils', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('nom');
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->string('statut')->default('actif'); // actif, inactif
            $table->json('permissions')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profils');
    }
};
