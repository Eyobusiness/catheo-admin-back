<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activite extends Model
{
    use Auditable, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'activites';

    public const STATUT_BROUILLON = 'brouillon';
    public const STATUT_PLANIFIEE = 'planifiee';
    public const STATUT_EN_COURS  = 'en_cours';
    public const STATUT_TERMINEE  = 'terminee';
    public const STATUT_ANNULEE   = 'annulee';

    public const STATUTS = [
        self::STATUT_BROUILLON,
        self::STATUT_PLANIFIEE,
        self::STATUT_EN_COURS,
        self::STATUT_TERMINEE,
        self::STATUT_ANNULEE,
    ];

    protected $fillable = [
        'uuid',
        'organisation_id',
        'code',
        'titre',
        'description',
        'type_activite',
        'date_debut',
        'date_fin',
        'lieu',
        'responsable_id',
        'statut',
        'taux_execution',
        'observation',
    ];

    protected $casts = [
        'date_debut'      => 'datetime',
        'date_fin'        => 'datetime',
        'taux_execution'  => 'decimal:2',
    ];

    public function resolveRouteBinding($value, $field = null)
    {
        return is_numeric($value)
            ? $this->where('id', $value)->first()
            : $this->where('uuid', $value)->first()
            ?? parent::resolveRouteBinding($value, $field);
    }

    /**
     * Organisation organisatrice de l'activité.
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    /**
     * Responsable désigné (obligatoirement membre de la même organisation).
     */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(Membre::class, 'responsable_id');
    }

    /**
     * Campagnes de pèlerinage associées à cette activité.
     */
    public function campagnesPelerinage(): HasMany
    {
        return $this->hasMany(CampagnePelerinage::class, 'activite_id');
    }
}
