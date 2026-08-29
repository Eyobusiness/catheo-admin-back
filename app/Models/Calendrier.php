<?php

namespace App\Models;

use App\Traits\Auditable;

use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Calendrier extends Model
{
    use Auditable, HasAuditFields, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'calendriers';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'annee_catechese_id',
        'titre',
        'type',
        'date',
        'heure_debut',
        'heure_fin',
        'lieu',
        'cible_type',
        'cible_id',
        'cible_ids',
        'cible_nom',
        'description',
        'statut',
    ];

    protected function casts(): array
    {
        return [
            'date'      => 'date:Y-m-d',
            'cible_ids' => 'array',
        ];
    }

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(CatecheseConfiguration::class, 'paroisse_configuration_id');
    }

    public function anneeCatechese(): BelongsTo
    {
        return $this->belongsTo(AnneeCatechese::class, 'annee_catechese_id');
    }

    /**
     * Résout l'entité cible selon le type choisi.
     */
    public function getCibleAttribute(): mixed
    {
        if (!$this->cible_id) {
            return null;
        }

        return match ($this->cible_type) {
            'SECTION'   => Section::find($this->cible_id),
            'NIVEAU'    => Niveau::find($this->cible_id),
            'CLASSE'    => Classe::find($this->cible_id),
            'CEB'       => Ceb::find($this->cible_id),
            'MOUVEMENT' => Mouvement::find($this->cible_id),
            default     => null,
        };
    }
}
