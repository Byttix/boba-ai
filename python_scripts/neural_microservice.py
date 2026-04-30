#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
МИКРОСЕРВИС НЕЙРОСЕТИ BOBAAI - ЧИСТЫЙ ВЫВОД
"""

import sys
import os
import json
import re
import subprocess
import tempfile
import sqlite3
from flask import Flask, request, jsonify
from flask_cors import CORS

app = Flask(__name__)
CORS(app)

print("[BOBAAI MICROSERVICE] Запуск микросервиса...", file=sys.stderr)

# ============ ПОДКЛЮЧЕНИЕ БАЗЫ ИГР ============
_SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
# На сервере задай переменную окружения: LARAVEL_DB_PATH=/var/www/laravel/database
_db_dir = os.environ.get('LARAVEL_DB_PATH') or os.path.normpath(os.path.join(_SCRIPT_DIR, '..', 'database'))
GAMES_DB_PATH = os.path.join(_db_dir, 'games.db')
PC_BUILDER_DB_PATH = os.path.join(_db_dir, 'pc_builder.db')

print(f"[DB] Директория скриптов: {_SCRIPT_DIR}", file=sys.stderr)
print(f"[DB] Папка с БД: {_db_dir}", file=sys.stderr)
print(f"[DB] БД компонентов: {PC_BUILDER_DB_PATH} (существует: {os.path.exists(PC_BUILDER_DB_PATH)})", file=sys.stderr)
print(f"[GAMES] БД игр: {GAMES_DB_PATH} (существует: {os.path.exists(GAMES_DB_PATH)})", file=sys.stderr)

games_dict = {}

def load_games():
    global games_dict
    if not os.path.exists(GAMES_DB_PATH):
        print(f"[WARN] База игр не найдена: {GAMES_DB_PATH}", file=sys.stderr)
        return
    try:
        conn = sqlite3.connect(GAMES_DB_PATH)
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()
        cursor.execute("""
            SELECT name, aliases, min_cpu_cores, rec_cpu_cores,
                   min_gpu_vram_gb, rec_gpu_vram_gb,
                   min_ram_gb, rec_ram_gb, storage_gb, year
            FROM games
        """)
        rows = cursor.fetchall()
        conn.close()

        for row in rows:
            game_data = dict(row)
            key = game_data['name'].lower()
            games_dict[key] = game_data
            if game_data['aliases']:
                for alias in game_data['aliases'].split(','):
                    games_dict[alias.strip().lower()] = game_data
        print(f"[INFO] Загружено {len(rows)} игр, всего ключей: {len(games_dict)}", file=sys.stderr)
    except Exception as e:
        print(f"[ERROR] Не удалось загрузить игры: {e}", file=sys.stderr)

load_games()

def extract_pinned_components(text: str, db_path: str) -> dict:
    """Ищет упоминания конкретных компонентов в тексте пользователя"""
    pinned = {}
    if not os.path.exists(db_path):
        return pinned

    text_lower = text.lower()
    candidates = []

    for pattern in [
        r'(?:amd\s+)?ryzen\s*\d+\s*\w*\d*\w*',
        r'(?:intel\s+)?(?:core\s+)?i[3579][\s\-]\d{4,5}\w*',
    ]:
        for m in re.finditer(pattern, text_lower):
            candidates.append(('cpu', m.group(0).strip()))

    for pattern in [
        r'(?:nvidia\s+)?(?:geforce\s+)?rtx\s*\d{4}\s*\w*',
        r'(?:nvidia\s+)?(?:geforce\s+)?gtx\s*\d{4}\s*\w*',
        r'(?:amd\s+)?(?:radeon\s+)?rx\s*\d{4}\s*\w*',
    ]:
        for m in re.finditer(pattern, text_lower):
            candidates.append(('gpu', m.group(0).strip()))

    if not candidates:
        return pinned

    try:
        conn = sqlite3.connect(db_path)
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()
        for comp_type, term in candidates:
            if comp_type in pinned:
                continue
            table = 'cpus' if comp_type == 'cpu' else 'gpus'
            try:
                if comp_type == 'cpu':
                    # Предпочитаем CPU без встроенной графики (integrated_graphics ASC = 0 раньше 1)
                    cursor.execute(
                        "SELECT * FROM cpus WHERE LOWER(name) LIKE ? AND price > 0 ORDER BY integrated_graphics ASC, price DESC LIMIT 1",
                        (f'%{term}%',)
                    )
                else:
                    cursor.execute(
                        f"SELECT * FROM {table} WHERE LOWER(name) LIKE ? AND price > 0 ORDER BY price DESC LIMIT 1",
                        (f'%{term}%',)
                    )
                row = cursor.fetchone()
                if row:
                    pinned[comp_type] = dict(row)
                    print(f"[PIN] Зафиксирован {comp_type}: {pinned[comp_type]['name']}", file=sys.stderr)
            except Exception as e:
                print(f"[WARN] PIN query error: {e}", file=sys.stderr)
        conn.close()
    except Exception as e:
        print(f"[WARN] extract_pinned error: {e}", file=sys.stderr)

    return pinned

def extract_game_from_text(text):
    text_lower = text.lower()
    for key in sorted(games_dict.keys(), key=len, reverse=True):
        if key in text_lower:
            return games_dict[key]
    return None
# ============ КОНЕЦ БЛОКА ============

# Пытаемся загрузить нейросеть
try:
    print("[INFO] Пытаюсь загрузить transformers...", file=sys.stderr)
    import torch
    from transformers import DistilBertTokenizer, DistilBertForSequenceClassification
    NEURAL_AVAILABLE = True
    print("[SUCCESS] Нейросеть доступна!", file=sys.stderr)

    script_dir = os.path.dirname(os.path.abspath(__file__))
    model_path = os.path.join(script_dir, "models", "intent_classifier")
    if not os.path.exists(model_path):
        model_path = os.path.join(script_dir, "..", "models", "intent_classifier")

    print(f"[INFO] Загружаю модель из: {model_path}", file=sys.stderr)

    if os.path.exists(model_path):
        tokenizer = DistilBertTokenizer.from_pretrained(model_path, local_files_only=True)
        model = DistilBertForSequenceClassification.from_pretrained(model_path, local_files_only=True)
        model.to(torch.device("cpu"))
        model.eval()

        mapping_file = os.path.join(model_path, "intent_mapping.json")
        if os.path.exists(mapping_file):
            with open(mapping_file, 'r', encoding='utf-8') as f:
                mapping = json.load(f)
                id_to_intent = mapping['id_to_intent']
        else:
            id_to_intent = {
                "0": "greeting", "1": "build_recommendation",
                "2": "component_query", "3": "budget_change",
                "4": "thanks", "5": "help", "6": "general",
                "7": "upgrade_request", "8": "unknown"
            }

        print(f"[SUCCESS] Модель загружена! Классы: {len(id_to_intent)}", file=sys.stderr)
        MODEL_LOADED = True
    else:
        print(f"[ERROR] Модель не найдена: {model_path}", file=sys.stderr)
        MODEL_LOADED = False

except Exception as e:
    print(f"[ERROR] Нейросеть недоступна: {e}", file=sys.stderr)
    NEURAL_AVAILABLE = False
    MODEL_LOADED = False


def get_recommendation(budget: float, purpose: str, db_path: str = None, game_req: dict = None, pinned_components: dict = None) -> dict:
    """Вызывает скрипт подбора сборки"""
    try:
        min_budgets = {
            'gaming': 40000,
            'office': 30000,
            'workstation': 60000,
            'streaming': 50000
        }
        min_budget = min_budgets.get(purpose, 30000)
        if budget < min_budget:
            return {
                'success': False,
                'error': f'Минимальный бюджет для {purpose} сборки: {min_budget:,.0f} ₽'.replace(',', ' ')
            }

        print(f"[RECOMMENDATION] Запускаю подбор: budget={budget}, purpose={purpose}", file=sys.stderr)

        script_dir = os.path.dirname(os.path.abspath(__file__))
        script_path = os.path.join(script_dir, "pc_recommendation_engine.py")
        if not os.path.exists(script_path):
            print(f"[ERROR] Скрипт не найден: {script_path}", file=sys.stderr)
            return {'success': False, 'error': f'Скрипт подбора не найден: {script_path}'}

        if db_path is None:
            db_path = PC_BUILDER_DB_PATH

        print(f"[RECOMMENDATION] Использую БД: {db_path}", file=sys.stderr)

        args = {
            'action': 'recommend_build',
            'budget': budget,
            'purpose': purpose,
            'db_path': db_path
        }
        if game_req:
            args['game_requirements'] = game_req
            print(f"[RECOMMENDATION] Передаю требования игры: {game_req.get('name')}", file=sys.stderr)
        if pinned_components:
            args['pinned_components'] = pinned_components
            print(f"[RECOMMENDATION] Зафиксированные компоненты: {list(pinned_components.keys())}", file=sys.stderr)

        with tempfile.NamedTemporaryFile(mode='w', suffix='.json', delete=False, encoding='utf-8') as f:
            json.dump(args, f, ensure_ascii=False, indent=2)
            temp_path = f.name

        process = subprocess.run(
            [sys.executable, script_path, temp_path],
            capture_output=True,
            text=True,
            encoding='utf-8',
            timeout=30
        )

        os.unlink(temp_path)

        if process.stderr:
            print(f"[RECOMMENDATION] STDERR: {process.stderr[:500]}", file=sys.stderr)

        if process.returncode != 0:
            print(f"[ERROR] Ошибка скрипта: {process.stderr}", file=sys.stderr)
            return {'success': False, 'error': f'Ошибка скрипта: {process.stderr[:200]}'}

        try:
            result = json.loads(process.stdout)
            return result
        except json.JSONDecodeError as e:
            print(f"[ERROR] Ошибка парсинга JSON: {e}", file=sys.stderr)
            print(f"[DEBUG] Вывод: {process.stdout[:500]}", file=sys.stderr)
            return {'success': False, 'error': f'Ошибка парсинга JSON: {str(e)}'}

    except subprocess.TimeoutExpired:
        return {'success': False, 'error': 'Таймаут при подборе сборки (30 секунд)'}
    except Exception as e:
        import traceback
        print(f"[ERROR] Исключение: {traceback.format_exc()}", file=sys.stderr)
        return {'success': False, 'error': f'Исключение: {str(e)}'}


def extract_params(text):
    budget = 0
    purpose = None
    text_lower = text.lower()
    patterns = [
        (r'(\d+)\s*(?:тыс|тысяч|к)\b', 1000),
        (r'(\d+)\s*(?:₽|руб|рублей|р\.?)\b', 1),
        (r'за\s*(\d+)', 1),
        (r'бюджет[^\d]*(\d+)', 1),
        (r'(\d{4,})\b', 1)
    ]
    text_clean = re.sub(r'(\d)\s+(\d)', r'\1\2', text_lower)
    for pattern, multiplier in patterns:
        match = re.search(pattern, text_clean)
        if match:
            try:
                budget = int(match.group(1)) * multiplier
                break
            except ValueError:
                continue

    purpose_keywords = {
        'gaming': ['игр', 'game', 'gaming', 'игра', 'игровой'],
        'office': ['офис', 'office', 'работа', 'рабочий', 'офисный'],
        'workstation': ['рабочая станция', 'workstation', 'дизайн', 'монтаж', '3d', 'видеомонтаж'],
        'streaming': ['стрим', 'stream', 'streaming', 'трансляц', 'вещание']
    }
    for p_type, keywords in purpose_keywords.items():
        for kw in keywords:
            if kw in text_lower:
                purpose = p_type
                return budget, purpose
    return budget, purpose


def simple_classifier(text):
    text_lower = text.lower()
    if any(w in text_lower for w in ['привет', 'здравствуй', 'здравствуйте', 'добрый', 'hello', 'hi']):
        return "greeting", 0.9
    if any(w in text_lower for w in ['апгрейд', 'улучши', 'улучшить', 'обнови', 'апгрейднуть', 'модернизир', 'заменить компонент', 'что обновить', 'что заменить']):
        return "upgrade_request", 0.85
    if any(w in text_lower for w in ['подбери', 'собери', 'рекомендуй', 'посоветуй']):
        return "build_recommendation", 0.85
    if any(w in text_lower for w in ['процессор', 'видеокарт', 'памят', 'ssd', 'материнск', 'ram']):
        return "component_query", 0.8
    if any(w in text_lower for w in ['бюджет', 'цена', 'стоимость', '₽', 'руб', 'к', 'тыс']):
        return "budget_change", 0.8
    if any(w in text_lower for w in ['спасибо', 'благодарю', 'thanks']):
        return "thanks", 0.95
    if any(w in text_lower for w in ['помощь', 'помоги', 'help', 'как', 'подскажи']):
        return "help", 0.85
    return "general", 0.5


def generate_response(intent, confidence, text, context, budget, purpose):
    """Возвращает кортеж (ответ_текст, результат_подбора)"""
    purpose_text = {
        'gaming': 'игрового',
        'office': 'офисного',
        'workstation': 'рабочей станции',
        'streaming': 'стримингового'
    }.get(purpose, purpose)

    game_info = None
    if intent == "build_recommendation":
        game_info = extract_game_from_text(text)
        if game_info:
            print(f"[GAME] Найдена игра: {game_info['name']}", file=sys.stderr)

    if intent == "greeting":
        response = "BobaAI приветствует вас!\n\n"
        response += "Я могу:\n- Подобрать сборку ПК по бюджету\n- Помочь с выбором компонентов\n- Проверить совместимость\n- Ответить на вопросы\n\n"
        if context.get('current_budget'):
            response += f"Текущие настройки:\nБюджет: {int(context['current_budget']):,} ₽\nЦель: {context.get('current_purpose', 'не указана')}\n\n"
        response += "Примеры запросов:\n- подбери игровой пк за 100000\n- офисный компьютер за 50000\n- какой процессор лучше?"
        return response, None

    elif intent == "build_recommendation":
        # Если игра найдена, бюджет переопределяется (игнорируем старый)
        if game_info:
            year = game_info.get('year', 2020)
            if year >= 2020:
                new_budget = 150000
            elif year >= 2015:
                new_budget = 100000
            else:
                new_budget = 75000
            budget = new_budget
            print(f"[GAME] Установлен бюджет {budget} для игры {game_info['name']} (год {year})", file=sys.stderr)

        if budget <= 0:
            budget = context.get('current_budget', 100000)

        if budget < 20000:
            response = f"Минимальный бюджет: 20 000 ₽\nВы указали: {budget:,} ₽\nДля сборки рабочего ПК нужен бюджет от 20 000 ₽.\nПожалуйста, увеличьте бюджет."
            return response, None

        db_path = PC_BUILDER_DB_PATH

        pinned = extract_pinned_components(text, db_path)

        game_req = None
        if game_info:
            game_req = {
                'name': game_info['name'],
                'min_cpu_cores': game_info.get('min_cpu_cores'),
                'rec_cpu_cores': game_info.get('rec_cpu_cores'),
                'min_gpu_vram_gb': game_info.get('min_gpu_vram_gb'),
                'rec_gpu_vram_gb': game_info.get('rec_gpu_vram_gb'),
                'min_ram_gb': game_info.get('min_ram_gb'),
                'rec_ram_gb': game_info.get('rec_ram_gb'),
                'storage_gb': game_info.get('storage_gb'),
            }

        recommendation_result = get_recommendation(
            budget, purpose, db_path,
            game_req=game_req,
            pinned_components=pinned if pinned else None
        )

        if recommendation_result.get('success', False):
            recommendation_result['used_budget'] = budget
            response = recommendation_result['message']
            if game_info and "для игры" not in response:
                response = response.replace("Я подобрал для вас", f"Я подобрал для вас (для игры «{game_info['name']}»)")
            if pinned:
                type_names = {'cpu': 'Процессор', 'gpu': 'Видеокарта'}
                pin_note = "Зафиксированные компоненты:\n"
                for ct, comp in pinned.items():
                    price_str = f"{comp.get('price', 0):,.0f}".replace(',', ' ')
                    pin_note += f"   • {type_names.get(ct, ct)}: {comp['name']} — {price_str} ₽\n"
                pin_note += "\n"
                response = pin_note + response
            return response, recommendation_result
        else:
            error_msg = recommendation_result.get('error', 'Неизвестная ошибка')
            response = f"Не удалось подобрать сборку\n\nПричина: {error_msg}\n\n"
            if budget >= 50000:
                response += "Демонстрационная сборка (тестовые данные):\n\n"
                response += f"Бюджет: {budget:,} ₽\nЦель: {purpose_text}\n\n"
                response += "Процессор: Intel Core i5-12400F - 15 000 ₽\n"
                response += "Материнская плата: ASUS PRIME B660-PLUS - 10 000 ₽\n"
                response += "Оперативная память: DDR4 16GB 3200MHz - 4 000 ₽\n"
                response += "Видеокарта: NVIDIA RTX 3060 - 30 000 ₽\n"
                response += "Накопитель: SSD 1TB NVMe - 6 000 ₽\n"
                response += "Блок питания: БП 650W 80+ Bronze - 5 000 ₽\n"
                response += "Корпус: Корпус Mid-Tower - 4 000 ₽\n"
                response += "Кулер: Кулер для процессора - 2 000 ₽\n\n"
                response += "Итоговая стоимость: 76 000 ₽\n\n"
                response += "Совет: Для реального подбора заполните базу данных компонентов."
            return response, None

    elif intent == "budget_change":
        if budget > 0:
            old = context.get('current_budget', 0)
            response = f"Новый бюджет: {budget:,} ₽\n".replace(',', ' ')
            if old > 0:
                change = budget - old
                response += f"{'Увеличение' if change > 0 else 'Уменьшение'} на: {abs(change):,} ₽\n".replace(',', ' ')
            response += f"\nНа этот бюджет можно собрать {purpose_text} ПК.\nНапишите 'подбери сборку' для продолжения."
        else:
            response = "Укажите бюджет цифрами (например: 80000 рублей)"
        return response, None

    elif intent == "component_query":
        response = "Рекомендации по выбору компонентов:\n\n"
        response += "Процессор: Для игр - Intel Core i5/i7 или AMD Ryzen 5/7\n"
        response += "Видеокарта: Для 1080p - RTX 3060 / RX 6600\n"
        response += "Оперативная память: 16GB DDR4 3200MHz (2 планки)\n"
        response += "Накопитель: SSD NVMe 1TB для системы\n\n"
        response += "Или напишите 'подбери сборку' для полного подбора."
        return response, None

    elif intent == "upgrade_request":
        response = "Апгрейд сборки\n\n"
        response += "Чтобы предложить улучшения, опишите:\n"
        response += "1. Текущие компоненты (процессор, видеокарта, память)\n"
        response += "2. Бюджет на апгрейд\n"
        response += "3. Цель — больше FPS в играх, скорость работы, монтаж и т.д.\n\n"
        response += "Пример: У меня i5-10400F, GTX 1060 6GB, 16GB DDR4. Бюджет 30 000 рублей. Хочу улучшить для игр."
        return response, None

    elif intent == "unknown":
        response = "Не удалось распознать запрос.\n\n"
        response += "Я специализируюсь на подборе и апгрейде ПК. Попробуйте:\n"
        response += "• Подбери игровой ПК за 100 000 рублей\n"
        response += "• Хочу апгрейд сборки за 30 000\n"
        response += "• Какую видеокарту выбрать для игр\n"
        response += "• Собери офисный компьютер за 50 000"
        return response, None

    elif intent == "thanks":
        response = "Спасибо! Рад был помочь.\n\nЕсли нужно что-то еще - просто напишите."
        return response, None

    elif intent == "help":
        response = "Как пользоваться BobaAI:\n\n"
        response += "1. Укажите бюджет и цель (например: '100000 рублей для игр')\n"
        response += "2. Напишите 'подбери сборку'\n"
        response += "3. Система предложит комплектующие\n"
        response += "4. Вы можете сохранить сборку в профиле\n\n"
        response += "Примеры запросов:\n- Подбери игровой ПК за 100000 рублей\n- Нужен офисный компьютер за 50000\n- Какой процессор лучше для игр?"
        return response, None

    else:
        response = "Я могу помочь с:\n- Подбором сборки ПК\n- Выбором комплектующих\n- Проверкой совместимости\n- Ответами на вопросы\n\nПопробуйте: 'подбери пк за 100000 рублей'"
        return response, None


@app.route('/health', methods=['GET'])
def health_check():
    return jsonify({
        'status': 'ok',
        'neural_available': NEURAL_AVAILABLE,
        'model_loaded': MODEL_LOADED if 'MODEL_LOADED' in locals() else False,
        'service': 'BobaAI Neural Microservice',
        'games_loaded': len(games_dict) > 0
    })


@app.route('/predict', methods=['POST'])
def predict():
    try:
        data = request.get_json()
        if not data:
            return jsonify({'success': False, 'error': 'Нет данных'}), 400

        message = data.get('message', '')
        context = data.get('context', {})

        if not message:
            return jsonify({'success': False, 'error': 'Пустое сообщение'}), 400

        print(f"[BOBAAI] Обработка: {message}", file=sys.stderr)
        print(f"[BOBAAI] Контекст: {context}", file=sys.stderr)

        extracted_budget, extracted_purpose = extract_params(message)

        should_update_db = False
        update_data = {}

        if extracted_budget > 0:
            final_budget = extracted_budget
        else:
            final_budget = context.get('current_budget', 100000)

        if extracted_purpose is not None:
            final_purpose = extracted_purpose
        else:
            final_purpose = context.get('current_purpose', 'gaming')

        if extracted_budget > 0 and extracted_budget != context.get('current_budget'):
            update_data['budget'] = extracted_budget
            should_update_db = True
        if extracted_purpose is not None and extracted_purpose != context.get('current_purpose'):
            update_data['purpose'] = extracted_purpose
            should_update_db = True

        intent = None
        confidence = 0.5
        if MODEL_LOADED:
            try:
                inputs = tokenizer(message, return_tensors="pt", truncation=True, max_length=128)
                with torch.no_grad():
                    outputs = model(**inputs)
                    predictions = torch.nn.functional.softmax(outputs.logits, dim=-1)
                predicted_idx = torch.argmax(predictions, dim=-1).item()
                confidence = predictions[0][predicted_idx].item()
                intent = id_to_intent.get(str(predicted_idx), "general")
                print(f"[NEURAL] Интент: {intent} ({confidence:.1%})", file=sys.stderr)
            except Exception as e:
                print(f"[ERROR] Ошибка нейросети: {e}", file=sys.stderr)
                intent, confidence = simple_classifier(message)
        else:
            intent, confidence = simple_classifier(message)

        response_text, recommendation_result = generate_response(intent, confidence, message, context, final_budget, final_purpose)

        result = {
            'success': True,
            'intent': intent,
            'intent_confidence': confidence,
            'message': response_text,
            'neural_processing': MODEL_LOADED,
            'model_used': 'DistilBERT' if MODEL_LOADED else 'RuleBased',
            'extracted_params': {'budget': final_budget, 'purpose': final_purpose},
            'should_update_context': intent in ['budget_change', 'build_recommendation'],
            'should_update_db': should_update_db,
            'update_db_data': update_data if should_update_db else None,
            'chat_id': context.get('chat_id'),
            'user_id': context.get('user_id')
        }

        # Если есть результат подбора, добавляем компоненты
        if recommendation_result and recommendation_result.get('success'):
            result['components'] = recommendation_result.get('components', {})
            result['total_price'] = recommendation_result.get('total_price', 0)
            result['budget_utilization'] = recommendation_result.get('budget_utilization', 0)
            result['build_budget'] = recommendation_result.get('used_budget', final_budget)

        return jsonify(result)

    except Exception as e:
        import traceback
        print(f"[FATAL ERROR] {traceback.format_exc()}", file=sys.stderr)
        return jsonify({'success': False, 'error': str(e), 'neural_processing': False}), 500


if __name__ == '__main__':
    print("[BOBAAI] Сервис запущен на http://127.0.0.1:8765", file=sys.stderr)
    app.run(host='127.0.0.1', port=8765, debug=False, threaded=True, use_reloader=False)
