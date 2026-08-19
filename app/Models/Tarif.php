<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Tarif extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $table = 'tarifs';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'annee_catechese_id',
        'niveau_id',
        'intitule',
        'description',
        'montant',
        'periode_debut',
        'periode_fin',
        'est_obligatoire',
        'type_tarif',
        'statut',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'periode_debut' => 'date',
        'periode_fin' => 'date',
        'est_obligatoire' => 'boolean',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(ParoisseConfiguration::class, 'paroisse_configuration_id');
    }

    public function anneeCatechese(): BelongsTo
    {
        return $this->belongsTo(AnneeCatechese::class, 'annee_catechese_id');
    }

    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class, 'niveau_id');
    }

    public function niveaux(): BelongsToMany
    {
        return $this->belongsToMany(Niveau::class, 'tarif_niveau', 'tarif_id', 'niveau_id');
    }
}
