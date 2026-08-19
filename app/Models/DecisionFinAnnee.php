<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DecisionFinAnnee extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $table = 'decisions_fin_annee';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'inscription_annuelle_id',
        'moyenne_annuelle',
        'decision',
        'mention',
        'sacrement_recu',
        'date_decision',
        'observations',
    ];

    protected $casts = [
        'moyenne_annuelle' => 'decimal:2',
        'sacrement_recu' => 'boolean',
        'date_decision' => 'date',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(ParoisseConfiguration::class, 'paroisse_configuration_id');
    }

    public function inscriptionAnnuelle(): BelongsTo
    {
        return $this->belongsTo(InscriptionAnnuelle::class, 'inscription_annuelle_id');
    }
}
