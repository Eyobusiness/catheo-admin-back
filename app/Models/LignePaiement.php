<?php

namespace App\Models;

use App\Traits\Auditable;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LignePaiement extends Model
{
    use Auditable, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'lignes_paiement';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'paiement_id',
        'tarif_id',
        'designation',
        'montant',
        'quantite',
        'sous_total',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'quantite' => 'integer',
        'sous_total' => 'decimal:2',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(CatecheseConfiguration::class, 'paroisse_configuration_id');
    }

    public function paiement(): BelongsTo
    {
        return $this->belongsTo(Paiement::class, 'paiement_id');
    }

    public function tarif(): BelongsTo
    {
        return $this->belongsTo(Tarif::class, 'tarif_id');
    }
}
