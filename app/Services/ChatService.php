<?php

namespace App\Services;

use App\Models\Chat;
use Illuminate\Support\Facades\Log;

class ChatService
{
    protected $pythonBridge;

    public function __construct(PythonBridgeService $pythonBridge)
    {
        $this->pythonBridge = $pythonBridge;
    }

    /**
     * Получить последние сообщения из чата для контекста
     */
    protected function getRecentHistory(Chat $chat, int $limit = 5): array
    {
        return $chat->messages()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(function ($message) {
                return [
                    'sender' => $message->sender,
                    'message' => $message->message,
                    'created_at' => $message->created_at->toDateTimeString()
                ];
            })
            ->toArray();
    }

    public function processUserMessage(Chat $chat, string $message): array
    {
        Log::info('ChatService: Отправка сообщения в BobaAI', [
            'message' => $message,
            'chat_id' => $chat->id,
            'budget' => $chat->budget,
            'purpose' => $chat->purpose
        ]);

        // 1. Собираем контекст - ДОБАВЛЯЕМ chat_id и user_id
        $context = [
            'current_budget' => $chat->budget,
            'current_purpose' => $chat->purpose,
            'chat_id' => $chat->id,
            'user_id' => $chat->user_id,
            'reset_on_new_query' => true // Флаг для сброса старого контекста
        ];

        // 2. Отправляем в BobaAI микросервис
        $result = $this->pythonBridge->neuralChatProcessor($message, $context);

        // ДОБАВЛЯЕМ: Обновление данных в БД если нужно
        if (isset($result['should_update_db']) && $result['should_update_db'] && !empty($result['update_db_data'])) {
            try {
                Log::info('ChatService: Обновление данных чата в БД', [
                    'chat_id' => $chat->id,
                    'update_data' => $result['update_db_data']
                ]);

                // Обновляем запись в базе данных
                $chat->update($result['update_db_data']);

                Log::info('ChatService: Данные чата успешно обновлены', [
                    'chat_id' => $chat->id,
                    'new_budget' => $chat->budget,
                    'new_purpose' => $chat->purpose
                ]);

            } catch (\Exception $e) {
                Log::error('ChatService: Ошибка обновления данных чата', [
                    'chat_id' => $chat->id,
                    'error' => $e->getMessage(),
                    'update_data' => $result['update_db_data']
                ]);
            }
        }

        // 3. Если ошибка - запасной ответ
        if (!($result['success'] ?? false)) {
            Log::error('BobaAI вернул ошибку');
            return [
                'type' => 'fallback',
                'message' => $this->getFallbackResponse($chat),
                'neural_processing' => false,
                'model_used' => 'fallback'
            ];
        }

        // 4. Возвращаем результат с компонентами
        return [
            'type' => $result['intent'] ?? 'general',
            'message' => $result['message'] ?? 'Ответ от нейросети',
            'components' => $result['components'] ?? [],
            'total_price' => $result['total_price'] ?? 0,
            'budget_utilization' => $result['budget_utilization'] ?? 0,
            'neural_data' => $result,
            'neural_processing' => $result['neural_processing'] ?? false,
            'model_used' => $result['model_used'] ?? 'unknown'
        ];
    }

    /**
     * Запасной ответ (если нейросеть не работает)
     */
    protected function getFallbackResponse(Chat $chat): string
    {
        return "🧠 **Нейросеть временно недоступна**\n\n" .
            "Текущие параметры:\n" .
            "• Бюджет: " . number_format($chat->budget, 0, ',', ' ') . " ₽\n" .
            "• Цель: {$chat->purpose}\n\n" .
            "Вы можете:\n" .
            "• Попробовать позже\n" .
            "• Использовать автоматический подбор\n" .
            "• Написать 'подбери сборку' для ручного подбора";
    }
}
