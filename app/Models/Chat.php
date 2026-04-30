<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Chat extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'last_message',
        'budget',
        'purpose',
    ];

    protected $casts = [
        'budget' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    // метод для удаления всех сообщений при удалении чата
    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($chat) {
            // Удаляем все сообщения чата
            $chat->messages()->delete();
        });
    }
}
