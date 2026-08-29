<?php

namespace App\Models;

use App\Traits\Auditable;

use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Preinscription extends Model
{
    use Auditable, HasAuditFields, HasFactory, HasUuid, SoftDeletes;

    protected $table = 'preinscriptions';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'campagne_preinscription_id',
        'annee_catechese_id',
        'section_souhaite_id',
        'niveau_souhaite_id',
        'code_dossier',
        'type_demande',
        'nom',
        'prenoms',
        'sexe',
        'date_naissance',
        'lieu_naissance',
        'adresse',
        'telephone',
        'photo_profil',
        'photo_url',
        'situation_matrimoniale',
        'nom_pere',
        'telephone_pere',
        'nom_mere',
        'telephone_mere',
        'nom_tuteur',
        'telephone_tuteur',
        'est_baptise',
        'date_bapteme',
        'lieu_bapteme',
        'paroisse_bapteme',
        'nom_parrain',
        'sexe_parrain',
        'telephone_parrain',
        'acte_naissance_url',
        'statut',
        'notes_validation',
    ];

    protected $casts = [
        'date_naissance' => 'date',
        'est_baptise' => 'boolean',
        'date_bapteme' => 'date',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(CatecheseConfiguration::class, 'paroisse_configuration_id');
    }

    public function campagne(): BelongsTo
    {
        return $this->belongsTo(CampagnePreinscription::class, 'campagne_preinscription_id');
    }

    public function anneeCatechese(): BelongsTo
    {
        return $this->belongsTo(AnneeCatechese::class, 'annee_catechese_id');
    }

    public function sectionSouhaite(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_souhaite_id');
    }

    public function niveauSouhaite(): BelongsTo
    {
        return $this->belongsTo(Niveau::class, 'niveau_souhaite_id');
    }
}
