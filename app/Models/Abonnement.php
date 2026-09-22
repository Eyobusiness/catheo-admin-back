<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Abonnement extends Model
{
    use Auditable, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'abonnements';

    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_ACTIF      = 'actif';
    public const STATUT_SUSPENDU   = 'suspendu';
    public const STATUT_EXPIRE     = 'expire';
    public const STATUT_RESILIE    = 'resilie';

    public const STATUTS = [
        self::STATUT_EN_ATTENTE,
        self::STATUT_ACTIF,
        self::STATUT_SUSPENDU,
        self::STATUT_EXPIRE,
        self::STATUT_RESILIE,
    ];

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'formule_id',
        'reference',
        'date_debut',
        'date_fin',
        'statut',
        'montant',
        'devise',
        'renouvellement_automatique',
        'date_resiliation',
        'motif_resiliation',
        'observation',
    ];

    protected $casts = [
        'date_debut'                  => 'date',
        'date_fin'                    => 'date',
        'date_resiliation'            => 'date',
        'montant'                     => 'decimal:2',
        'renouvellement_automatique'  => 'boolean',
    ];

    public function resolveRouteBinding($value, $field = null)
    {
        return is_numeric($value)
            ? $this->where('id', $value)->first()
            : $this->where('uuid', $value)->orWhere('reference', $value)->first()
            ?? parent::resolveRouteBinding($value, $field);
    }

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(CatecheseConfiguration::class, 'paroisse_configuration_id');
    }

    public function formule(): BelongsTo
    {
        return $this->belongsTo(Formule::class, 'formule_id');
    }

    public function echeances(): HasMany
    {
        return $this->hasMany(EcheanceAbonnement::class, 'abonnement_id');
    }

    public function scopeActif($query)
    {
        return $query->where('statut', self::STATUT_ACTIF);
    }
}
