<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Motherboard extends Model
{
    protected $connection = 'sqlite_components';
    protected $table = 'motherboards';

    protected $fillable = [
        'name',
        'manufacturer',
        'socket',
        'chipset',
        'form_factor',
        'memory_type',
        'memory_slots',
        'price',
        'url'
    ];

    protected $casts = [
        'memory_slots' => 'integer',
        'max_memory' => 'integer',
        'price' => 'integer',
    ];

    public function builds()
    {
        return $this->hasMany(Build::class);
    }
}
