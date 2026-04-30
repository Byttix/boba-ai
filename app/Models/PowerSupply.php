<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PowerSupply extends Model
{
    protected $connection = 'sqlite_components';
    protected $table = 'psus';

    protected $fillable = [
        'name',
        'manufacturer',
        'power_consumption',
        'price',
        'url',
    ];

    protected $casts = [
        'power_consumption' => 'integer',
        'price' => 'integer',
    ];

    public function builds()
    {
        return $this->hasMany(Build::class);
    }
}
