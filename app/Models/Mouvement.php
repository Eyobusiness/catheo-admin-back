<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mouvement extends Model
{
    use HasAuditFields, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'mouvements';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'nom',
        'code',
        'responsable',
        'telephone',
        'description',
        'statut',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(ParoisseConfiguration::class, 'paroisse_configuration_id');
    }

    public function inscriptionsAnnuelles(): HasMany
    {
        return $this->hasMany(InscriptionAnnuelle::class, 'mouvement_id');
    }
}
