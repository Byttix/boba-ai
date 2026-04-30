<?php

namespace App\Services;

use App\Models\Build;
use Illuminate\Support\Facades\Log;

class BuildService
{
    /**
     * Создать сборку
     */
    public function createBuild(array $data, int $userId): Build
    {
        try {
            Log::info('Создание сборки', ['data' => $data, 'user_id' => $userId]);

            // Проверяем обязательные поля
            $required = ['name', 'cpu_id', 'motherboard_id', 'ram_id'];
            foreach ($required as $field) {
                if (empty($data[$field])) {
                    throw new \Exception("Обязательное поле '{$field}' не заполнено");
                }
            }

            // Конвертируем пустые строки в null для опциональных полей
            $optionalFields = [
                'gpu_id', 'power_supply_id', 'cpu_cooler_id',
                'case_id', 'storage_id', 'budget', 'description'
            ];

            foreach ($optionalFields as $field) {
                if (isset($data[$field]) && $data[$field] === '') {
                    $data[$field] = null;
                }
            }

            // Получаем реальные цены компонентов
            $totalPrice = $this->calculateRealPrice($data);

            Log::info('Рассчитанная цена', ['total' => $totalPrice]);

            // Создаем сборку
            $build = Build::create([
                'user_id' => $userId,
                'name' => $data['name'],
                'description' => $data['description'] ?? 'Сборка из чата',
                'total_price' => $totalPrice,
                'budget' => $data['budget'] ?? $totalPrice * 1.2,
                'purpose' => $data['purpose'] ?? 'gaming',
                'cpu_id' => $data['cpu_id'],
                'motherboard_id' => $data['motherboard_id'],
                'ram_id' => $data['ram_id'],
                'ram_quantity' => $data['ram_quantity'] ?? 1,
                'gpu_id' => $data['gpu_id'] ?? null,
                'power_supply_id' => $data['power_supply_id'] ?? null,
                'cpu_cooler_id' => $data['cpu_cooler_id'] ?? null,
                'case_id' => $data['case_id'] ?? null,
                'storage_id' => $data['storage_id'] ?? null,
            ]);

            Log::info('Сборка успешно создана', [
                'build_id' => $build->id,
                'name' => $build->name,
                'price' => $totalPrice,
                'components' => [
                    'cpu_id' => $build->cpu_id,
                    'motherboard_id' => $build->motherboard_id,
                    'ram_id' => $build->ram_id,
                    'gpu_id' => $build->gpu_id,
                    'power_supply_id' => $build->power_supply_id,
                    'cpu_cooler_id' => $build->cpu_cooler_id,
                    'case_id' => $build->case_id,
                    'storage_id' => $build->storage_id,
                ]
            ]);

            return $build;

        } catch (\Exception $e) {
            Log::error('Ошибка создания сборки', [
                'error' => $e->getMessage(),
                'data' => $data,
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception('Не удалось создать сборку: ' . $e->getMessage());
        }
    }

    /**
     * Рассчитать реальную стоимость компонентов
     */
    protected function calculateRealPrice(array $componentIds): float
    {
        $total = 0;

        $componentModels = [
            'cpu_id' => 'cpus',
            'motherboard_id' => 'motherboards',
            'ram_id' => 'rams',
            'gpu_id' => 'gpus',
            'power_supply_id' => 'psus',
            'cpu_cooler_id' => 'coolers',
            'case_id' => 'cases',
            'storage_id' => 'storages',
        ];

        foreach ($componentModels as $field => $table) {
            if (!empty($componentIds[$field])) {
                try {
                    $component = \Illuminate\Support\Facades\DB::connection('sqlite_components')
                        ->table($table)
                        ->where('id', $componentIds[$field])
                        ->first();

                    if ($component && isset($component->price)) {
                        // Преобразуем цену в число
                        $price = is_string($component->price)
                            ? (float) preg_replace('/[^0-9\.]/', '', $component->price)
                            : (float) $component->price;

                        // Умножаем цену RAM на количество планок
                        if ($field === 'ram_id') {
                            $ramQuantity = $componentIds['ram_quantity'] ?? 1;
                            $price *= $ramQuantity;
                        }

                        $total += $price;
                        Log::info("Цена компонента {$field}: {$price}");
                    }
                } catch (\Exception $e) {
                    Log::warning('Не удалось получить цену компонента', [
                        'table' => $table,
                        'id' => $componentIds[$field],
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }

        Log::info('Итоговая цена сборки', ['total' => $total]);
        return $total;
    }

    public function saveFromChat(array $chatComponents, string $name, int $userId, float $budget, string $purpose): Build
    {
        Log::info('Сохранение сборки из чата', [
            'name' => $name,
            'user_id' => $userId,
            'budget' => $budget,
            'purpose' => $purpose,
            'chat_components_keys' => array_keys($chatComponents),
        ]);

        // Преобразуем структуру компонентов из чата
        $componentIds = [];

        $componentMapping = [
            'cpu' => 'cpu_id',
            'motherboard' => 'motherboard_id',
            'ram' => 'ram_id',
            'gpu' => 'gpu_id',
            'psu' => 'power_supply_id',
            'cooler' => 'cpu_cooler_id',
            'case' => 'case_id',
            'storage' => 'storage_id',
        ];

        foreach ($componentMapping as $chatKey => $dbField) {
            if (isset($chatComponents[$chatKey]['id'])) {
                $componentIds[$dbField] = $chatComponents[$chatKey]['id'];

                // Для RAM также получаем количество
                if ($chatKey === 'ram' && isset($chatComponents[$chatKey]['quantity'])) {
                    $componentIds['ram_quantity'] = $chatComponents[$chatKey]['quantity'];
                }
            }
        }

        if (!isset($componentIds['ram_quantity'])) {
            $componentIds['ram_quantity'] = 2; // По умолчанию 2 планки
        }

        Log::info('Сопоставленные ID компонентов:', $componentIds);

        $data = [
            'name' => $name,
            'budget' => $budget,
            'purpose' => $purpose,
            'description' => "Собрано через чат-бот NeuroPC. Бюджет: " .
                number_format($budget, 0, ',', ' ') . " ₽",
            ...$componentIds
        ];

        Log::info('Данные для создания сборки из чата', $data);

        return $this->createBuild($data, $userId);
    }
}
