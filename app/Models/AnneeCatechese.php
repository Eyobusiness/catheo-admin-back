<?php

namespace App\Models;

use App\Traits\Auditable;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnneeCatechese extends Model
{
    use Auditable, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'annee_catecheses';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'libelle',
        'date_debut',
        'date_fin',
        'statut',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(CatecheseConfiguration::class, 'paroisse_configuration_id');
    }

    public function classes(): HasMany
    {
        return $this->hasMany(Classe::class, 'annee_catechese_id');
    }

    public function modulesTrimestriels(): HasMany
    {
        return $this->hasMany(ModuleTrimestriel::class, 'annee_catechese_id');
    }

    /**
     * Récupère l'année pastorale en cours / par défaut pour une paroisse donnée.
     * Priorité :
     * 1. Année avec statut = 'active'
     * 2. Année couvrant la date du jour
     * 3. Année la plus récente
     */
    public static function getAnneeCourante(?int $paroisseId): ?self
    {
        if (!$paroisseId) {
            return null;
        }

        $annee = self::where('paroisse_configuration_id', $paroisseId)
            ->where('statut', 'active')
            ->first();

        if ($annee) {
            return $annee;
        }

        $today = now()->toDateString();
        $annee = self::where('paroisse_configuration_id', $paroisseId)
            ->whereDate('date_debut', '<=', $today)
            ->whereDate('date_fin', '>=', $today)
            ->first();

        if ($annee) {
            return $annee;
        }

        return self::where('paroisse_configuration_id', $paroisseId)
            ->latest('date_debut')
            ->first();
    }

    /**
     * Résout dynamiquement l'année catéchétique pour la requête (via en-tête ou paramètre),
     * avec repli par défaut sur l'année en cours.
     */
    public static function resolveAnnee(\Illuminate\Http\Request $request, ?int $paroisseId = null): ?self
    {
        $paroisseId = $paroisseId ?? $request->user()?->paroisse_configuration_id;

        if (!$paroisseId) {
            return null;
        }

        $anneeIdentifier = $request->input('annee_catechese_id')
            ?? $request->input('annee_id')
            ?? $request->header('X-Annee-Id')
            ?? $request->header('X-Annee-Catechese-Id');

        if (!empty($anneeIdentifier)) {
            $query = self::where('paroisse_configuration_id', $paroisseId);
            $found = is_numeric($anneeIdentifier)
                ? (clone $query)->where('id', (int) $anneeIdentifier)->first()
                : (clone $query)->where('uuid', $anneeIdentifier)->first();

            if ($found) {
                return $found;
            }
        }

        return self::getAnneeCourante($paroisseId);
    }
}
