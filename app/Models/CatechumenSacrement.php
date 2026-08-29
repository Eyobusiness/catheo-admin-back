<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CatechumenSacrement extends Model
{
    use Auditable, HasAuditFields, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'catechumen_sacrements';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'catechumene_id',
        'sacrement_id',
        'annee_catechese_id',
        'statut', // preparation, valide
        'date_sacrement',
        'lieu',
        'paroisse_nom',
        'celebrant',
        'numero_registre',
        'num_carnet',
        'observations',
        'validated_at',
        'validated_by',
    ];

    protected $casts = [
        'date_sacrement' => 'date',
        'validated_at'   => 'datetime',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(CatecheseConfiguration::class, 'paroisse_configuration_id');
    }

    public function catechumene(): BelongsTo
    {
        return $this->belongsTo(Catechumene::class, 'catechumene_id');
    }

    public function sacrement(): BelongsTo
    {
        return $this->belongsTo(Sacrement::class, 'sacrement_id');
    }

    public function anneeCatechese(): BelongsTo
    {
        return $this->belongsTo(AnneeCatechese::class, 'annee_catechese_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validated_by');
    }
}
