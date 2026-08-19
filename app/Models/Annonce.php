<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Annonce extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $table = 'annonces';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'annee_catechese_id',
        'titre',
        'contenu',
        'cible',
        'section_id',
        'niveau_id',
        'classe_id',
        'date_publication',
        'date_expiration',
        'statut',
    ];

    protected $casts = [
        'date_publication' => 'date',
        'date_expiration' => 'date',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(ParoisseConfiguration::class, 'paroisse_configuration_id');
    }

    public function anneeCatechese(): BelongsTo
    {
        return $this->belongsTo(AnneeCatechese::class, 'annee_catechese_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function niveau(): BelongsTo
    {
        return $this->belongsTo(Niveau::class, 'niveau_id');
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class, 'classe_id');
    }
}
