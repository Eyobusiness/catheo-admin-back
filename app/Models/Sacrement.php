<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sacrement extends Model
{
    use Auditable, HasAuditFields, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'sacrements';

    protected $fillable = [
        'uuid',
        'code',
        'nom',
        'libelle',
        'description',
        'ordre',
        'statut',
    ];

    /**
     * Parcours sacramentels associés à ce type de sacrement.
     */
    public function catechumenSacrements(): HasMany
    {
        return $this->hasMany(CatechumenSacrement::class, 'sacrement_id');
    }
}
