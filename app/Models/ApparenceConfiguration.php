<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;

use App\Traits\Auditable;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApparenceConfiguration extends Model
{
    use Auditable, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'apparence_configurations';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'couleur_principale',
        'couleur_secondaire',
        'police_caracteres',
        'entete_document',
        'pied_page_document',
    ];

    public function catechese(): BelongsTo
    {
        return $this->belongsTo(CatecheseConfiguration::class, 'paroisse_configuration_id');
    }

    public function paroisse(): BelongsTo
    {
        return $this->catechese();
    }
}
