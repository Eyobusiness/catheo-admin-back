<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Note extends Model
{
    use HasAuditFields, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'notes';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'evaluation_id',
        'catechumene_id',
        'note_obtenue',
        'appreciation',
    ];

    protected $casts = [
        'note_obtenue' => 'decimal:2',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(ParoisseConfiguration::class, 'paroisse_configuration_id');
    }

    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class, 'evaluation_id');
    }

    public function catechumene(): BelongsTo
    {
        return $this->belongsTo(Catechumene::class, 'catechumene_id');
    }
}
