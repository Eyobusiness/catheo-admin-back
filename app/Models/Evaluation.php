<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Evaluation extends Model
{
    use HasAuditFields, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'evaluations';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'annee_catechese_id',
        'module_trimestriel_id',
        'classe_id',
        'titre',
        'description',
        'type_eval',
        'coefficient',
        'note_max',
        'date_evaluation',
        'statut',
    ];

    protected $casts = [
        'coefficient' => 'decimal:1',
        'note_max' => 'decimal:2',
        'date_evaluation' => 'date',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(ParoisseConfiguration::class, 'paroisse_configuration_id');
    }

    public function anneeCatechese(): BelongsTo
    {
        return $this->belongsTo(AnneeCatechese::class, 'annee_catechese_id');
    }

    public function moduleTrimestriel(): BelongsTo
    {
        return $this->belongsTo(ModuleTrimestriel::class, 'module_trimestriel_id');
    }

    public function classe(): BelongsTo
    {
        return $this->belongsTo(Classe::class, 'classe_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(Note::class, 'evaluation_id');
    }

    /**
     * Calculer l'appréciation textuelle basée sur la note obtenue.
     */
    public static function calculateAppreciation(float $note, float $noteMax = 20.0): string
    {
        $ratio = ($noteMax > 0) ? ($note / $noteMax) * 20.0 : $note;

        if ($ratio >= 16.0) {
            return 'Très Bien';
        }
        if ($ratio >= 14.0) {
            return 'Bien';
        }
        if ($ratio >= 12.0) {
            return 'Assez Bien';
        }
        if ($ratio >= 10.0) {
            return 'Passable';
        }
        return 'Insuffisant';
    }
}
