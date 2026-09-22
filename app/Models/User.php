<?php

namespace App\Models;

use App\Traits\Auditable;

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
    use Auditable, HasApiTokens, HasAuditFields, HasFactory, HasUuid, Notifiable, SoftDeletes;

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'organisation_id',
        'profil_id',
        'user_type', // admin, animateur, parent
        'username',  // code_catechumene ou matricule
        'name',
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

    /**
     * Résolution robuste pour Route Model Binding (UUID ou ID numérique).
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return is_numeric($value)
            ? $this->where('id', $value)->first()
            : $this->where('uuid', $value)->first()
            ?? parent::resolveRouteBinding($value, $field);
    }

    public function catechese(): BelongsTo
    {
        return $this->belongsTo(CatecheseConfiguration::class, 'paroisse_configuration_id');
    }

    public function paroisse(): BelongsTo
    {
        return $this->catechese();
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
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

    public function isSuperAdmin(): bool
    {
        return $this->user_type === 'super_admin'
            || $this->profil?->code === 'SUPER_ADMIN'
            || in_array('*', (array) ($this->profil?->permissions ?? []), true);
    }

    public function isParoisseAdmin(): bool
    {
        return !empty($this->paroisse_configuration_id) && empty($this->organisation_id);
    }

    public function isOrganisationUser(): bool
    {
        return !empty($this->organisation_id);
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
