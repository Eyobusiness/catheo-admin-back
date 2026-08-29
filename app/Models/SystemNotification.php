<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class SystemNotification extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'system_notifications';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'user_id',
        'role_destinataire',
        'type',
        'action',
        'titre',
        'message',
        'source_type',
        'source_id',
        'route_url',
        'icon',
        'couleur',
        'donnees_additionnelles',
        'is_read',
        'read_at',
        'created_by',
    ];

    protected $casts = [
        'donnees_additionnelles' => 'array',
        'is_read'                => 'boolean',
        'read_at'                => 'datetime',
    ];

    /**
     * Relation vers la paroisse.
     */
    public function paroisseConfiguration(): BelongsTo
    {
        return $this->belongsTo(ParoisseConfiguration::class, 'paroisse_configuration_id');
    }

    /**
     * Relation vers l'utilisateur destinataire (si ciblé).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relation vers l'auteur de l'action.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope pour filtrer les notifications visibles pour un utilisateur connecté.
     */
    public function scopeForUser(Builder $query, $user): Builder
    {
        if (!$user) {
            return $query;
        }

        $paroisseId = $user->paroisse_configuration_id;

        if ($paroisseId) {
            $query->where('paroisse_configuration_id', $paroisseId);
        }

        return $query->where(function (Builder $q) use ($user) {
            $q->whereNull('user_id')
              ->orWhere('user_id', $user->id);
        });
    }

    /**
     * Marquer comme lue.
     */
    public function markAsRead(): self
    {
        $this->update([
            'is_read' => true,
            'read_at' => now(),
        ]);

        return $this;
    }
}
