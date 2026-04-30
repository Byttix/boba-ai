<?php

namespace App\Http\Controllers;

use App\Models\Build;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Services\ChatService;
use App\Services\ComponentService;
use App\Services\PythonBridgeService;
use App\Services\BuildService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator; // Добавлен импорт

class HomeController extends Controller
{
    protected $componentService;
    protected $pythonBridge;
    protected $buildService;

    public function __construct(
        PythonBridgeService $pythonBridge,
        ComponentService $componentService,
        BuildService $buildService = null
    ) {
        $this->pythonBridge = $pythonBridge;
        $this->componentService = $componentService;
        $this->buildService = $buildService ?? app(BuildService::class);
    }

    /**
     * Главная страница
     */
    public function index()
    {
        $data = [];

        if (Auth::check()) {
            $data['chats'] = Auth::user()->chats()->latest()->take(10)->get();
            $data['builds'] = Auth::user()->builds()->latest()->take(6)->get();
        }

        return view('home', $data);
    }

    /**
     * Начать новый чат
     */
    public function startChat(Request $request)
    {
        $request->validate([
            'budget' => 'required|numeric|min:10000|max:1000000',
            'purpose' => 'required|in:gaming,workstation,office,streaming',
            'title' => 'required|string|max:100',
        ], [
            'budget.required' => 'Укажите бюджет',
            'budget.numeric' => 'Бюджет должен быть числом',
            'budget.min' => 'Бюджет должен быть не менее 10 000 ₽',
            'budget.max' => 'Бюджет не может превышать 1 000 000 ₽',
            'purpose.required' => 'Выберите назначение ПК',
            'purpose.in' => 'Некорректное назначение',
            'title.required' => 'Введите название чата',
            'title.max' => 'Название чата не должно превышать 100 символов',
        ]);

        $chat = Chat::create([
            'title' => $request->title,
            'user_id' => Auth::id(),
            'last_message' => 'Начало диалога',
            'budget' => $request->budget,
            'purpose' => $request->purpose,
        ]);

        return redirect()->route('chat.show', $chat)->with('success', 'Чат создан успешно!');
    }

    /**
     * Страница чата
     */
    public function chat(Chat $chat)
    {
        if ($chat->user_id !== Auth::id()) {
            abort(403, 'Нет доступа к этому чату');
        }

        $messages = ChatMessage::where('chat_id', $chat->id)
            ->latest()
            ->limit(200)
            ->get()
            ->reverse()
            ->values();

        return view('chat', [
            'chat' => $chat,
            'messages' => $messages,
        ]);
    }

