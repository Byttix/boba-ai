<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Storage extends Model
{
    protected $connection = 'sqlite_components';
    protected $table = 'storages';

    protected $fillable = [
        'name',
        'manufacturer',
        'storage_type',
        'interface',
        'capacity',
        'price',
        'url'
    ];

    protected $casts = [
        'capacity' => 'integer',
        'price' => 'integer',
    ];

    public function builds()
    {
        return $this->hasMany(Build::class);
    }
}
