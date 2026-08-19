<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DonCotisation extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $table = 'dons_cotisations';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'annee_catechese_id',
        'mouvement_id',
        'ceb_id',
        'donateur_nom',
        'type_don',
        'montant',
        'description',
        'date_reception',
        'numero_recu',
    ];

    protected $casts = [
        'montant' => 'decimal:2',
        'date_reception' => 'date',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(ParoisseConfiguration::class, 'paroisse_configuration_id');
    }

    public function anneeCatechese(): BelongsTo
    {
        return $this->belongsTo(AnneeCatechese::class, 'annee_catechese_id');
    }

    public function mouvement(): BelongsTo
    {
        return $this->belongsTo(Mouvement::class, 'mouvement_id');
    }

    public function ceb(): BelongsTo
    {
        return $this->belongsTo(Ceb::class, 'ceb_id');
    }
}
