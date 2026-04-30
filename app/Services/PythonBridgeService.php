<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;

class PythonBridgeService
{
    protected $pythonPath;
    protected $scriptsPath;

    public function __construct()
    {
        $this->pythonPath = config('services.python.path', 'python3');
        $this->scriptsPath = config('services.python.scripts_path', base_path('python_scripts'));
    }

    /**
     * Основной метод вызова Python скриптов
     */
    public function callScript(string $scriptName, array $args = []): array
    {
        $scriptPath = $this->scriptsPath . '/' . $scriptName;

        Log::info('PythonBridge: Запуск скрипта', [
            'python' => $this->pythonPath,
            'script' => $scriptName,
            'script_path' => $scriptPath,
            'exists' => file_exists($scriptPath)
        ]);

        if (!file_exists($scriptPath)) {
            return [
                'success' => false,
                'error' => "Скрипт не найден: {$scriptPath}"
            ];
        }

        // Создаем временный файл с UTF-8
        $tempFile = tempnam(sys_get_temp_dir(), 'pyargs_');
        file_put_contents($tempFile, json_encode($args, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        try {
            // Настройки окружения
            $env = array_merge($_SERVER, [
                'PYTHONIOENCODING' => 'utf-8',
                'PYTHONUTF8' => '1',
                'LC_ALL' => 'en_US.UTF-8',
                'LANG' => 'en_US.UTF-8'
            ]);

            $process = new Process([
                $this->pythonPath,
                $scriptPath,
                $tempFile
            ], null, $env, null, 60);

            $process->run();

            $output = $process->getOutput();
            $errorOutput = $process->getErrorOutput();
            $exitCode = $process->getExitCode();

            Log::info('PythonBridge: Результат выполнения', [
                'exit_code' => $exitCode,
                'output_preview' => mb_substr($output, 0, 200),
                'error_preview' => mb_substr($errorOutput, 0, 200)
            ]);

            // Удаляем временный файл
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }

            if (!$process->isSuccessful()) {
                return [
                    'success' => false,
                    'error' => 'Скрипт завершился с ошибкой',
                    'exit_code' => $exitCode,
                    'python_error' => $this->cleanUtf8($errorOutput),
                    'raw_output' => $this->cleanUtf8($output)
                ];
            }

            // Чистим и парсим вывод
            $output = $this->cleanUtf8($output);
            $output = trim($output);

            if (empty($output)) {
                return [
                    'success' => false,
                    'error' => 'Скрипт не вернул данных',
                    'raw_output' => 'пусто'
                ];
            }

            // Пробуем найти JSON в выводе
            $jsonStart = strpos($output, '{');
            $jsonEnd = strrpos($output, '}');

            if ($jsonStart !== false && $jsonEnd !== false) {
                $output = substr($output, $jsonStart, $jsonEnd - $jsonStart + 1);
            }

            $result = json_decode($output, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                Log::error('PythonBridge: Ошибка парсинга JSON', [
                    'error' => json_last_error_msg(),
                    'raw_output' => mb_substr($output, 0, 500)
                ]);

                return [
                    'success' => false,
                    'error' => 'Ошибка парсинга JSON: ' . json_last_error_msg(),
                    'raw_output' => mb_substr($output, 0, 500)
                ];
            }

            return $result;

        } catch (\Exception $e) {
            Log::error('PythonBridge: Исключение', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Исключение PHP: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Очищает строку от невалидных UTF-8 символов
     */
    private function cleanUtf8(string $string): string
    {
        // Удаляем BOM
        $string = str_replace("\xEF\xBB\xBF", '', $string);

        // Преобразуем к UTF-8 если нужно
        if (!mb_check_encoding($string, 'UTF-8')) {
            $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        }

        // Удаляем невидимые символы
        $string = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $string);

        return trim($string);
    }

    /**
     * Обработка чата через нейросеть - ГЛАВНЫЙ МЕТОД
     */
    public function neuralChatProcessor(string $message, array $context = []): array
    {
        Log::info('Запуск нейросети для сообщения', ['message' => $message]);

        // Пробуем микросервис
        $microserviceResult = $this->callMicroservice($message, $context);

        if ($microserviceResult['success']) {
            Log::info('Микросервис успешно обработал запрос', [
                'intent' => $microserviceResult['intent'] ?? null,
                'neural_processing' => $microserviceResult['neural_processing'] ?? false
            ]);
            return $microserviceResult;
        }

        // Если микросервис недоступен, пробуем резервный вариант
        Log::warning('Использую резервный PHP классификатор');
        return $this->getFallbackResponse($message, $context);
    }

    /**
     * Вызов микросервиса нейросети
     */
    private function callMicroservice(string $message, array $context = []): array
    {
        try {
            $url = 'http://127.0.0.1:8765/predict';

            $data = [
                'message' => $message,
                'context' => $context
            ];

            $ch = curl_init($url);

            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($data, JSON_UNESCAPED_UNICODE),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Accept: application/json'
                ],
                CURLOPT_TIMEOUT => 10,
                CURLOPT_CONNECTTIMEOUT => 3
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            Log::info('PythonBridge: Ответ микросервиса', [
                'http_code' => $httpCode,
                'error' => $error,
                'response_preview' => substr($response, 0, 200)
            ]);

            if ($httpCode === 200 && $response) {
                $result = json_decode($response, true);
                if ($result && ($result['success'] ?? false)) {
                    return $result;
                }
            }

            Log::warning('Микросервис недоступен или вернул ошибку', [
                'http_code' => $httpCode,
                'error' => $error
            ]);

            return [
                'success' => false,
                'error' => 'Микросервис недоступен'
            ];

        } catch (\Exception $e) {
            Log::error('Ошибка подключения к микросервису', [
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Ошибка подключения'
            ];
        }
    }

    /**
     * Резервный ответ на PHP
     */
    private function getFallbackResponse(string $message, array $context = []): array
    {
        $messageLower = mb_strtolower($message);
        $intent = 'general';
        $confidence = 0.5;

        if (str_contains($messageLower, 'привет') || str_contains($messageLower, 'здравствуй')) {
            $intent = 'greeting';
            $confidence = 0.9;
        } elseif (str_contains($messageLower, 'подбери') || str_contains($messageLower, 'собери') || str_contains($messageLower, 'рекомендуй')) {
            $intent = 'build_recommendation';
            $confidence = 0.85;
        } elseif (preg_match('/(бюджет|цена|стоимость|₽|руб|к[^а-я]|тыс)/u', $messageLower)) {
            $intent = 'budget_change';
            $confidence = 0.8;
        } elseif (str_contains($messageLower, 'процессор') || str_contains($messageLower, 'видеокарт') || str_contains($messageLower, 'памят')) {
            $intent = 'component_query';
            $confidence = 0.75;
        }

        // Простой ответ
        $response = "⚠️ **Микросервис нейросети временно недоступен**\n\n";
        $response .= "Работает резервный режим.\n";
        $response .= "Интент: {$intent}\n\n";

        if (isset($context['current_budget'])) {
            $response .= "Текущий бюджет: " . number_format($context['current_budget'], 0, ',', ' ') . " ₽\n";
        }

        $response .= "\nПопробуйте позже или используйте автоматический подбор.";

        return [
            'success' => true,
            'intent' => $intent,
            'intent_confidence' => $confidence,
            'message' => $response,
            'neural_processing' => false,
            'model_used' => 'PHP Fallback',
            'should_recommend' => $intent === 'build_recommendation'
        ];
    }

    /**
     * Рекомендация сборки по параметрам
     */
    public function recommendBuild(float $budget, string $purpose = 'gaming', array $preferences = []): array
    {
        return $this->callScript('pc_recommendation_engine.py', [
            'action' => 'recommend_build',
            'budget' => $budget,
            'purpose' => $purpose,
            'preferences' => $preferences,
            'db_path' => database_path('pc_builder.db')
        ]);
    }

    /**
     * Проверка совместимости компонентов
     */
    public function checkCompatibility(array $components): array
    {
        return $this->callScript('check_compatibility.py', [
            'components' => $components,
            'db_path' => database_path('pc_builder.db')
        ]);
    }
}
