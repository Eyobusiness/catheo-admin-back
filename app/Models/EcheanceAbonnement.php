<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class EcheanceAbonnement extends Model
{
    use Auditable, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'echeances_abonnement';

    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_PAYEE      = 'payee';
    public const STATUT_EN_RETARD  = 'en_retard';
    public const STATUT_ANNULEE    = 'annulee';

    public const STATUTS = [
        self::STATUT_EN_ATTENTE,
        self::STATUT_PAYEE,
        self::STATUT_EN_RETARD,
        self::STATUT_ANNULEE,
    ];

    protected $fillable = [
        'uuid',
        'abonnement_id',
        'reference',
        'periode_debut',
        'periode_fin',
        'date_echeance',
        'montant',
        'devise',
        'statut',
        'observation',
    ];

    protected $casts = [
        'periode_debut' => 'date',
        'periode_fin'   => 'date',
        'date_echeance' => 'date',
        'montant'       => 'decimal:2',
    ];

    public function resolveRouteBinding($value, $field = null)
    {
        return is_numeric($value)
            ? $this->where('id', $value)->first()
            : $this->where('uuid', $value)->orWhere('reference', $value)->first()
            ?? parent::resolveRouteBinding($value, $field);
    }

    public function abonnement(): BelongsTo
    {
        return $this->belongsTo(Abonnement::class, 'abonnement_id');
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(PaiementAbonnement::class, 'echeance_abonnement_id');
    }

    public function facture(): HasOne
    {
        return $this->hasOne(Facture::class, 'echeance_abonnement_id');
    }

    /**
     * Calcul du total des paiements validés pour cette échéance.
     */
    public function getMontantPayeAttribute(): float
    {
        return (float) $this->paiements()
            ->where('statut', PaiementAbonnement::STATUT_VALIDE)
            ->sum('montant');
    }

    /**
     * Calcul du solde restant dû.
     */
    public function getSoldeRestantAttribute(): float
    {
        return max(0.0, (float) $this->montant - $this->getMontantPayeAttribute());
    }

    /**
     * Vérifie si l'échéance est entièrement soldée.
     */
    public function isSolded(): bool
    {
        return $this->getSoldeRestantAttribute() <= 0.001;
    }
}
