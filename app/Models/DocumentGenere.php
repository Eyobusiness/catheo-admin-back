<?php

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentGenere extends Model
{
    use Auditable, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'documents_generes';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'modele_document_id',
        'catechumene_id',
        'annee_catechese_id',
        'user_id',
        'reference_document',
        'titre',
        'type_document',
        'contenu',
        'metadonnees',
        'date_generation',
        'statut',
    ];

    protected $casts = [
        'metadonnees'     => 'array',
        'date_generation' => 'date',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(CatecheseConfiguration::class, 'paroisse_configuration_id');
    }

    public function modeleDocument(): BelongsTo
    {
        return $this->belongsTo(ModeleDocument::class, 'modele_document_id');
    }

    public function catechumene(): BelongsTo
    {
        return $this->belongsTo(Catechumene::class, 'catechumene_id');
    }

    public function anneeCatechese(): BelongsTo
    {
        return $this->belongsTo(AnneeCatechese::class, 'annee_catechese_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
