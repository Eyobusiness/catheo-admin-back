<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    /**
     * Boot the trait to automatically handle created_by, updated_by, deleted_by.
     */
    public static function bootAuditable(): void
    {
        // 1. Création : assigne created_by à l'UUID de l'utilisateur authentifié
        static::creating(function ($model) {
            if (Auth::check()) {
                $user = Auth::user();
                $userId = $user->uuid ?? $user->id;
                if (empty($model->created_by)) {
                    $model->created_by = $userId;
                }
            }
        });

        // 2. Modification : assigne updated_by à l'UUID de l'utilisateur authentifié
        static::updating(function ($model) {
            if (Auth::check()) {
                $user = Auth::user();
                $userId = $user->uuid ?? $user->id;
                $model->updated_by = $userId;
            }
        });

        // 3. Suppression (Soft Delete) : assigne deleted_by avant la suppression
        static::deleting(function ($model) {
            if (Auth::check() && method_exists($model, 'isForceDeleting') && !$model->isForceDeleting()) {
                $user = Auth::user();
                $userId = $user->uuid ?? $user->id;
                $model->deleted_by = $userId;
                $model->saveQuietly();
            }
        });

        // 4. Restauration : réinitialise deleted_by et met à jour updated_by
        if (method_exists(static::class, 'restoring')) {
            static::restoring(function ($model) {
                if (Auth::check()) {
                    $user = Auth::user();
                    $userId = $user->uuid ?? $user->id;
                    $model->updated_by = $userId;
                }
                $model->deleted_by = null;
            });
        }
    }

    /**
     * Relation vers l'utilisateur créateur.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by', 'uuid');
    }

    /**
     * Relation vers l'utilisateur ayant effectué la dernière modification.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by', 'uuid');
    }

    /**
     * Relation vers l'utilisateur ayant supprimé l'enregistrement.
     */
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by', 'uuid');
    }
}
