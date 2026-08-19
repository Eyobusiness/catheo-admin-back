<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TypeActivite extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $table = 'types_activites';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'nom',
        'code',
        'couleur_agenda',
        'description',
        'statut',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(ParoisseConfiguration::class, 'paroisse_configuration_id');
    }

    public function activites(): HasMany
    {
        return $this->hasMany(Activite::class, 'type_activite_id');
    }
}
