<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Gpu extends Model
{
    protected $connection = 'sqlite_components';
    protected $table = 'gpus';

    protected $fillable = [
        'name',
        'manufacturer',
        'vram',
        'length',
        'tdp',
        'price',
        'url'
    ];

    protected $casts = [
        'vram' => 'integer',
        'length' => 'integer',
        'tdp' => 'integer',
        'price' => 'integer',
    ];

    public function builds()
    {
        return $this->hasMany(Build::class);
    }
}
