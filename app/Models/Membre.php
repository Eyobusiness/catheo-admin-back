<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Membre extends Model
{
    use Auditable, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'membres';

    public const STATUT_ACTIF     = 'actif';
    public const STATUT_INACTIF   = 'inactif';
    public const STATUT_SUSPENDU  = 'suspendu';

    public const STATUTS = [
        self::STATUT_ACTIF,
        self::STATUT_INACTIF,
        self::STATUT_SUSPENDU,
    ];

    public const SEXE_MASCULIN  = 'M';
    public const SEXE_FEMININ   = 'F';

    public const SEXES = [
        self::SEXE_MASCULIN,
        self::SEXE_FEMININ,
    ];

    protected $fillable = [
        'uuid',
        'organisation_id',
        'nom',
        'prenoms',
        'sexe',
        'date_naissance',
        'telephone',
        'email',
        'quartier',
        'adresse',
        'fonction',
        'date_entree',
        'statut',
        'photo_path',
        'observation',
    ];

    protected $casts = [
        'date_naissance' => 'date',
        'date_entree'    => 'date',
    ];

    public function resolveRouteBinding($value, $field = null)
    {
        return is_numeric($value)
            ? $this->where('id', $value)->first()
            : $this->where('uuid', $value)->first()
            ?? parent::resolveRouteBinding($value, $field);
    }

    /**
     * Organisation à laquelle appartient ce membre.
     */
    public function organisation(): BelongsTo
    {
        return $this->belongsTo(Organisation::class, 'organisation_id');
    }

    /**
     * Activités dont ce membre est le responsable désigné.
     */
    public function activitesEnResponsabilite(): HasMany
    {
        return $this->hasMany(Activite::class, 'responsable_id');
    }

    /**
     * Nom complet du membre.
     */
    public function getNomCompletAttribute(): string
    {
        return trim("{$this->nom} {$this->prenoms}");
    }
}
