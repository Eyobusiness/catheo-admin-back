<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaiementPelerinage extends Model
{
    use Auditable, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'paiement_pelerinages';

    public const STATUT_VALIDE    = 'valide';
    public const STATUT_ANNULE    = 'annule';
    public const STATUT_REMBOURSE = 'rembourse';

    protected $fillable = [
        'uuid',
        'inscription_pelerinage_id',
        'reference',
        'montant',
        'devise',
        'mode_paiement',
        'date_paiement',
        'statut',
        'reference_transaction',
        'observation',
    ];

    protected $casts = [
        'montant'       => 'decimal:2',
        'date_paiement' => 'datetime',
    ];

    public function resolveRouteBinding($value, $field = null)
    {
        return is_numeric($value)
            ? $this->where('id', $value)->first()
            : $this->where('uuid', $value)->first()
            ?? parent::resolveRouteBinding($value, $field);
    }

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(InscriptionPelerinage::class, 'inscription_pelerinage_id');
    }

    public function caissier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(OperationOrganisation::class, 'paiement_pelerinage_id');
    }
}
