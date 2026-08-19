<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait HasAuditFields
{
    /**
     * Boot the trait to automatically fill created_by, updated_by, deleted_by.
     */
    public static function bootHasAuditFields(): void
    {
        static::creating(function ($model) {
            if (Auth::check() && Auth::user()->uuid) {
                if (empty($model->created_by)) {
                    $model->created_by = Auth::user()->uuid;
                }
                if (empty($model->updated_by)) {
                    $model->updated_by = Auth::user()->uuid;
                }
            }
        });

        static::updating(function ($model) {
            if (Auth::check() && Auth::user()->uuid) {
                $model->updated_by = Auth::user()->uuid;
            }
        });

        static::deleting(function ($model) {
            if (Auth::check() && Auth::user()->uuid) {
                $model->deleted_by = Auth::user()->uuid;
                // Save updated_by and deleted_by before soft delete
                $model->saveQuietly();
            }
        });
    }
}
