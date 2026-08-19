<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notes', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('paroisse_configuration_id')->constrained('paroisse_configurations')->cascadeOnDelete();
            $table->foreignId('evaluation_id')->constrained('evaluations')->cascadeOnDelete();
            $table->foreignId('catechumene_id')->constrained('catechumenes')->cascadeOnDelete();
            $table->decimal('note_obtenue', 4, 2);
            $table->string('appreciation')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['paroisse_configuration_id', 'evaluation_id', 'catechumene_id'], 'notes_paroisse_eval_cat_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notes');
    }
};
