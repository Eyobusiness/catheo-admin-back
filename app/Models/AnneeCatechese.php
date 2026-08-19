<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnneeCatechese extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $table = 'annee_catecheses';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'libelle',
        'date_debut',
        'date_fin',
        'est_active',
        'statut',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'est_active' => 'boolean',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(ParoisseConfiguration::class, 'paroisse_configuration_id');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(Classe::class, 'annee_catechese_id');
    }

    public function modulesTrimestriels(): HasMany
    {
        return $this->hasMany(ModuleTrimestriel::class, 'annee_catechese_id');
    }
}
