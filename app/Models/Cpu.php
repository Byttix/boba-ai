<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cpu extends Model
{
    protected $connection = 'sqlite_components';
    protected $table = 'cpus';

    protected $fillable = [
        'name',
        'manufacturer',
        'socket',
        'cores',
        'threads',
        'tdp',
        'integrated_graphics',
        'price',
        'url'
    ];

    protected $casts = [
        'cores' => 'integer',
        'threads' => 'integer',
        'tdp' => 'integer',
        'price' => 'integer',
        'integrated_graphics' => 'boolean',
    ];

    // Связь со сборками
    public function builds()
    {
        return $this->hasMany(Build::class);
    }
}
