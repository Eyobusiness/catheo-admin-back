<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Menu extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $fillable = [
        'uuid',
        'parent_id',
        'libelle',
        'icon',
        'path',
        'reference',
        'ordre',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'ordre'     => 'integer',
    ];

    /**
     * Menu parent (si c'est un sous-menu).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    /**
     * Sous-menus associés.
     */
    public function sousMenus(): HasMany
    {
        return $this->hasMany(Menu::class, 'parent_id')->orderBy('ordre', 'asc');
    }

    /**
     * Permissions de profils associées à ce menu.
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(ProfilMenuPermission::class, 'menu_id');
    }

    /**
     * Scope pour les menus racines (parents).
     */
    public function scopeRoots($query)
    {
        return $query->whereNull('parent_id')->orderBy('ordre', 'asc');
    }
}
