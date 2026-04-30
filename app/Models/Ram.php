<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ram extends Model
{
    protected $connection = 'sqlite_components';
    protected $table = 'rams';

    protected $fillable = [
        'name',
        'manufacturer',
        'memory_type',
        'capacity',
        'speed',
        'price',
        'url'
    ];

    protected $casts = [
        'capacity' => 'integer',
        'speed' => 'integer',
        'price' => 'integer',
    ];

    public function builds()
    {
        return $this->hasMany(Build::class);
    }
}
