<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'audit_logs';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'user_id',
        'action',
        'entite_type',
        'entite_id',
        'anciennes_valeurs',
        'nouvelles_valeurs',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'anciennes_valeurs' => 'array',
        'nouvelles_valeurs' => 'array',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(ParoisseConfiguration::class, 'paroisse_configuration_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
