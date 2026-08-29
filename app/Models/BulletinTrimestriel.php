<?php

namespace App\Models;

use App\Traits\Auditable;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulletinTrimestriel extends Model
{
    use Auditable, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'bulletins_trimestriels';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'inscription_annuelle_id',
        'module_trimestriel_id',
        'moyenne_trimestrielle',
        'rang',
        'assiduite_total_absences',
        'appreciation_generale',
        'statut',
    ];

    protected $casts = [
        'moyenne_trimestrielle' => 'decimal:2',
        'rang' => 'integer',
        'assiduite_total_absences' => 'integer',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(CatecheseConfiguration::class, 'paroisse_configuration_id');
    }

    public function inscriptionAnnuelle(): BelongsTo
    {
        return $this->belongsTo(InscriptionAnnuelle::class, 'inscription_annuelle_id');
    }

    public function moduleTrimestriel(): BelongsTo
    {
        return $this->belongsTo(ModuleTrimestriel::class, 'module_trimestriel_id');
    }
}
