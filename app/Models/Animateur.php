<?php

namespace App\Models;

use App\Traits\Auditable;

use App\Traits\HasAuditFields;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Animateur extends Authenticatable
{
    use Auditable, HasApiTokens, HasAuditFields, HasFactory, HasUuid, Notifiable, SoftDeletes;

    protected $table = 'animateurs';

    protected $fillable = [
        'uuid',
        'numero',
        'paroisse_configuration_id',
        'nom',
        'prenoms',
        'sexe',
        'telephone',
        'email',
        'password',
        'profession',
        'statut',
        'dernier_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'dernier_login_at' => 'datetime',
            'password'         => 'hashed',
        ];
    }

    public function paroisse(): BelongsTo
    {
        return $this->belongsTo(CatecheseConfiguration::class, 'paroisse_configuration_id');
    }

    public function affectations(): HasMany
    {
        return $this->hasMany(AffectationAnimateur::class, 'animateur_id');
    }

    /**
     * Récupère l'affectation active de l'animateur pour une année pastorale donnée (ou l'année en cours).
     */
    public function affectationActive(?int $anneeId = null): ?AffectationAnimateur
    {
        if (!$anneeId) {
            $anneeCourante = AnneeCatechese::getAnneeCourante($this->paroisse_configuration_id);
            $anneeId = $anneeCourante?->id;
        }

        if (!$anneeId) {
            return null;
        }

        return $this->affectations()
            ->where('annee_catechese_id', $anneeId)
            ->with(['classe.niveau.section', 'anneeCatechese'])
            ->first();
    }

    /**
     * Récupère la classe active affectée à l'animateur pour l'année pastorale active.
     */
    public function classeActive(?int $anneeId = null): ?Classe
    {
        return $this->affectationActive($anneeId)?->classe;
    }

    public function getNomCompletAttribute(): string
    {
        return trim("{$this->prenoms} {$this->nom}");
    }

    public function getNumeroAttribute(): ?string
    {
        return $this->attributes['numero'] ?? $this->attributes['telephone'] ?? null;
    }

    /**
     * Menus accessibles pour l'espace Animateur
     */
    public function getAccessibleMenus(): array
    {
        return [
            [
                'libelle'   => 'Mon Tableau de Bord',
                'icon'      => 'bi-speedometer2',
                'path'      => '/animateur/dashboard',
                'reference' => 'animateur_dashboard',
                'ordre'     => 1,
            ],
            [
                'libelle'   => 'Mes Classes & Groupes',
                'icon'      => 'bi-mortarboard',
                'path'      => '/animateur/classes',
                'reference' => 'animateur_classes',
                'ordre'     => 2,
            ],
            [
                'libelle'   => 'Séances & Appel',
                'icon'      => 'bi-calendar-check',
                'path'      => '/animateur/seances',
                'reference' => 'animateur_seances',
                'ordre'     => 3,
            ],
            [
                'libelle'   => 'Évaluations & Notes',
                'icon'      => 'bi-journal-check',
                'path'      => '/animateur/evaluations',
                'reference' => 'animateur_evaluations',
                'ordre'     => 4,
            ],
            [
                'libelle'   => 'Annonces & Messages',
                'icon'      => 'bi-megaphone',
                'path'      => '/animateur/annonces',
                'reference' => 'animateur_annonces',
                'ordre'     => 5,
            ],
        ];
    }

    public function hasPermission(string $permission): bool
    {
        return true;
    }
}


