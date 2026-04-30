<?php

namespace App\Http\Controllers;

use App\Models\Build;
use App\Models\Chat;
use App\Models\Review;
use App\Services\BuildService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    protected $buildService;

    public function __construct(BuildService $buildService)
    {
        $this->buildService = $buildService;
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        $builds = $user->builds()
            ->with(['cpu', 'motherboard', 'ram', 'gpu', 'powerSupply', 'cpuCooler', 'case', 'storage'])
            ->latest()
            ->paginate(2);

        $builds->getCollection()->transform(function ($build) {
            return $this->mapLoadedComponents($build);
        });

        if ($request->ajax()) {
            return response()->json([
                'html' => view('profile.partials.build-cards', ['builds' => $builds])->render(),
                'has_more' => $builds->hasMorePages(),
                'next_page' => $builds->currentPage() + 1,
            ]);
        }

        return view('profile.index', [
            'user' => $user,
            'builds' => $builds,
            'reviews' => $user->reviews()->with('build')->latest()->get(),
            'chats' => $user->chats()->count(),
        ]);
    }

    /**
     * Маппит уже загруженные eager-связи в loadedComponents для совместимости с видами
     */
    private function mapLoadedComponents(Build $build): Build
    {
        $components = [];

        if ($build->cpu)          $components['cpu']          = $build->cpu;
        if ($build->motherboard)  $components['motherboard']  = $build->motherboard;
        if ($build->gpu)          $components['gpu']          = $build->gpu;
        if ($build->powerSupply)  $components['power_supply'] = $build->powerSupply;
        if ($build->cpuCooler)    $components['cpu_cooler']   = $build->cpuCooler;
        if ($build->case)         $components['case']         = $build->case;
        if ($build->storage)      $components['storage']      = $build->storage;

        if ($build->ram) {
            $build->ram->quantity = $build->ram_quantity ?? 1;
            $build->ram->config   = $build->ram_config   ?? 'dual';
            $components['ram']    = $build->ram;
        }

        $build->loadedComponents = $components;

        return $build;
    }

    public function update(Request $request)
    {
        $user = Auth::user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:50',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'current_password' => 'nullable|required_with:password',
            'password' => 'nullable|min:8|confirmed',
        ], [
            'name.required' => 'Введите имя',
            'name.max' => 'Имя максимально вмещает 50 символов',
            'email.required' => 'Введите email',
            'email.email' => 'Введите корректный email',
            'email.unique' => 'Этот email уже занят',
            'current_password.required_with' => 'Для смены пароля введите текущий пароль',
            'password.min' => 'Пароль должен содержать минимум 8 символов',
            'password.confirmed' => 'Пароли не совпадают',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $passwordChanged = false;

        // Если указан новый пароль
        if ($request->filled('password')) {
            if (!$request->filled('current_password')) {
                return response()->json([
                    'success' => false,
                    'errors' => ['current_password' => ['Для смены пароля введите текущий пароль']]
                ], 422);
            }

            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'errors' => ['current_password' => ['Текущий пароль неверен']]
                ], 422);
            }

            $passwordChanged = true;
        }

        // Обновляем данные
        $data = [
            'name' => $request->name,
            'email' => $request->email,
        ];

        // Обновляем пароль только если он указан
        if ($passwordChanged) {
            $data['password'] = Hash::make($request->password);
        }

        $user->update($data);

        // Возвращаем ответ с флагом изменения пароля
        return response()->json([
            'success' => true,
            'message' => 'Профиль обновлен успешно!',
            'password_changed' => $passwordChanged,
            'user' => [
                'name' => $user->name,
                'email' => $user->email
            ]
        ]);
    }

// Вспомогательная функция для ошибок пароля
    private function passwordErrorResponse(string $message, Request $request)
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => false,
                'errors' => ['current_password' => [$message]]
            ], 422);
        }

        return back()->withErrors(['current_password' => $message])->withInput();
    }

    public function exportPdf(Build $build)
    {
        if ($build->user_id !== Auth::id()) {
            abort(403, 'Нет доступа к этой сборке');
        }

        $tableMap = [
            'Процессор'        => ['field' => 'cpu_id',          'table' => 'cpus'],
            'Материнская плата'=> ['field' => 'motherboard_id',   'table' => 'motherboards'],
            'Оперативная память'=> ['field' => 'ram_id',          'table' => 'rams'],
            'Видеокарта'       => ['field' => 'gpu_id',           'table' => 'gpus'],
            'Накопитель'       => ['field' => 'storage_id',       'table' => 'storages'],
            'Блок питания'     => ['field' => 'power_supply_id',  'table' => 'psus'],
            'Корпус'           => ['field' => 'case_id',          'table' => 'cases'],
            'Кулер'            => ['field' => 'cpu_cooler_id',    'table' => 'coolers'],
        ];

        $components = [];
        foreach ($tableMap as $label => $info) {
            $fieldValue = $build->{$info['field']};
            if ($fieldValue) {
                $component = DB::connection('sqlite_components')
                    ->table($info['table'])
                    ->where('id', $fieldValue)
                    ->first();
                if ($component) {
                    if ($label === 'Оперативная память') {
                        $component->quantity = $build->ram_quantity ?? 1;
                    }
                    $components[$label] = $component;
                }
            }
        }

        $pdf = Pdf::loadView('profile.build-pdf', compact('build', 'components'))
            ->setPaper('a4', 'portrait');

        $filename = 'build-' . $build->id . '-' . now()->format('Ymd') . '.pdf';

        return $pdf->download($filename);
    }

    public function builds()
    {
        return redirect()->route('profile');
    }

    public function destroy(Build $build)
    {
        if ($build->user_id !== Auth::id()) {
            abort(403, 'Недостаточно прав');
        }

        $build->delete();

        return back()->with('success', 'Сборка удалена успешно!');
    }

    public function addReview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'build_id' => 'required|integer|exists:builds,id',
            'title' => 'required|string|max:255',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|max:1000',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Проверяем, что сборка принадлежит пользователю
        $build = Build::where('id', $request->build_id)
            ->where('user_id', Auth::id())
            ->first();

        if (!$build) {
            return back()->with('error', 'Сборка не найдена или нет доступа');
        }

        Review::create([
            'user_id' => Auth::id(),
            'build_id' => $request->build_id,
            'title' => $request->title,
            'rating' => $request->rating,
            'comment' => $request->comment,
        ]);

        return back()->with('success', 'Отзыв добавлен успешно!');
    }

    public function saveFromChat(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'components' => 'required|array',
            'chat_id' => 'required|integer|exists:chats,id',
        ]);

        $chat = Chat::find($request->chat_id);

        if ($chat->user_id !== Auth::id()) {
            abort(403, 'Нет доступа к этому чату');
        }

        try {
            $build = $this->buildService->saveFromChat(
                $request->components,
                $request->name,
                Auth::id(),
                $chat->budget,
                $chat->purpose
            );

            return response()->json([
                'success' => true,
                'build_id' => $build->id,
                'message' => 'Сборка сохранена успешно!',
                'redirect' => route('profile')
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
