<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfilMenuPermission extends Model
{
    use HasFactory, HasUuid;

    protected $table = 'profil_menu_permissions';

    protected $fillable = [
        'uuid',
        'profil_id',
        'menu_id',
        'can_read',
        'can_create',
        'can_update',
        'can_delete',
        'can_restore',
        'can_force_delete',
    ];

    protected $casts = [
        'can_read'         => 'boolean',
        'can_create'       => 'boolean',
        'can_update'       => 'boolean',
        'can_delete'       => 'boolean',
        'can_restore'      => 'boolean',
        'can_force_delete' => 'boolean',
    ];

    public function profil(): BelongsTo
    {
        return $this->belongsTo(Profil::class, 'profil_id');
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'menu_id');
    }

    /**
     * Retourne les permissions sous forme de tableau associatif.
     */
    public function toPermissionsArray(): array
    {
        return [
            'create'       => (bool) $this->can_create,
            'read'         => (bool) $this->can_read,
            'update'       => (bool) $this->can_update,
            'delete'       => (bool) $this->can_delete,
            'restore'      => (bool) $this->can_restore,
            'force_delete' => (bool) $this->can_force_delete,
        ];
    }
}
