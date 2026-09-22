<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class InscriptionPelerinage extends Model
{
    use Auditable, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'inscription_pelerinages';

    public const TYPE_CATECHUMENE = 'CATECHUMENE';
    public const TYPE_EXTERNE     = 'EXTERNE';

    public const STATUT_EN_ATTENTE           = 'en_attente';
    public const STATUT_PARTIELLEMENT_PAYEE  = 'partiellement_payee';
    public const STATUT_PAYEE                = 'payee';
    public const STATUT_ANNULEE              = 'annulee';

    public const STATUTS = [
        self::STATUT_EN_ATTENTE,
        self::STATUT_PARTIELLEMENT_PAYEE,
        self::STATUT_PAYEE,
        self::STATUT_ANNULEE,
    ];

    public const PARTICIPATION_PREVUE   = 'prevue';
    public const PARTICIPATION_PRESENTE = 'presente';
    public const PARTICIPATION_ABSENTE  = 'absente';

    public const PARTICIPATIONS = [
        self::PARTICIPATION_PREVUE,
        self::PARTICIPATION_PRESENTE,
        self::PARTICIPATION_ABSENTE,
    ];

    public const TAILLE_M    = 'M';
    public const TAILLE_S    = 'S';
    public const TAILLE_X    = 'X';
    public const TAILLE_L    = 'L';
    public const TAILLE_XL   = 'XL';
    public const TAILLE_XXL  = 'XXL';
    public const TAILLE_XXXL = 'XXXL';

    public const TAILLES = [
        self::TAILLE_M,
        self::TAILLE_S,
        self::TAILLE_X,
        self::TAILLE_L,
        self::TAILLE_XL,
        self::TAILLE_XXL,
        self::TAILLE_XXXL,
        'XS',
    ];

    protected $fillable = [
        'uuid',
        'campagne_pelerinage_id',
        'tarif_pelerinage_id',
        'catechumene_id',
        'type_participant',
        'reference',
        'nom',
        'prenoms',
        'sexe',
        'taille',
        'date_naissance',
        'telephone',
        'email',
        'adresse',
        'contact_urgence_nom',
        'contact_urgence_telephone',
        'montant',
        'montant_paye',
        'reste_a_payer',
        'statut_inscription',
        'statut_participation',
        'date_inscription',
        'badge_imprime',
        'kit_remis',
        'date_remise_kit',
        'observation',
    ];

    protected $casts = [
        'date_naissance'   => 'date',
        'date_inscription' => 'datetime',
        'date_remise_kit'  => 'datetime',
        'montant'          => 'decimal:2',
        'montant_paye'     => 'decimal:2',
        'reste_a_payer'    => 'decimal:2',
        'badge_imprime'    => 'boolean',
        'kit_remis'        => 'boolean',
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

    public function tarif(): BelongsTo
    {
        return $this->belongsTo(TarifPelerinage::class, 'tarif_pelerinage_id');
    }

    public function catechumene(): BelongsTo
    {
        return $this->belongsTo(Catechumene::class, 'catechumene_id');
    }

    public function paiements(): HasMany
    {
        return $this->hasMany(PaiementPelerinage::class, 'inscription_pelerinage_id');
    }

    public function operations(): HasMany
    {
        return $this->hasMany(OperationOrganisation::class, 'inscription_pelerinage_id');
    }

    /**
     * Recalcule le montant payé, le reste à payer et met à jour le statut d'inscription.
     */
    public function recalculerMontants(): self
    {
        if ($this->statut_inscription === self::STATUT_ANNULEE) {
            return $this;
        }

        $totalPaye = (float) $this->paiements()
            ->where('statut', PaiementPelerinage::STATUT_VALIDE)
            ->sum('montant');

        $montant = (float) $this->montant;
        $reste = max(0.0, round($montant - $totalPaye, 2));

        $this->montant_paye = $totalPaye;
        $this->reste_a_payer = $reste;

        if ($totalPaye <= 0) {
            $this->statut_inscription = self::STATUT_EN_ATTENTE;
        } elseif ($totalPaye < $montant) {
            $this->statut_inscription = self::STATUT_PARTIELLEMENT_PAYEE;
        } else {
            $this->statut_inscription = self::STATUT_PAYEE;
        }

        $this->save();

        return $this;
    }
}
