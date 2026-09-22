<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Formule extends Model
{
    use Auditable, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'formules';

    public const PERIODICITE_MENSUELLE = 'mensuelle';
    public const PERIODICITE_ANNUELLE   = 'annuelle';

    public const PERIODICITES = [
        self::PERIODICITE_MENSUELLE,
        self::PERIODICITE_ANNUELLE,
    ];

    protected $fillable = [
        'uuid',
        'produit_id',
        'code',
        'nom',
        'description',
        'periodicite',
        'montant',
        'devise',
        'est_gratuite',
        'statut',
        'ordre',
    ];

    protected $casts = [
        'montant'      => 'decimal:2',
        'est_gratuite' => 'boolean',
        'ordre'        => 'integer',
    ];

    /**
     * Résolution pour Route Model Binding.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return is_numeric($value)
            ? $this->where('id', $value)->first()
            : $this->where('uuid', $value)->orWhere('code', $value)->first()
            ?? parent::resolveRouteBinding($value, $field);
    }

    /**
     * Produit SaaS parent.
     */
    public function produit(): BelongsTo
    {
        return $this->belongsTo(Produit::class, 'produit_id');
    }

    /**
     * Abonnements souscrits à cette formule.
     */
    public function abonnements(): HasMany
    {
        return $this->hasMany(Abonnement::class, 'formule_id');
    }

    /**
     * Scope pour les formules actives.
     */
    public function scopeActif($query)
    {
        return $query->where('statut', 'actif');
    }
}
