<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Facture extends Model
{
    use Auditable, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'factures';

    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_PAYEE      = 'payee';
    public const STATUT_ANNULEE    = 'annulee';

    public const STATUTS = [
        self::STATUT_EN_ATTENTE,
        self::STATUT_PAYEE,
        self::STATUT_ANNULEE,
    ];

    protected $fillable = [
        'uuid',
        'echeance_abonnement_id',
        'reference',
        'date_facture',
        'date_echeance',
        'montant_ht',
        'taux_tva',
        'montant_tva',
        'montant_total',
        'devise',
        'statut',
        'description',
        'observation',
    ];

    protected $casts = [
        'date_facture'  => 'date',
        'date_echeance' => 'date',
        'montant_ht'    => 'decimal:2',
        'taux_tva'      => 'decimal:2',
        'montant_tva'   => 'decimal:2',
        'montant_total' => 'decimal:2',
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
}
