<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presence extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $table = 'presences';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'seance_id',
        'catechumene_id',
        'statut_presence',
        'motif_absence',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(ParoisseConfiguration::class, 'paroisse_configuration_id');
    }

    public function seance(): BelongsTo
    {
        return $this->belongsTo(Seance::class, 'seance_id');
    }

    public function catechumene(): BelongsTo
    {
        return $this->belongsTo(Catechumene::class, 'catechumene_id');
    }
}
