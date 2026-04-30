<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

use App\Models\Cpu;
use App\Models\Motherboard;
use App\Models\Ram;
use App\Models\Gpu;
use App\Models\PowerSupply;
use App\Models\CPUCooler;
use App\Models\PCCase;
use App\Models\Storage;

class Build extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'total_price',
        'budget',
        'purpose',
        'cpu_id',
        'motherboard_id',
        'ram_id',
        'ram_quantity',
        'gpu_id',
        'power_supply_id',
        'cpu_cooler_id',
        'case_id',
        'storage_id',
    ];

    protected $casts = [
        'total_price' => 'float',
        'budget' => 'float',
        'ram_quantity' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // Связь с пользователем (MySQL)
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Связь с процессором (SQLite)
    public function cpu(): BelongsTo
    {
        return $this->belongsTo(Cpu::class, 'cpu_id');
    }

    // Связь с материнской платой (SQLite)
    public function motherboard(): BelongsTo
    {
        return $this->belongsTo(Motherboard::class, 'motherboard_id');
    }

    // Связь с оперативной памятью (SQLite)
    public function ram(): BelongsTo
    {
        return $this->belongsTo(Ram::class, 'ram_id');
    }

    // Связь с видеокартой (SQLite)
    public function gpu(): BelongsTo
    {
        return $this->belongsTo(Gpu::class, 'gpu_id');
    }

    // Связь с блоком питания (SQLite)
    public function powerSupply(): BelongsTo
    {
        return $this->belongsTo(PowerSupply::class, 'power_supply_id');
    }

    // Связь с кулером процессора (SQLite)
    public function cpuCooler(): BelongsTo
    {
        return $this->belongsTo(CPUCooler::class, 'cpu_cooler_id');
    }

    // Связь с корпусом (SQLite)
    public function case(): BelongsTo
    {
        return $this->belongsTo(PCCase::class, 'case_id');
    }

    // Связь с накопителем (SQLite)
    public function storage(): BelongsTo
    {
        return $this->belongsTo(Storage::class, 'storage_id');
    }
}
