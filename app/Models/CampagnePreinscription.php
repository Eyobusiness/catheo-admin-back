<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CampagnePreinscription extends Model
{
    use HasAuditFields, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'campagnes_preinscriptions';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'annee_catechese_id',
        'titre',
        'date_debut',
        'date_fin',
        'statut',
        'description',
        'sections_autorisees',
    ];

    protected $casts = [
        'date_debut' => 'date',
        'date_fin' => 'date',
        'sections_autorisees' => 'array',
    ];

    protected $appends = [
        'public_url',
        'qr_code_url',
        'est_ouverte',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(ParoisseConfiguration::class, 'paroisse_configuration_id');
    }

    public function anneeCatechese(): BelongsTo
    {
        return $this->belongsTo(AnneeCatechese::class, 'annee_catechese_id');
    }

    public function preinscriptions(): HasMany
    {
        return $this->hasMany(Preinscription::class, 'campagne_preinscription_id');
    }

    public function getPublicUrlAttribute(): string
    {
        $frontendUrl = env('FRONTEND_URL', config('app.url', 'http://localhost:4200'));
        return rtrim($frontendUrl, '/') . "/preinscriptions/campagne/{$this->uuid}";
    }

    public function getQrCodeUrlAttribute(): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($this->public_url);
    }

    public function getEstOuverteAttribute(): bool
    {
        return $this->statut === 'ouverte';
    }
}
