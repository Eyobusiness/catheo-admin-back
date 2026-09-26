<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait Auditable
{
    /**
     * Determines whether the model's audit columns use integer ID or UUID string.
     */
    protected static function getAuditKeyForModel($model, $user): mixed
    {
        if (!$user) {
            return null;
        }

        return static::getAuditKeyType($model) === 'id' ? $user->id : ($user->uuid ?? $user->id);
    }

    protected static function getAuditKeyType($model): string
    {
        // Tables using integer user ID for audit columns
        static $intTables = [
            'campagne_pelerinages',
            'inscription_pelerinages',
            'tarif_pelerinages',
            'paiement_pelerinages',
            'operation_organisations',
            'sacrements',
            'catechumen_sacrements',
            'system_notifications',
        ];

        return in_array($model->getTable(), $intTables, true) ? 'id' : 'uuid';
    }

    /**
     * Boot the trait to automatically handle created_by, updated_by, deleted_by.
     */
    public static function bootAuditable(): void
    {
        // 1. Création : assigne created_by (ID ou UUID selon la table)
        static::creating(function ($model) {
            if (Auth::check()) {
                $user = Auth::user();
                $auditKey = static::getAuditKeyForModel($model, $user);
                if (empty($model->created_by)) {
                    $model->created_by = $auditKey;
                }
            }
        });

        // 2. Modification : assigne updated_by
        static::updating(function ($model) {
            if (Auth::check()) {
                $user = Auth::user();
                $model->updated_by = static::getAuditKeyForModel($model, $user);
            }
        });

        // 3. Suppression (Soft Delete) : assigne deleted_by avant la suppression
        static::deleting(function ($model) {
            if (Auth::check() && method_exists($model, 'isForceDeleting') && !$model->isForceDeleting()) {
                $user = Auth::user();
                $model->deleted_by = static::getAuditKeyForModel($model, $user);
                $model->saveQuietly();
            }
        });

        // 4. Restauration : réinitialise deleted_by et met à jour updated_by
        if (method_exists(static::class, 'restoring')) {
            static::restoring(function ($model) {
                if (Auth::check()) {
                    $user = Auth::user();
                    $model->updated_by = static::getAuditKeyForModel($model, $user);
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
        $ownerKey = static::getAuditKeyType($this) === 'id' ? 'id' : 'uuid';
        return $this->belongsTo(User::class, 'created_by', $ownerKey);
    }

    /**
     * Relation vers l'utilisateur ayant effectué la dernière modification.
     */
    public function updatedBy(): BelongsTo
    {
        $ownerKey = static::getAuditKeyType($this) === 'id' ? 'id' : 'uuid';
        return $this->belongsTo(User::class, 'updated_by', $ownerKey);
    }

    /**
     * Relation vers l'utilisateur ayant supprimé l'enregistrement.
     */
    public function deletedBy(): BelongsTo
    {
        $ownerKey = static::getAuditKeyType($this) === 'id' ? 'id' : 'uuid';
        return $this->belongsTo(User::class, 'deleted_by', $ownerKey);
    }
}