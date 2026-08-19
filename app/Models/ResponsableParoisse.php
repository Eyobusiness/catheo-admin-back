<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResponsableParoisse extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $table = 'responsables_paroisse';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'titre',
        'nom_prenoms',
        'telephone',
        'email',
        'fonction',
        'signature_path',
        'ordre_affichage',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(ParoisseConfiguration::class, 'paroisse_configuration_id');
    }
}
