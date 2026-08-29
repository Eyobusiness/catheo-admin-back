<?php

namespace App\Models;

use App\Traits\Auditable;

use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sauvegarde extends Model
{
    use Auditable, HasAuditFields, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'sauvegardes';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'nom_fichier',
        'chemin_fichier',
        'taille_octets',
        'cree_par',
        'type',
        'statut',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(CatecheseConfiguration::class, 'paroisse_configuration_id');
    }

    /**
     * Formatage de la taille en Ko/Mo/Go.
     */
    public function getTailleFormattedAttribute(): string
    {
        $bytes = $this->taille_octets;
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 1) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' B';
    }
}
