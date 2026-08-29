<?php

namespace App\Models;

use App\Traits\Auditable;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AffectationAnimateur extends Model
{
    use Auditable, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'affectations_animateurs';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'animateur_id',
        'annee_catechese_id',
        'classe_id',
        'role_animateur',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(CatecheseConfiguration::class, 'paroisse_configuration_id');
    }

    public function animateur(): BelongsTo
    {
        return $this->belongsTo(Animateur::class, 'animateur_id');
    }

    public function anneeCatechese(): BelongsTo
    {
        return $this->belongsTo(AnneeCatechese::class, 'annee_catechese_id');
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class, 'classe_id');
    }
}
