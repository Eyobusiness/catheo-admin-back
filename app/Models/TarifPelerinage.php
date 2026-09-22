<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TarifPelerinage extends Model
{
    use Auditable, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'tarif_pelerinages';

    public const STATUT_ACTIF   = 'actif';
    public const STATUT_INACTIF = 'inactif';

    protected $fillable = [
        'uuid',
        'campagne_pelerinage_id',
        'code',
        'libelle',
        'description',
        'montant',
        'devise',
        'statut',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
    ];

    public function resolveRouteBinding($value, $field = null)
    {
        return is_numeric($value)
            ? $this->where('id', $value)->first()
            : $this->where('uuid', $value)->first()
            ?? parent::resolveRouteBinding($value, $field);
    }

    public function campagne(): BelongsTo
    {
        return $this->belongsTo(CampagnePelerinage::class, 'campagne_pelerinage_id');
    }

    public function inscriptions(): HasMany
    {
        return $this->hasMany(InscriptionPelerinage::class, 'tarif_pelerinage_id');
    }
}
