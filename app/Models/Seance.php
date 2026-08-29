<?php

namespace App\Models;

use App\Traits\Auditable;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Seance extends Model
{
    use Auditable, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'seances';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'annee_catechese_id',
        'classe_id',
        'titre',
        'date_seance',
        'heure_debut',
        'heure_fin',
        'description',
        'statut',
    ];

    protected $casts = [
        'date_seance' => 'date',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(CatecheseConfiguration::class, 'paroisse_configuration_id');
    }

    public function anneeCatechese(): BelongsTo
    {
        return $this->belongsTo(AnneeCatechese::class, 'annee_catechese_id');
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class, 'classe_id');
    }

    public function presences(): HasMany
    {
        return $this->hasMany(Presence::class, 'seance_id');
    }
}
