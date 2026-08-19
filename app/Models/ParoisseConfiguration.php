<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ParoisseConfiguration extends Model
{
    use HasFactory, HasUuid, SoftDeletes;

    protected $table = 'paroisse_configurations';

    protected $fillable = [
        'uuid',
        'nom',
        'code_paroisse',
        'diocese',
        'doyenne',
        'ville',
        'commune',
        'telephone',
        'email',
        'site_web',
        'adresse',
        'logo_path',
        'cure_nom',
        'coordination_nom',
        'statut',
    ];

    /**
     * Utilisateurs rattachés à cette paroisse.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'paroisse_configuration_id');
    }

    /**
     * Catéchumènes rattachés à cette paroisse.
     */
    public function catechumenes(): HasMany
    {
        return $this->hasMany(Catechumene::class, 'paroisse_configuration_id');
    }

    /**
     * Classes rattachées à cette paroisse.
     */
    public function classes(): HasMany
    {
        return $this->hasMany(Classe::class, 'paroisse_configuration_id');
    }
}
