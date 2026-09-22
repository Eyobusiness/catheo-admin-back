<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class CampagnePelerinage extends Model
{
    use Auditable, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'campagne_pelerinages';

    public const STATUT_BROUILLON = 'brouillon';
    public const STATUT_OUVERTE   = 'ouverte';
    public const STATUT_CLOTUREE  = 'cloturee';
    public const STATUT_ANNULEE   = 'annulee';
    public const STATUT_TERMINEE  = 'terminee';

    public const STATUTS = [
        self::STATUT_BROUILLON,
        self::STATUT_OUVERTE,
        self::STATUT_CLOTUREE,
        self::STATUT_ANNULEE,
        self::STATUT_TERMINEE,
    ];

    protected $fillable = [
        'uuid',
        'organisation_id',
        'activite_id',
        'code',
        'nom',
        'description',
        'lieu_depart',
        'destination',
        'date_depart',
        'heure_depart',
        'date_fin',
        'heure_fin',
        'date_debut_inscription',
        'date_fin_inscription',
        'capacite',
        'statut',
        'observation',
    ];

    protected $casts = [
        'date_depart'             => 'date',
        'date_fin'                => 'date',
        'date_debut_inscription'  => 'date',
        'date_fin_inscription'    => 'date',
        'capacite'                => 'integer',
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

    public function activite(): BelongsTo
    {
        return $this->belongsTo(Activite::class, 'activite_id');
    }

    public function tarifs(): HasMany
    {
        return $this->hasMany(TarifPelerinage::class, 'campagne_pelerinage_id');
    }

    public function inscriptions(): HasMany
    {
        return $this->hasMany(InscriptionPelerinage::class, 'campagne_pelerinage_id');
    }

    public function paiements(): HasManyThrough
    {
        return $this->hasManyThrough(
            PaiementPelerinage::class,
            InscriptionPelerinage::class,
            'campagne_pelerinage_id',
            'inscription_pelerinage_id',
            'id',
            'id'
        );
    }

    public function operations(): HasMany
    {
        return $this->hasMany(OperationOrganisation::class, 'campagne_pelerinage_id');
    }

    /**
     * Nombre de places actuellement occupées (inscriptions non annulées).
     */
    public function placesOccupees(): int
    {
        return $this->inscriptions()
            ->where('statut_inscription', '!=', InscriptionPelerinage::STATUT_ANNULEE)
            ->count();
    }

    /**
     * Vérifie si la capacité maximale est atteinte.
     */
    public function estComplete(): bool
    {
        if (is_null($this->capacite) || $this->capacite <= 0) {
            return false;
        }

        return $this->placesOccupees() >= $this->capacite;
    }
}
