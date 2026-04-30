<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = [
        'build_id',
        'user_id',
        'rating',
        'comment',
        'title'
    ];

    protected $casts = [
        'rating' => 'integer',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function build(): BelongsTo
    {
        return $this->belongsTo(Build::class);
    }
}
