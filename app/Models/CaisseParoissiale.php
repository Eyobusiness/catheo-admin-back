<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaisseParoissiale extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $table = 'caisse_paroissiale';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'annee_catechese_id',
        'type_mouvement',
        'categorie',
        'montant',
        'reference_document',
        'libelle',
        'date_mouvement',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'date_mouvement' => 'date',
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
