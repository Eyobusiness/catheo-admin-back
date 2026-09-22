<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OperationPaiement extends Model
{
    use Auditable, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'operations_paiements';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'annee_catechese_id',
        'catechumene_id',
        'tarif_id',
        'reference',
        'libelle',
        'montant',
        'montant_paye',
        'remise',
        'echeance',
        'statut',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'montant_paye' => 'decimal:2',
        'remise' => 'decimal:2',
        'echeance' => 'date',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(CatecheseConfiguration::class, 'paroisse_configuration_id');
    }

    public function anneeCatechese(): BelongsTo
    {
        return $this->belongsTo(AnneeCatechese::class, 'annee_catechese_id');
    }

    public function catechumene(): BelongsTo
    {
        return $this->belongsTo(Catechumene::class, 'catechumene_id');
    }

    public function tarif(): BelongsTo
    {
        return $this->belongsTo(Tarif::class, 'tarif_id');
    }
}
