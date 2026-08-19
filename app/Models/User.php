<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasAuditFields, HasFactory, HasUuid, Notifiable, SoftDeletes;

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'profil_id',
        'user_type', // admin, animateur, parent
        'username',  // code_catechumene ou matricule
        'name',
        'nom',
        'prenoms',
        'email',
        'telephone',
        'password',
        'statut',
        'dernier_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'dernier_login_at'  => 'datetime',
            'password'          => 'hashed',
        ];
    }

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(ParoisseConfiguration::class, 'paroisse_configuration_id');
    }

    public function profil(): BelongsTo
    {
        return $this->belongsTo(Profil::class, 'profil_id');
    }

    public function animateur(): HasOne
    {
        return $this->hasOne(Animateur::class, 'user_id');
    }

    public function catechumene(): HasOne
    {
        return $this->hasOne(Catechumene::class, 'user_id');
    }

    public function getNomAttribute(): string
    {
        $parts = explode(' ', $this->name ?? '', 2);
        return $parts[0] ?? '';
    }

    public function getPrenomsAttribute(): string
    {
        $parts = explode(' ', $this->name ?? '', 2);
        return $parts[1] ?? '';
    }

    /**
     * Vérifie si l'utilisateur possède une permission donnée.
     */
    public function hasPermission(string $permission): bool
    {
        if (!$this->profil) {
            return false;
        }

        return $this->profil->hasPermission($permission);
    }

    /**
     * Retourne l'arborescence des menus et sous-menus autorisés pour cet utilisateur.
     */
    public function getAccessibleMenus(): array
    {
        if (!$this->profil) {
            return [];
        }

        return $this->profil->getAccessibleMenusTree();
    }
}
