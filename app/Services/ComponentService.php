<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ComponentService
{
    public function getComponents(string $type, array $filters = [])
    {
        $tableMap = [
            'cpu' => 'cpus',
            'motherboard' => 'motherboards',
            'ram' => 'rams',
            'gpu' => 'gpus',
            'psu' => 'psus',
            'case' => 'cases',
            'storage' => 'storages',
            'cooler' => 'coolers'
        ];

        $table = $tableMap[$type] ?? null;

        if (!$table) {
            return [
                'success' => false,
                'error' => "Неизвестный тип компонента: {$type}"
            ];
        }

        try {
            $query = DB::table($table);

            // Применяем фильтры
            if (isset($filters['min_price'])) {
                $query->where('price', '>=', $filters['min_price']);
            }

            if (isset($filters['max_price'])) {
                $query->where('price', '<=', $filters['max_price']);
            }

            $components = $query->limit(50)->get();

            return [
                'success' => true,
                'components' => $components,
                'count' => count($components)
            ];

        } catch (\Exception $e) {
            Log::error('ComponentService error: ' . $e->getMessage());

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
