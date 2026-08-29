<?php

namespace App\Models;

use App\Traits\Auditable;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationLog extends Model
{
    use Auditable, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'notifications_log';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'canal',
        'destinataire',
        'sujet',
        'message',
        'statut_envoi',
        'erreur_message',
        'date_envoi',
    ];

    protected $casts = [
        'date_envoi' => 'datetime',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(CatecheseConfiguration::class, 'paroisse_configuration_id');
    }
}
