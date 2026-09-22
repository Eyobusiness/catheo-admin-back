<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RegleProgressionPastorale extends Model
{
    use HasFactory;

    protected $table = 'regles_progression_pastorale';

    protected $fillable = [
        'paroisse_configuration_id',
        'code_section_source',
        'niveau_source',
        'decision',
        'code_section_destination',
        'niveau_destination',
        'est_fin_parcours',
        'actif',
        'ordre_priorite',
    ];

    protected $casts = [
        'est_fin_parcours' => 'boolean',
        'actif' => 'boolean',
        'ordre_priorite' => 'integer',
    ];

    public function paroisseConfiguration(): BelongsTo
    {
        return $this->belongsTo(ParoisseConfiguration::class, 'paroisse_configuration_id');
    }
}