<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleTrimestriel extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $table = 'modules_trimestriels';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'annee_catechese_id',
        'nom',
        'numero_trimestre',
        'date_debut',
        'date_fin',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'numero_trimestre' => 'integer',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(ParoisseConfiguration::class, 'paroisse_configuration_id');
    }

    public function anneeCatechese(): BelongsTo
    {
        return $this->belongsTo(AnneeCatechese::class, 'annee_catechese_id');
    }
}
