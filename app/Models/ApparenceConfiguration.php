<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApparenceConfiguration extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'apparence_configurations';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'couleur_principale',
        'couleur_secondaire',
        'police_caracteres',
        'logo_url',
        'entete_document',
        'pied_page_document',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(ParoisseConfiguration::class, 'paroisse_configuration_id');
    }
}
