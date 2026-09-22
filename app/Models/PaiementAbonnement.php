<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaiementAbonnement extends Model
{
    use Auditable, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'paiements_abonnement';

    public const MODE_ESPECES      = 'especes';
    public const MODE_VIREMENT     = 'virement';
    public const MODE_MOBILE_MONEY = 'mobile_money';
    public const MODE_CHEQUE       = 'cheque';
    public const MODE_AUTRE        = 'autre';

    public const MODES = [
        self::MODE_ESPECES,
        self::MODE_VIREMENT,
        self::MODE_MOBILE_MONEY,
        self::MODE_CHEQUE,
        self::MODE_AUTRE,
    ];

    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_VALIDE     = 'valide';
    public const STATUT_ANNULE     = 'annule';
    public const STATUT_REMBOURSE  = 'rembourse';

    public const STATUTS = [
        self::STATUT_EN_ATTENTE,
        self::STATUT_VALIDE,
        self::STATUT_ANNULE,
        self::STATUT_REMBOURSE,
    ];

    protected $fillable = [
        'uuid',
        'echeance_abonnement_id',
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
        'date_paiement' => 'date',
        'montant'       => 'decimal:2',
    ];

    public function resolveRouteBinding($value, $field = null)
    {
        return is_numeric($value)
            ? $this->where('id', $value)->first()
            : $this->where('uuid', $value)->orWhere('reference', $value)->first()
            ?? parent::resolveRouteBinding($value, $field);
    }

    public function echeance(): BelongsTo
    {
        return $this->belongsTo(EcheanceAbonnement::class, 'echeance_abonnement_id');
    }

    public function caissier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'uuid');
    }
}
