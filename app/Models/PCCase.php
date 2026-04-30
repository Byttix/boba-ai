<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PCCase extends Model
{
    protected $connection = 'sqlite_components';
    protected $table = 'cases';

    protected $fillable = [
        'name',
        'manufacturer',
        'case_type',
        'max_gpu_length',
        'max_cpu_cooler_height',
        'price',
        'url'
    ];

    protected $casts = [
        'max_gpu_length' => 'integer',
        'max_cpu_cooler_height' => 'integer',
        'price' => 'integer',
];

    public function builds()
{
    return $this->hasMany(Build::class);
}
}
