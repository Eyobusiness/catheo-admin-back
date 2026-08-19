<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MutationCatechumene extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $table = 'mutations_catechumenes';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'catechumene_id',
        'annee_catechese_id',
        'paroisse_origine_nom',
        'paroisse_destination_nom',
        'motif',
        'date_mutation',
        'statut',
    ];

    protected $casts = [
        'date_mutation' => 'date',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(ParoisseConfiguration::class, 'paroisse_configuration_id');
    }

    public function catechumene(): BelongsTo
    {
        return $this->belongsTo(Catechumene::class, 'catechumene_id');
    }

    public function anneeCatechese(): BelongsTo
    {
        return $this->belongsTo(AnneeCatechese::class, 'annee_catechese_id');
    }
}
