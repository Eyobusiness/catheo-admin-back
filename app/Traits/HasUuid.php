<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasUuid
{
    /**
     * Boot trait pour générer automatiquement un UUID lors de la création d'une entité.
     */
    protected static function bootHasUuid(): void
    {
        static::creating(function ($model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Utiliser la colonne 'uuid' pour le Route Model Binding au lieu de 'id'.
     */
    public function getRouteKeyName(): string
    {
        return 'uuid';
    }
}
