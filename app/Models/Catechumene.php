<?php

namespace App\Models;

use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Catechumene extends Authenticatable
{
    use HasApiTokens, HasAuditFields, HasFactory, HasUuid, Notifiable, SoftDeletes;

    protected $table = 'catechumenes';

    protected $fillable = [
        'uuid',
        'paroisse_configuration_id',
        'ceb_id',
        'user_id',
        'code_catechumene',
        'nom',
        'prenoms',
        'sexe',
        'date_naissance',
        'lieu_naissance',
        'adresse',
        'domicile',
        'profession',
        'classe_scolaire',
        'telephone',
        'photo_path',
        'nom_pere',
        'origine_pere',
        'telephone_pere',
        'nom_mere',
        'origine_mere',
        'telephone_mere',
        'nom_tuteur',
        'telephone_tuteur',
        'situation_matrimoniale',
        'password',
        'est_baptise',
        'num_carnet_bapteme',
        'date_bapteme',
        'lieu_bapteme',
        'diocese_bapteme',
        'ville_bapteme',
        'paroisse_bapteme',
        'date_premiere_communion',
        'paroisse_premiere_communion',
        'date_confirmation',
        'paroisse_confirmation',
        'ministre_confirmation',
        'statut',
        'dernier_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'date_naissance'           => 'date',
        'est_baptise'              => 'boolean',
        'date_bapteme'             => 'date',
        'date_premiere_communion'  => 'date',
        'date_confirmation'        => 'date',
        'dernier_login_at'         => 'datetime',
        'password'                 => 'hashed',
    ];

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(ParoisseConfiguration::class, 'paroisse_configuration_id');
    }

    public function ceb(): BelongsTo
    {
        return $this->belongsTo(Ceb::class, 'ceb_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function inscriptionsAnnuelles(): HasMany
    {
        return $this->hasMany(InscriptionAnnuelle::class, 'catechumene_id');
    }

    public function parrainsMarraines(): HasMany
    {
        return $this->hasMany(ParrainMarraine::class, 'catechumene_id');
    }

    public function getNomCompletAttribute(): string
    {
        return trim("{$this->prenoms} {$this->nom}");
    }

    /**
     * Menus accessibles pour l'espace Parent / Catéchumène
     */
    public function getAccessibleMenus(): array
    {
        return [
            [
                'libelle'   => 'Accueil & Synthèse',
                'icon'      => 'bi-house',
                'path'      => '/parent/dashboard',
                'reference' => 'parent_dashboard',
                'ordre'     => 1,
            ],
            [
                'libelle'   => 'Fiche & Sacrements',
                'icon'      => 'bi-person-badge',
                'path'      => '/parent/fiche',
                'reference' => 'parent_fiche',
                'ordre'     => 2,
            ],
            [
                'libelle'   => 'Assiduité & Présences',
                'icon'      => 'bi-calendar-check',
                'path'      => '/parent/presences',
                'reference' => 'parent_presences',
                'ordre'     => 3,
            ],
            [
                'libelle'   => 'Bulletins & Évaluations',
                'icon'      => 'bi-award',
                'path'      => '/parent/bulletins',
                'reference' => 'parent_bulletins',
                'ordre'     => 4,
            ],
            [
                'libelle'   => 'Paiements & Cotisations',
                'icon'      => 'bi-wallet2',
                'path'      => '/parent/paiements',
                'reference' => 'parent_paiements',
                'ordre'     => 5,
            ],
            [
                'libelle'   => 'Annonces Paroissiales',
                'icon'      => 'bi-bell',
                'path'      => '/parent/annonces',
                'reference' => 'parent_annonces',
                'ordre'     => 6,
            ],
        ];
    }
}
