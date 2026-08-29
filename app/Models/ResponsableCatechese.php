<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResponsableCatechese extends Model
{
    use Auditable, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'responsables_paroisse';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'nom_prenoms',
        'fonction',
        'telephone',
        'statut',
    ];

    /**
     * Configuration de la catéchèse rattachée.
     */
    public function catechese(): BelongsTo
    {
        return $this->belongsTo(CatecheseConfiguration::class, 'paroisse_configuration_id');
    }
}
