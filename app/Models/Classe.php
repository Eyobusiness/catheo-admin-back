<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Classe extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $table = 'classes';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'annee_catechese_id',
        'niveau_id',
        'nom',
        'code',
        'capacite_max',
        'lieu_rassemblement',
        'jour_rencontre',
        'heure_debut',
        'heure_fin',
        'statut',
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

    public function groupes(): HasMany
    {
        return $this->hasMany(Groupe::class, 'classe_id');
    }

    public function affectationsAnimateurs(): HasMany
    {
        return $this->hasMany(AffectationAnimateur::class, 'classe_id');
    }

    public function inscriptionsAnnuelles(): HasMany
    {
        return $this->hasMany(InscriptionAnnuelle::class, 'classe_id');
    }
}