    /**
     * Отправить сообщение в чат
     */
    public function sendMessage(Request $request, Chat $chat)
    {
        if ($chat->user_id !== Auth::id()) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['error' => 'Нет доступа к этому чату'], 403);
            }
            abort(403, 'Нет доступа к этому чату');
        }

        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        // Сохраняем сообщение пользователя
        $userMessage = $chat->messages()->create([
            'sender' => 'user',
            'message' => $request->message,
        ]);

        // Обновляем последнее сообщение
        $chat->update([
            'last_message' => Str::limit($request->message, 30),
        ]);

        try {
            // Обработка через Python
            $chatService = app(ChatService::class);
            $response = $chatService->processUserMessage($chat, $request->message);

            // Очищаем сообщение от не-UTF8 символов
            $cleanMessage = $this->cleanString($response['message']);

            // Сохраняем ответ AI
            $aiMessage = $chat->messages()->create([
                'sender' => 'ai',
                'message' => $cleanMessage,
                'metadata' => json_encode($response, JSON_UNESCAPED_UNICODE)
            ]);

            // Если это AJAX запрос (скорее всего, так и есть), возвращаем JSON
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'user_message' => [
                        'id' => $userMessage->id,
                        'message' => $request->message,
                        'created_at' => $userMessage->created_at->toDateTimeString()
                    ],
                    'ai_message' => [
                        'id' => $aiMessage->id,
                        'message' => $cleanMessage,
                        'created_at' => $aiMessage->created_at->toDateTimeString(),
                        'metadata' => $response
                    ],
                    'chat' => [
                        'id' => $chat->id,
                        'last_message' => $chat->last_message
                    ]
                ]);
            }

            // Для не-AJAX запросов (обычная форма) - редирект
            return back()->with('success', 'Сообщение отправлено');

        } catch (\Exception $e) {
            Log::error('Chat error: ', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'chat_id' => $chat->id,
                'user_message' => $request->message,
            ]);

            // Запасной ответ
            $fallback = "Извините, произошла ошибка. " .
                "Ваш бюджет: " . number_format($chat->budget, 0, ',', ' ') . " ₽. " .
                "Цель: {$chat->purpose}.\n\n" .
                "Ошибка: " . substr($e->getMessage(), 0, 200);

            $aiMessage = $chat->messages()->create([
                'sender' => 'ai',
                'message' => $fallback,
            ]);

            // Для AJAX запросов
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'error' => $e->getMessage(),
                    'ai_message' => [
                        'id' => $aiMessage->id,
                        'message' => $fallback,
                        'created_at' => $aiMessage->created_at->toDateTimeString()
                    ]
                ], 500);
            }

            return back()->with('error', 'Произошла ошибка при отправке сообщения');
        }
    }

    /**
     * Очистка строки от не-UTF8 символов
     */
    private function cleanString($string)
    {
        $string = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        $string = preg_replace('/[^\x{0000}-\x{007F}\x{0400}-\x{04FF}\s]/u', '', $string);
        return $string;
    }

    /**
     * Получить компоненты по типу (API для выпадающих списков)
     */
    public function getComponents(Request $request, $type)
    {
        $filters = $request->all();
        $components = $this->componentService->getComponents($type, $filters);

        return response()->json($components);
    }

    /**
     * Проверить совместимость компонентов
     */
    public function checkCompatibility(Request $request)
    {
        $components = $request->validate([
            'cpu_id' => 'required|integer',
            'motherboard_id' => 'required|integer',
            'ram_id' => 'required|integer',
            'ram_quantity' => 'nullable|integer|min:1|max:4',
            'gpu_id' => 'nullable|integer',
            'power_supply_id' => 'nullable|integer',
            'cpu_cooler_id' => 'nullable|integer',
            'case_id' => 'nullable|integer',
            'storage_id' => 'nullable|integer',
        ]);

        try {
            $result = $this->pythonBridge->checkCompatibility($components);

            // Добавляем информацию о компонентах в ответ
            if ($result['success']) {
                $result['message'] = $result['message'] ?? 'Компоненты совместимы';
            } else {
                $result['message'] = $result['error'] ?? 'Не удалось проверить совместимость';
            }

            return response()->json($result);

        } catch (\Exception $e) {
            Log::error('Ошибка проверки совместимости', [
                'error' => $e->getMessage(),
                'components' => $components
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ошибка при проверке совместимости: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Получить рекомендацию сборки
     */
    public function recommendBuild(Request $request)
    {
        $validated = $request->validate([
            'budget' => 'required|numeric|min:10000|max:1000000',
            'purpose' => 'required|in:gaming,workstation,office,streaming',
            'preferences' => 'nullable|array',
        ]);

        $result = $this->pythonBridge->recommendBuild(
            $validated['budget'],
            $validated['purpose'],
            $validated['preferences'] ?? []
        );

        return response()->json($result);
    }

    /**
     * Сохранить сборку из чата
     */
    public function saveBuildFromChat(Request $request, Chat $chat)
    {
        if ($chat->user_id !== Auth::id()) {
            abort(403, 'Нет доступа к этому чату');
        }

        $request->validate([
            'name' => 'required|string|max:255',
        ]);

        // Получаем последнюю рекомендацию от AI
        $lastAiMessage = ChatMessage::where('chat_id', $chat->id)
            ->where('sender', 'ai')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$lastAiMessage || empty($lastAiMessage->metadata)) {
            return back()->with('error', 'Нет рекомендации для сохранения');
        }

        $metadata = json_decode($lastAiMessage->metadata, true);

        if (empty($metadata['components'])) {
            return back()->with('error', 'В рекомендации нет компонентов');
        }

        try {
            $budget = $metadata['neural_data']['build_budget']
                ?? $metadata['build_budget']
                ?? $chat->budget;

            $build = $this->buildService->saveFromChat(
                $metadata['components'],
                $request->name,
                Auth::id(),
                $budget,
                $chat->purpose
            );

            return redirect()->route('profile')
                ->with('success', "Сборка '{$build->name}' сохранена успешно!");

        } catch (\Exception $e) {
            return back()->with('error', 'Ошибка сохранения: ' . $e->getMessage());
        }
    }

    /**
     * Сохранить сборку из формы
     */
    public function saveBuild(Request $request)
    {
        // Преобразуем пустые строки в null для опциональных полей
        $data = $request->all();
        $optionalFields = ['gpu_id', 'power_supply_id', 'cpu_cooler_id', 'case_id', 'storage_id'];

        foreach ($optionalFields as $field) {
            if (isset($data[$field]) && ($data[$field] === '' || $data[$field] === '0' || $data[$field] === 0)) {
                $data[$field] = null;
            }
        }

        // Валидация с учетом nullable полей
        $validator = Validator::make($data, [
            'name' => 'required|string|max:50',
            'description' => 'nullable|string',
            'cpu_id' => 'required|integer|min:1',
            'motherboard_id' => 'required|integer|min:1',
            'ram_id' => 'required|integer|min:1',
            'gpu_id' => 'nullable|integer|min:1',
            'power_supply_id' => 'nullable|integer|min:1',
            'cpu_cooler_id' => 'nullable|integer|min:1',
            'case_id' => 'nullable|integer|min:1',
            'storage_id' => 'nullable|integer|min:1',
            'budget' => 'nullable|numeric|min:1000',
            'purpose' => 'nullable|in:gaming,workstation,office,streaming,other',
        ], [
            'name.max' => 'Максимальная длина названия 50 символов',
            'cpu_id.required' => 'Выберите процессор',
            'cpu_id.min' => 'Выберите процессор',
            'motherboard_id.required' => 'Выберите материнскую плату',
            'motherboard_id.min' => 'Выберите материнскую плату',
            'ram_id.required' => 'Выберите оперативную память',
            'ram_id.min' => 'Выберите оперативную память',
            'gpu_id.min' => 'Некорректный ID видеокарты',
            'power_supply_id.min' => 'Некорректный ID блока питания',
            'cpu_cooler_id.min' => 'Некорректный ID кулера процессора',
            'case_id.min' => 'Некорректный ID корпуса',
            'storage_id.min' => 'Некорректный ID накопителя',
        ]);

        if ($validator->fails()) {
            return back()
                ->withErrors($validator)
                ->withInput();
        }

        try {

            $componentsForCheck = [
                'cpu_id' => $data['cpu_id'],
                'motherboard_id' => $data['motherboard_id'],
                'ram_id' => $data['ram_id'],
                'ram_quantity' => $data['ram_quantity'] ?? 1,
                'gpu_id' => $data['gpu_id'] ?? null,
                'power_supply_id' => $data['power_supply_id'] ?? null,
                'cpu_cooler_id' => $data['cpu_cooler_id'] ?? null,
                'case_id' => $data['case_id'] ?? null,
                'storage_id' => $data['storage_id'] ?? null,
            ];

            $compatibilityResult = $this->pythonBridge->checkCompatibility($componentsForCheck);

            if (!$compatibilityResult['success']) {
                \Log::warning('Сборка не прошла проверку совместимости', [
                    'user_id' => Auth::id(),
                ]);

                return back()
                    ->with('error', 'Компоненты несовместимы: ' . ($compatibilityResult['message'] ?? 'Неизвестная ошибка совместимости'))
                    ->withInput();
            }

            $buildService = app(\App\Services\BuildService::class);
            $build = $buildService->createBuild($data, Auth::id());

            return redirect()->route('profile')
                ->with('success', "Сборка '{$build->name}' сохранена успешно! Стоимость: " . number_format($build->total_price, 0, ',', ' ') . " ₽");

        } catch (\Exception $e) {
            \Log::error('Ошибка сохранения сборки', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            return back()
                ->with('error', 'Не удалось сохранить сборку. Попробуйте ещё раз.')
                ->withInput();
        }
    }

    /**
     * Показать детали компонента
     */
    public function showComponent($type, $id)
    {
        try {
            $tableMap = [
                'cpu' => 'cpus',
                'motherboard' => 'motherboards',
                'ram' => 'rams',
                'gpu' => 'gpus',
                'power_supply' => 'psus',
                'cpu_cooler' => 'coolers',
                'case' => 'cases',
                'storage' => 'storages',
            ];

            if (!isset($tableMap[$type])) {
                abort(404, 'Тип компонента не найден');
            }

            $table = $tableMap[$type];

            // Используем подключение sqlite_components
            $component = DB::connection('sqlite_components')
                ->table($table)
                ->where('id', $id)
                ->first();

            if (!$component) {
                abort(404, 'Компонент не найден');
            }

            // Получаем связанные компоненты
            $relatedComponents = [];

            if ($type === 'cpu' && isset($component->socket)) {
                $relatedComponents = DB::connection('sqlite_components')
                    ->table('motherboards')
                    ->where('socket', 'like', '%' . $component->socket . '%')
                    ->limit(5)
                    ->get();
            }

            return view('components.show', [
                'component' => $component,
                'type' => $type,
                'type_name' => $this->getComponentTypeName($type),
                'related' => $relatedComponents,
            ]);

        } catch (\Exception $e) {
            Log::error('Ошибка показа компонента', [
                'type' => $type,
                'id' => $id,
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Не удалось загрузить данные компонента');
        }
    }

    /**
     * Получить русское название типа компонента
     */
    private function getComponentTypeName($type)
    {
        $names = [
            'cpu' => 'Процессор',
            'motherboard' => 'Материнская плата',
            'ram' => 'Оперативная память',
            'gpu' => 'Видеокарта',
            'power_supply' => 'Блок питания',
            'cpu_cooler' => 'Кулер процессора',
            'case' => 'Корпус',
            'storage' => 'Накопитель',
        ];

        return $names[$type] ?? ucfirst($type);
    }

    /**
     * Быстрый доступ к списку компонентов по типу (для AJAX)
     */
    public function ajaxGetComponents(Request $request)
    {
        $type = $request->get('type');
        $search = $request->get('search', '');

        $tableMap = [
            'cpu' => 'cpus',
            'motherboard' => 'motherboards',
            'ram' => 'rams',
            'gpu' => 'gpus',
            'power_supply' => 'psus',
            'cpu_cooler' => 'coolers',
            'case' => 'cases',
            'storage' => 'storages',
        ];

        if (!isset($tableMap[$type])) {
            return response()->json(['error' => 'Invalid component type'], 400);
        }

        $query = DB::connection('sqlite_components')->table($tableMap[$type]);

        if (!empty($search)) {
            $query->where('name', 'like', '%' . $search . '%');
        }

        $components = $query->select('id', 'name', 'price')
            ->orderBy('price', 'asc')
            ->limit(50)
            ->get();

        return response()->json($components);
    }

    /**
     * API для получения компонентов (для выпадающих списков в модальном окне)
     */
    public function apiGetComponents($type)
    {
        try {
            $tableMap = [
                'cpu' => 'cpus',
                'motherboard' => 'motherboards',
                'ram' => 'rams',
                'gpu' => 'gpus',
                'power_supply' => 'psus',
                'cpu_cooler' => 'coolers',
                'case' => 'cases',
                'storage' => 'storages',
            ];

            if (!isset($tableMap[$type])) {
                return response()->json([
                    'success' => false,
                    'error' => 'Неизвестный тип компонента'
                ], 400);
            }

            $table = $tableMap[$type];

            $components = DB::connection('sqlite_components')
                ->table($table)
                ->select('id', 'name', 'price', 'manufacturer')
                ->orderBy('price', 'asc')
                ->limit(100)
                ->get();

            return response()->json([
                'success' => true,
                'components' => $components
            ]);

        } catch (\Exception $e) {
            Log::error('Ошибка получения компонентов', [
                'type' => $type,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Ошибка загрузки компонентов'
            ], 500);
        }
    }

    /**
     * Получить ссылки на комплектующие сборки
     */
    public function getBuildLinks(Build $build)
    {
        if ($build->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'error' => 'Нет доступа к этой сборке'
            ], 403);
        }

        try {
            $components = [];
            $tableMap = [
                'cpu' => 'cpus',
                'motherboard' => 'motherboards',
                'ram' => 'rams',
                'gpu' => 'gpus',
                'power_supply' => 'psus',
                'cpu_cooler' => 'coolers',
                'case' => 'cases',
                'storage' => 'storages',
            ];

            foreach ($tableMap as $type => $table) {
                $fieldName = $type . '_id';
                if ($build->$fieldName) {
                    $component = DB::connection('sqlite_components')
                        ->table($table)
                        ->where('id', $build->$fieldName)
                        ->select('id', 'name', 'price', 'manufacturer', 'model', 'url')
                        ->first();

                    if ($component) {
                        // Для RAM добавляем информацию о количестве
                        if ($type === 'ram') {
                            $component->quantity = $build->ram_quantity ?? 1;
                        }

                        $components[$type] = $component;
                    }
                }
            }

            return response()->json([
                'success' => true,
                'build' => [
                    'id' => $build->id,
                    'name' => $build->name,
                    'created_at' => $build->created_at->format('d.m.Y'),
                    'total_price' => $build->total_price,
                ],
                'components' => $components
            ]);

        } catch (\Exception $e) {
            Log::error('Ошибка получения ссылок на компоненты', [
                'build_id' => $build->id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Ошибка загрузки данных о компонентах'
            ], 500);
        }
    }

    public function deleteChat(Chat $chat)
    {
        if ($chat->user_id !== Auth::id()) {
            abort(403, 'Нет доступа к этому чату');
        }

        try {
            // Удаляем чат (сообщения удалятся автоматически через каскад)
            $chat->delete();

            return redirect()->route('home')
                ->with('success', 'Чат удален успешно!');

        } catch (\Exception $e) {
            Log::error('Ошибка удаления чата', [
                'chat_id' => $chat->id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return back()->with('error', 'Ошибка при удалении чата: ' . $e->getMessage());
        }
    }
}
