<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ModeleDocument extends Model
{
    use Auditable, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'modeles_documents';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'titre',
        'code',
        'type_document',
        'description',
        'contenu',
        'variables_disponibles',
        'signature_nom',
        'signature_titre',
        'statut',
        'is_system',
    ];

    protected $casts = [
        'variables_disponibles' => 'array',
        'is_system'             => 'boolean',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(CatecheseConfiguration::class, 'paroisse_configuration_id');
    }

    public function documentsGeneres(): HasMany
    {
        return $this->hasMany(DocumentGenere::class, 'modele_document_id');
    }
}
