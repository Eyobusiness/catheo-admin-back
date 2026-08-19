<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Activite extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $table = 'activites';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'annee_catechese_id',
        'type_activite_id',
        'titre',
        'description',
        'lieu',
        'date_debut',
        'date_fin',
        'heure_debut',
        'heure_fin',
        'statut',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(ParoisseConfiguration::class, 'paroisse_configuration_id');
    }

    public function anneeCatechese(): BelongsTo
    {
        return $this->belongsTo(AnneeCatechese::class, 'annee_catechese_id');
    }

    public function typeActivite(): BelongsTo
    {
        return $this->belongsTo(TypeActivite::class, 'type_activite_id');
    }

    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(Section::class, 'activite_section');
    }

    public function niveaux(): BelongsToMany
    {
        return $this->belongsToMany(Niveau::class, 'activite_niveau');
    }

    public function classes(): BelongsToMany
    {
        return $this->belongsToMany(Classe::class, 'activite_classe');
    }

    public function animateurs(): BelongsToMany
    {
        return $this->belongsToMany(Animateur::class, 'activite_animateur');
    }
}
