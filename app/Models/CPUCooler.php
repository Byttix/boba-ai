<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CPUCooler extends Model
{
    protected $connection = 'sqlite_components';
    protected $table = 'coolers';

    protected $fillable = [
        'name',
        'manufacturer',
        'cooler_type',
        'max_tdp',
        'height',
        'price',
        'url'
    ];

    protected $casts = [
        'max_tdp' => 'integer',
        'height' => 'integer',
        'price' => 'integer',
    ];

    public function builds()
    {
        return $this->hasMany(Build::class);
    }
}
