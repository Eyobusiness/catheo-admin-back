<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class OperationOrganisation extends Model
{
    use Auditable, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'operation_organisations';

    public const TYPE_ENTREE = 'entree';
    public const TYPE_SORTIE = 'sortie';

    public const STATUT_VALIDE = 'valide';
    public const STATUT_ANNULE = 'annule';

    protected $fillable = [
        'uuid',
        'organisation_id',
        'campagne_pelerinage_id',
        'inscription_pelerinage_id',
        'paiement_pelerinage_id',
        'reference',
        'type_operation',
        'montant',
        'devise',
        'libelle',
        'mode_reglement',
        'date_operation',
        'statut',
        'created_by',
    ];

    protected $casts = [
        'montant'        => 'decimal:2',
        'date_operation' => 'datetime',
    ];

    public function resolveRouteBinding($value, $field = null)
    {
        return is_numeric($value)
            ? $this->where('id', $value)->first()
            : $this->where('uuid', $value)->first()
            ?? parent::resolveRouteBinding($value, $field);
    }

    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    public function campagne(): BelongsTo
    {
        return $this->belongsTo(CampagnePelerinage::class, 'campagne_pelerinage_id');
    }

    public function inscription(): BelongsTo
    {
        return $this->belongsTo(InscriptionPelerinage::class, 'inscription_pelerinage_id');
    }

    public function paiement(): BelongsTo
    {
        return $this->belongsTo(PaiementPelerinage::class, 'paiement_pelerinage_id');
    }

    public function operateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
}
