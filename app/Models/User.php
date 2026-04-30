<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    // Связь с сохраненными сборками
    public function builds()
    {
        return $this->hasMany(Build::class);
    }

    // Связь с чатами
    public function chats()
    {
        return $this->hasMany(Chat::class);
    }

    // Связь с отзывами
    public function reviews()
    {
        return $this->hasMany(Review::class);
    }
}
