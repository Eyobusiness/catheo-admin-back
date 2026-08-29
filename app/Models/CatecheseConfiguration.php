<?php

namespace App\Models;

use App\Traits\Auditable;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CatecheseConfiguration extends Model
{
    use Auditable, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'paroisse_configurations';

    protected $fillable = [
        'uuid',
        'nom_paroisse',
        'code_paroisse',
        'prefixe_matricule',
        'prefixe_recu',
        'diocese',
        'doyenne',
        'ville',
        'commune',
        'telephone',
        'email',
        'site_web',
        'adresse',
        'logo_paroisse',
        'logo_catechese',
        'logo_path',
        'cure_nom',
        'coordination_nom',
        'statut',
    ];

    /**
     * Accesseur de compatibilité pour le champ 'nom'.
     */
    public function getNomAttribute(): ?string
    {
        return $this->nom_paroisse;
    }

    /**
     * Mutateur de compatibilité pour le champ 'nom'.
     */
    public function setNomAttribute(?string $value): void
    {
        $this->attributes['nom_paroisse'] = $value;
    }

    /**
     * URL complète du logo de la paroisse.
     */
    public function getLogoParoisseUrlAttribute(): ?string
    {
        $val = $this->logo_paroisse ?: $this->logo_path;
        if (!$val) {
            return null;
        }
        if (str_contains($val, '/')) {
            return asset('storage/' . ltrim($val, '/'));
        }
        return asset('storage/catechese/logos/paroisse/' . $val);
    }

    /**
     * URL complète du logo de la catéchèse.
     */
    public function getLogoCatecheseUrlAttribute(): ?string
    {
        $val = $this->logo_catechese;
        if (!$val) {
            return null;
        }
        if (str_contains($val, '/')) {
            return asset('storage/' . ltrim($val, '/'));
        }
        return asset('storage/catechese/logos/catechese/' . $val);
    }

    /**
     * URL du logo par défaut (rétro-compatibilité).
     */
    public function getLogoUrlAttribute(): ?string
    {
        return $this->getLogoParoisseUrlAttribute();
    }

    /**
     * Utilisateurs rattachés à cette configuration de catéchèse.
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'paroisse_configuration_id');
    }

    /**
     * Catéchumènes rattachés à cette configuration.
     */
    public function catechumenes(): HasMany
    {
        return $this->hasMany(Catechumene::class, 'paroisse_configuration_id');
    }

    /**
     * Classes rattachées à cette configuration.
     */
    public function classes(): HasMany
    {
        return $this->hasMany(Classe::class, 'paroisse_configuration_id');
    }

    /**
     * Configuration d'apparence rattachée.
     */
    public function apparence(): HasOne
    {
        return $this->hasOne(ApparenceConfiguration::class, 'paroisse_configuration_id');
    }

    /**
     * Responsables de la catéchèse rattachés.
     */
    public function responsables(): HasMany
    {
        return $this->hasMany(ResponsableCatechese::class, 'paroisse_configuration_id');
    }

    /**
     * Années de catéchèse rattachées.
     */
    public function annees(): HasMany
    {
        return $this->hasMany(AnneeCatechese::class, 'paroisse_configuration_id');
    }
}
