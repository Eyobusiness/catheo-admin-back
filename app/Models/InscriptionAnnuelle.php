<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InscriptionAnnuelle extends Model
{
    use HasAuditFields, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'inscriptions_annuelles';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'catechumene_id',
        'annee_catechese_id',
        'section_id',
        'niveau_id',
        'classe_id',
        'ceb_id',
        'mouvement_id',
        'code_inscription',
        'date_inscription',
        'statut_inscription',
        'frais_inscription_payes',
        'observation',
    ];

    protected $casts = [
        'date_inscription' => 'date',
        'frais_inscription_payes' => 'boolean',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(ParoisseConfiguration::class, 'paroisse_configuration_id');
    }

    public function catechumene(): BelongsTo
    {
        return $this->belongsTo(Catechumene::class, 'catechumene_id');
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

    public function ceb(): BelongsTo
    {
        return $this->belongsTo(Ceb::class, 'ceb_id');
    }

    public function mouvement(): BelongsTo
    {
        return $this->belongsTo(Mouvement::class, 'mouvement_id');
    }
}
