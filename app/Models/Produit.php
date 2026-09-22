<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Produit extends Model
{
    use Auditable, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'produits';

    public const CODE_CATHEO = 'CATHEO';
    public const CODE_OPPE   = 'OPPE';
    public const CODE_OPPJ   = 'OPPJ';
    public const CODE_OPPA   = 'OPPA';

    public const CODES = [
        self::CODE_CATHEO,
        self::CODE_OPPE,
        self::CODE_OPPJ,
        self::CODE_OPPA,
    ];

    protected $fillable = [
        'uuid',
        'code',
        'nom',
        'description',
        'icone',
        'statut',
    ];

    /**
     * Résolution robuste pour Route Model Binding (UUID, code ou ID numérique).
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return is_numeric($value)
            ? $this->where('id', $value)->first()
            : $this->where('uuid', $value)->orWhere('code', $value)->first()
            ?? parent::resolveRouteBinding($value, $field);
    }

    /**
     * Formules tarifaires associées à ce produit.
     */
    public function formules(): HasMany
    {
        return $this->hasMany(Formule::class, 'produit_id');
    }

    /**
     * Organisations déployées pour ce produit.
     */
    public function organisations(): HasMany
    {
        return $this->hasMany(Organisation::class, 'produit_id');
    }

    /**
     * Scope pour filtrer les produits actifs.
     */
    public function scopeActif($query)
    {
        return $query->where('statut', 'actif');
    }

    /**
     * Recherche un produit par son code fonctionnel.
     */
    public static function findByCode(string $code): ?self
    {
        return static::where('code', strtoupper(trim($code)))->first();
    }
}
