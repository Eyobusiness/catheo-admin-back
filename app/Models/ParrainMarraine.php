<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParrainMarraine extends Model
{
    use HasAuditFields, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'parrains_marraines';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'catechumene_id',
        'type',
        'nom_prenoms',
        'telephone',
        'email',
        'domicile',
        'paroisse_origine',
        'representant_nom',
        'representant_contact',
        'sacrement_confirmation',
    ];

    protected $casts = [
        'sacrement_confirmation' => 'boolean',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(ParoisseConfiguration::class, 'paroisse_configuration_id');
    }

    public function catechumene(): BelongsTo
    {
        return $this->belongsTo(Catechumene::class, 'catechumene_id');
    }
}
