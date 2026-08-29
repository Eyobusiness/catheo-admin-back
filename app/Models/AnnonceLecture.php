<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnnonceLecture extends Model
{
    use HasFactory;

    protected $table = 'annonce_lectures';

    protected $fillable = [
        'annonce_id',
        'user_id',
        'animateur_id',
        'catechumene_id',
        'lu_at',
    ];

    protected $casts = [
        'lu_at' => 'datetime',
    ];

    public function annonce(): BelongsTo
    {
        return $this->belongsTo(Annonce::class, 'annonce_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function animateur(): BelongsTo
    {
        return $this->belongsTo(Animateur::class, 'animateur_id');
    }

    public function catechumene(): BelongsTo
    {
        return $this->belongsTo(Catechumene::class, 'catechumene_id');
    }
}
