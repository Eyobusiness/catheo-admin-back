<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Paiement extends Model
{
    use Auditable, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'paiements';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'annee_catechese_id',
        'inscription_annuelle_id',
        'catechumene_id',
        'numero_recu',
        'montant_total',
        'remise',
        'mode_paiement',
        'reference_transaction',
        'date_paiement',
        'statut',
        'notes',
    ];

    protected $casts = [
        'montant_total' => 'decimal:2',
        'remise' => 'decimal:2',
        'date_paiement' => 'date',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(CatecheseConfiguration::class, 'paroisse_configuration_id');
    }

    public function anneeCatechese(): BelongsTo
    {
        return $this->belongsTo(AnneeCatechese::class, 'annee_catechese_id');
    }

    public function inscriptionAnnuelle(): BelongsTo
    {
        return $this->belongsTo(InscriptionAnnuelle::class, 'inscription_annuelle_id');
    }

    public function catechumene(): BelongsTo
    {
        return $this->belongsTo(Catechumene::class, 'catechumene_id');
    }

    public function lignes(): HasMany
    {
        return $this->hasMany(LignePaiement::class, 'paiement_id');
    }
}
