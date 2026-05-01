import sys
import json
import sqlite3
import codecs
import re

if sys.platform == "win32":
    sys.stdout = codecs.getwriter('utf-8')(sys.stdout.buffer, 'strict')
    sys.stderr = codecs.getwriter('utf-8')(sys.stderr.buffer, 'strict')
else:
    sys.stdout.reconfigure(encoding='utf-8')
    sys.stderr.reconfigure(encoding='utf-8')


def check_database(db_path: str):
    try:
        conn = sqlite3.connect(db_path)
        cursor = conn.cursor()
        cpu_count = gpu_count = ram_count = 0
        try:
            cursor.execute("SELECT COUNT(*) FROM cpus")
            cpu_count = cursor.fetchone()[0]
        except: pass
        try:
            cursor.execute("SELECT COUNT(*) FROM gpus")
            gpu_count = cursor.fetchone()[0]
        except: pass
        try:
            cursor.execute("SELECT COUNT(*) FROM rams")
            ram_count = cursor.fetchone()[0]
        except: pass
        conn.close()
        return {
            'exists': True,
            'cpu_count': cpu_count,
            'gpu_count': gpu_count,
            'ram_count': ram_count,
            'total_components': cpu_count + gpu_count + ram_count,
        }
    except Exception as e:
        return {'exists': False, 'error': str(e)}


def find_compatible_cpu_mb_pair(cursor, cpu_budget, mb_budget, require_igpu=False):
    try:
        query = "SELECT * FROM cpus WHERE price <= ? AND price > 0"
        params = [cpu_budget]
        if require_igpu:
            query += " AND integrated_graphics = 1"
        query += " ORDER BY cores DESC, threads DESC, price DESC"
        cursor.execute(query, params)
        cpus = cursor.fetchall()
        for cpu in cpus:
            cpu_dict = dict(cpu)
            cpu_socket = cpu_dict.get('socket')
            if not cpu_socket:
                continue
            compat_query = "SELECT * FROM motherboards WHERE socket = ? AND price <= ? AND price > 0 ORDER BY price DESC"
            cursor.execute(compat_query, (cpu_socket, mb_budget))
            mbs = cursor.fetchall()
            if mbs:
                mb = max(mbs, key=lambda x: x['price'])
                return cpu_dict, dict(mb)
        return None, None
    except Exception:
        return None, None


def find_psu_by_power(cursor, min_power, max_price):
    try:
        exclude = ['ssd', 'hdd', 'накопитель', 'диск', 'drive', 'nvme', 'sata', 'm.2', 'твердотельный', 'жёсткий']
        query = "SELECT * FROM psus WHERE price <= ? AND price > 0 AND power >= ?"
        params = [max_price, min_power]
        for kw in exclude:
            query += " AND LOWER(name) NOT LIKE ?"
            params.append(f'%{kw.lower()}%')
        query += " ORDER BY power DESC, price DESC LIMIT 1"
        cursor.execute(query, params)
        res = cursor.fetchone()
        if res:
            return dict(res)
        # fallback
        cursor.execute("SELECT * FROM psus WHERE price <= ? AND price > 0 AND power >= ? ORDER BY power DESC, price DESC LIMIT 1",
                       (max_price, min_power))
        res = cursor.fetchone()
        if res:
            return dict(res)
        cursor.execute("SELECT * FROM psus WHERE price <= ? AND price > 0 ORDER BY power DESC, price DESC LIMIT 1", (max_price,))
        res = cursor.fetchone()
        return dict(res) if res else None
    except Exception:
        return None


def pick_component(cursor, component_type, max_price, extra_filters=None,
                   require_integrated_graphics=False, purpose=None,
                   min_capacity=None, min_cores=None, min_vram=None):
    try:
        table_map = {
            'cpu': 'cpus', 'gpu': 'gpus', 'ram': 'rams', 'motherboard': 'motherboards',
            'storage': 'storages', 'psu': 'psus', 'case': 'cases', 'cooler': 'coolers'
        }
        table = table_map.get(component_type)
        if not table:
            return None
        cursor.execute("SELECT name FROM sqlite_master WHERE type='table' AND name=?", (table,))
        if not cursor.fetchone():
            return None

        if component_type == 'psu':
            min_power_map = {'gaming': 550, 'streaming': 600, 'workstation': 650, 'office': 350}
            min_power = min_power_map.get(purpose, 500)
            return find_psu_by_power(cursor, min_power, max_price)

        if component_type == 'ram':
            target = min_capacity if min_capacity else (
                4 if max_price < 5000 else 8 if max_price < 8000 else 16 if max_price < 12000 else 32 if max_price < 20000 else 64
            )
            query = "SELECT * FROM rams WHERE price <= ? AND price > 0"
            params = [max_price]
            if extra_filters and 'memory_type' in extra_filters:
                query += " AND memory_type = ?"
                params.append(extra_filters['memory_type'])
            query += " AND capacity >= ? ORDER BY capacity DESC, price DESC LIMIT 1"
            params.append(target)
            cursor.execute(query, params)
            res = cursor.fetchone()
            if res:
                ram = dict(res)
                qty = 2 if purpose == 'gaming' else 1
                ram['quantity'] = qty
                ram['total_capacity'] = ram['capacity'] * qty
                return ram
            # ослабляем фильтр — пересобираем запрос без capacity
            fallback_query = "SELECT * FROM rams WHERE price <= ? AND price > 0"
            fallback_params = [max_price]
            if extra_filters and 'memory_type' in extra_filters:
                fallback_query += " AND memory_type = ?"
                fallback_params.append(extra_filters['memory_type'])
            fallback_query += " ORDER BY capacity DESC, price DESC LIMIT 1"
            cursor.execute(fallback_query, fallback_params)
            res = cursor.fetchone()
            if res:
                ram = dict(res)
                qty = 2 if purpose == 'gaming' else 1
                ram['quantity'] = qty
                ram['total_capacity'] = ram['capacity'] * qty
                return ram
            return None

        ALLOWED_EXTRA_COLUMNS = {'socket', 'form_factor', 'chipset'}

        query = f"SELECT * FROM {table} WHERE price <= ? AND price > 0"
        params = [max_price]

        cores_filter = min_cores and component_type == 'cpu'
        vram_filter = min_vram and component_type == 'gpu'

        if cores_filter:
            query += " AND cores >= ?"
            params.append(min_cores)
        if vram_filter:
            query += " AND vram >= ?"
            params.append(min_vram)

        if component_type == 'cpu' and require_integrated_graphics:
            query += " AND integrated_graphics = 1"
        elif component_type == 'gpu' and purpose:
            if purpose == 'gaming':
                query += " AND vram >= 4"
            elif purpose in ['streaming', 'workstation']:
                query += " AND vram >= 6"
            elif purpose == 'office':
                query += " AND (vram >= 2 OR vram IS NULL)"
        elif component_type == 'storage' and purpose == 'office':
            query += " AND (storage_type LIKE '%SSD%' OR storage_type LIKE '%NVMe%')"

        if extra_filters:
            for k, v in extra_filters.items():
                if v and k != 'memory_type' and k in ALLOWED_EXTRA_COLUMNS:
                    query += f" AND {k} = ?"
                    params.append(v)

        # сортировка
        if component_type == 'cpu':
            query += " ORDER BY cores DESC, threads DESC, base_clock DESC, price DESC"
        elif component_type == 'gpu':
            query += " ORDER BY vram DESC, price DESC"
        elif component_type == 'motherboard':
            query += " ORDER BY memory_slots DESC, price DESC"
        elif component_type == 'storage':
            query += " ORDER BY capacity DESC, price DESC"
        else:
            query += " ORDER BY price DESC"
        query += " LIMIT 1"

        cursor.execute(query, params)
        res = cursor.fetchone()
        if res:
            return dict(res)

        # Если не нашли с фильтрами — пересобираем запрос без требований к cores/vram
        if cores_filter or vram_filter:
            fallback_query = f"SELECT * FROM {table} WHERE price <= ? AND price > 0"
            fallback_params = [max_price]
            if component_type == 'cpu' and require_integrated_graphics:
                fallback_query += " AND integrated_graphics = 1"
            elif component_type == 'gpu' and purpose:
                if purpose == 'gaming':
                    fallback_query += " AND vram >= 4"
                elif purpose in ['streaming', 'workstation']:
                    fallback_query += " AND vram >= 6"
                elif purpose == 'office':
                    fallback_query += " AND (vram >= 2 OR vram IS NULL)"
            fallback_query += query[query.index(" ORDER BY"):]
            cursor.execute(fallback_query, fallback_params)
            res = cursor.fetchone()
            if res:
                return dict(res)
        return None
    except Exception:
        return None


def recommend_build(db_path: str, budget: float, purpose: str = 'gaming', game_req: dict = None, pinned_components: dict = None):
    try:
        conn = sqlite3.connect(db_path)
        conn.row_factory = sqlite3.Row
        cursor = conn.cursor()

        components = {}
        total_price = 0
        issues = []
        pinned = pinned_components or {}

        for comp_type, comp in pinned.items():
            comp = dict(comp)
            if comp_type == 'ram':
                qty = 2 if purpose == 'gaming' else 1
                comp['quantity'] = qty
                comp['total_capacity'] = comp.get('capacity', 0) * qty
                total_price += comp['price'] * qty
            else:
                total_price += comp['price']
            components[comp_type] = comp

        priorities = {
            'gaming': {'order': ['cpu', 'motherboard', 'gpu', 'ram', 'storage', 'psu', 'case', 'cooler'], 'requires_gpu': True},
            'office': {'order': ['cpu', 'motherboard', 'ram', 'storage', 'psu', 'case', 'cooler'], 'requires_gpu': False},
            'workstation': {'order': ['cpu', 'motherboard', 'gpu', 'ram', 'storage', 'psu', 'case', 'cooler'], 'requires_gpu': True},
            'streaming': {'order': ['cpu', 'motherboard', 'gpu', 'ram', 'storage', 'psu', 'case', 'cooler'], 'requires_gpu': True}
        }
        settings = priorities.get(purpose, priorities['gaming'])
        priority_order = settings['order']
        requires_gpu = settings.get('requires_gpu', True)

        dist = {
            'gaming': {'cpu': 0.18, 'gpu': 0.35, 'ram': 0.08, 'motherboard': 0.12, 'storage': 0.10, 'psu': 0.07, 'case': 0.06, 'cooler': 0.04},
            'office': {'cpu': 0.25, 'ram': 0.12, 'motherboard': 0.15, 'storage': 0.15, 'psu': 0.08, 'case': 0.08, 'cooler': 0.05, 'gpu': 0.12},
            'workstation': {'cpu': 0.25, 'gpu': 0.25, 'ram': 0.15, 'motherboard': 0.10, 'storage': 0.10, 'psu': 0.07, 'case': 0.05, 'cooler': 0.03},
            'streaming': {'cpu': 0.20, 'gpu': 0.30, 'ram': 0.12, 'motherboard': 0.10, 'storage': 0.10, 'psu': 0.08, 'case': 0.06, 'cooler': 0.04}
        }.get(purpose)

        # извлечение требований игры
        game_min_cores = game_rec_cores = None
        game_min_vram = game_rec_vram = None
        game_min_ram = game_rec_ram = None
        if game_req:
            game_min_cores = game_req.get('min_cpu_cores')
            game_rec_cores = game_req.get('rec_cpu_cores')
            game_min_vram = game_req.get('min_gpu_vram_gb')
            game_rec_vram = game_req.get('rec_gpu_vram_gb')
            game_min_ram = game_req.get('min_ram_gb')
            game_rec_ram = game_req.get('rec_ram_gb')
            print(f"[GAME] Учитываем игру: {game_req.get('name')}", file=sys.stderr)
            if game_rec_vram:
                dist['gpu'] = min(dist.get('gpu', 0.3) + 0.1, 0.45)
                dist['cpu'] = max(dist.get('cpu', 0.2) - 0.05, 0.15)
                dist['ram'] = min(dist.get('ram', 0.08) + 0.02, 0.15)

        # CPU + MB (с поддержкой фиксированных компонентов)
        require_igpu = (purpose == 'office')
        if 'cpu' in pinned and 'motherboard' in pinned:
            cpu_found = components['cpu']
            mb_found = components['motherboard']
            motherboard_memory_type = mb_found.get('memory_type', 'DDR4')
        elif 'cpu' in pinned:
            cpu_found = components['cpu']
            cpu_socket = cpu_found.get('socket')
            mb_budget = budget * dist.get('motherboard', 0.12)
            if not cpu_socket:
                conn.close()
                return {'success': False, 'error': 'Не удалось определить сокет зафиксированного процессора'}
            cursor.execute(
                "SELECT * FROM motherboards WHERE socket=? AND price<=? AND price>0 ORDER BY price DESC LIMIT 1",
                (cpu_socket, mb_budget)
            )
            mb_row = cursor.fetchone()
            if not mb_row:
                cursor.execute(
                    "SELECT * FROM motherboards WHERE socket=? AND price>0 ORDER BY price ASC LIMIT 1",
                    (cpu_socket,)
                )
                mb_row = cursor.fetchone()
            if not mb_row:
                conn.close()
                return {'success': False, 'error': f'Нет совместимой материнской платы для сокета {cpu_socket}'}
            mb_found = dict(mb_row)
            components['motherboard'] = mb_found
            total_price += mb_found['price']
            motherboard_memory_type = mb_found.get('memory_type', 'DDR4')
        elif 'motherboard' in pinned:
            mb_found = components['motherboard']
            mb_socket = mb_found.get('socket')
            motherboard_memory_type = mb_found.get('memory_type', 'DDR4')
            cpu_budget = budget * dist.get('cpu', 0.18)
            query = "SELECT * FROM cpus WHERE price<=? AND price>0"
            params = [cpu_budget]
            if mb_socket:
                query += " AND socket=?"
                params.append(mb_socket)
            if require_igpu:
                query += " AND integrated_graphics=1"
            query += " ORDER BY cores DESC, threads DESC, price DESC LIMIT 1"
            cursor.execute(query, params)
            cpu_row = cursor.fetchone()
            if not cpu_row:
                conn.close()
                return {'success': False, 'error': 'Нет совместимого процессора для выбранной материнской платы'}
            cpu_found = dict(cpu_row)
            components['cpu'] = cpu_found
            total_price += cpu_found['price']
        else:
            cpu_budget = budget * dist.get('cpu', 0.25)
            mb_budget = budget * dist.get('motherboard', 0.12)
            cpu_found, mb_found = find_compatible_cpu_mb_pair(cursor, cpu_budget, mb_budget, require_igpu)
            if not cpu_found or not mb_found:
                conn.close()
                return {'success': False, 'error': 'Не удалось подобрать совместимую пару CPU+MB.'}
            components['cpu'] = cpu_found
            components['motherboard'] = mb_found
            total_price += cpu_found['price'] + mb_found['price']
            motherboard_memory_type = mb_found.get('memory_type', 'DDR4')

        # остальные компоненты
        for comp_type in priority_order:
            if comp_type in ['cpu', 'motherboard']:
                continue
            if comp_type in pinned:
                continue
            if comp_type in dist:
                comp_budget = budget * dist[comp_type]
                extra_filters = None
                if comp_type == 'ram':
                    extra_filters = {'memory_type': motherboard_memory_type}
                min_cores = min_vram = min_capacity = None
                if game_req:
                    if comp_type == 'cpu':
                        min_cores = game_rec_cores or game_min_cores
                    elif comp_type == 'gpu':
                        min_vram = game_rec_vram or game_min_vram
                    elif comp_type == 'ram':
                        min_capacity = game_rec_ram or game_min_ram
                comp = pick_component(
                    cursor, comp_type, comp_budget, extra_filters,
                    require_integrated_graphics=False, purpose=purpose,
                    min_capacity=min_capacity, min_cores=min_cores, min_vram=min_vram
                )
                if comp:
                    components[comp_type] = comp
                    if comp_type == 'ram':
                        total_price += comp['price'] * comp.get('quantity', 1)
                    else:
                        total_price += comp['price']

        # проверка обязательных
        essential = ['cpu', 'motherboard', 'ram', 'storage', 'psu', 'case']
        missing = [c for c in essential if c not in components]
        if missing:
            remaining = budget - total_price
            for comp in missing[:]:
                if remaining <= 1000:
                    break
                table_map = {'ram': 'rams', 'storage': 'storages', 'psu': 'psus', 'case': 'cases'}
                table = table_map.get(comp)
                if table:
                    cursor.execute(f"SELECT * FROM {table} WHERE price <= ? AND price > 0 ORDER BY price ASC LIMIT 1", (remaining,))
                    row = cursor.fetchone()
                    if row:
                        cd = dict(row)
                        if comp == 'ram':
                            cd['quantity'] = 1
                            cd['total_capacity'] = cd.get('capacity', 0)
                        components[comp] = cd
                        if comp == 'ram':
                            total_price += cd['price'] * cd.get('quantity', 1)
                        else:
                            total_price += cd['price']
                        remaining = budget - total_price
                        missing.remove(comp)
            if missing:
                conn.close()
                return {'success': False, 'error': f'Не удалось подобрать все компоненты. Отсутствуют: {", ".join(missing)}'}

        # GPU (если требуется)
        if requires_gpu and 'gpu' not in components:
            if cpu_found.get('integrated_graphics', 0) == 0:
                remaining = budget - total_price
                if remaining > 5000:
                    gpu = pick_component(cursor, 'gpu', remaining, purpose=purpose)
                    if gpu:
                        components['gpu'] = gpu
                        total_price += gpu['price']
                    else:
                        issues.append("⚠️ Не удалось подобрать видеокарту")
                else:
                    issues.append("⚠️ Не хватило бюджета на видеокарту")

        # кулер
        if 'cooler' not in components:
            remaining = budget - total_price
            if remaining > 1000:
                cursor.execute("SELECT * FROM coolers WHERE price <= ? AND price > 0 ORDER BY price DESC LIMIT 1", (remaining,))
                cooler = cursor.fetchone()
                if cooler:
                    components['cooler'] = dict(cooler)
                    total_price += components['cooler']['price']

        # предупреждения по игре
        if game_req:
            if 'cpu' in components and game_rec_cores:
                cpu_cores = components['cpu'].get('cores', 0)
                if cpu_cores < game_rec_cores:
                    issues.append(f"⚠️ Процессор ({cpu_cores} ядер) может не дотягивать до рекомендуемого ({game_rec_cores} ядер)")
            if 'gpu' in components and game_rec_vram:
                gpu_vram = components['gpu'].get('vram', 0)
                if gpu_vram < game_rec_vram:
                    issues.append(f"⚠️ Видеокарта ({gpu_vram} ГБ) может не соответствовать рекомендуемой ({game_rec_vram} ГБ VRAM)")
            if 'ram' in components and game_rec_ram:
                ram_total = components['ram'].get('total_capacity', 0) or (components['ram'].get('capacity', 0) * components['ram'].get('quantity', 1))
                if ram_total < game_rec_ram:
                    issues.append(f"⚠️ RAM ({ram_total} ГБ) ниже рекомендуемого ({game_rec_ram} ГБ)")

        conn.close()
        return {
            'success': True,
            'components': components,
            'total_price': total_price,
            'budget_utilization': round((total_price / budget) * 100, 2) if budget > 0 else 0,
            'issues': issues
        }

    except Exception as e:
        if 'conn' in locals():
            conn.close()
        return {'success': False, 'error': f'Ошибка при подборе сборки: {str(e)}'}


def get_component_explanation(comp_type: str, comp: dict, purpose: str) -> str:
    """Возвращает короткое пояснение почему выбран этот компонент"""
    price = comp.get('price', 0)

    if comp_type == 'cpu':
        cores = comp.get('cores', 0)
        if cores >= 12:
            return "Мощный многоядерный процессор — справится с играми, стримингом и параллельными задачами одновременно"
        elif cores >= 8:
            if purpose == 'gaming':
                return "Хороший выбор для игр — 8+ ядер исключают просадки FPS даже в требовательных сценах"
            return "Хватит для работы, многозадачности и нетяжёлого видеомонтажа"
        elif cores >= 6:
            if purpose == 'gaming':
                return "Оптимальный выбор для игр — 6 ядер достаточно для стабильного FPS в большинстве тайтлов"
            elif purpose == 'office':
                return "Достаточно для офисных задач, браузера и лёгкой многозадачности"
            return "Сбалансированный процессор для повседневных задач"
        else:
            return "Базовый вариант для нетребовательных задач"

    elif comp_type == 'gpu':
        vram = comp.get('vram', 0)
        if vram >= 20:
            return "Флагманская видеокарта — для 4K с трассировкой лучей, профессиональной 3D-работы и запаса на годы вперёд"
        elif vram >= 16:
            return "Топовый выбор — 16 ГБ VRAM для комфортной игры в 4K и требовательных профессиональных задач"
        elif vram >= 12:
            return "Для комфортной игры в 1440p и 4K, с запасом на несколько лет вперёд"
        elif vram >= 8:
            return "Оптимальный выбор — 8 ГБ VRAM хватает для всех современных игр в 1080p и большинства в 1440p"
        else:
            return "Бюджетный вариант для нетребовательных игр и медиа в 1080p"

    elif comp_type == 'ram':
        qty = comp.get('quantity', 1)
        cap = comp.get('capacity', 0)
        total_gb = cap * qty
        mem_type = comp.get('memory_type', '')
        type_note = f" ({mem_type})" if mem_type else ""
        if total_gb >= 32:
            return f"32+ ГБ{type_note} — достаточно для стриминга, монтажа и тяжёлых приложений без ограничений"
        elif total_gb >= 16:
            return f"16 ГБ{type_note} — оптимальный объём для игр, работы и многозадачности"
        elif total_gb >= 8:
            if purpose == 'gaming':
                return f"8 ГБ{type_note} — минимум для современных игр, при возможности стоит расширить до 16 ГБ"
            return f"8 ГБ{type_note} — достаточно для офисных задач и повседневной работы"
        return "Базовый объём памяти"

    elif comp_type == 'motherboard':
        mem_type = comp.get('memory_type', '')
        socket = comp.get('socket', '')
        if 'DDR5' in mem_type:
            return f"Современная платформа {socket} с DDR5 — обеспечивает высокую скорость памяти и запас для апгрейда"
        return f"Надёжная платформа {socket} с поддержкой расширения и совместима с процессором"

    elif comp_type == 'storage':
        cap = comp.get('capacity', 0)
        stype = comp.get('storage_type', '').lower()
        cap_text = f"{cap/1000:.0f} ТБ" if cap >= 1000 else f"{cap} ГБ"
        if 'nvme' in stype or 'm.2' in stype:
            return f"Быстрый NVMe SSD {cap_text} — мгновенная загрузка ОС и игр, скорость чтения в несколько раз выше обычного SSD"
        elif 'ssd' in stype:
            return f"SSD {cap_text} значительно ускоряет загрузку системы и приложений по сравнению с HDD"
        return f"Накопитель {cap_text} для хранения ОС, игр и файлов"

    elif comp_type == 'psu':
        power = comp.get('power', 0)
        if power >= 800:
            return f"Блок питания {power} Вт с запасом — стабильное питание и возможность установить более мощные компоненты в будущем"
        elif power >= 650:
            return f"Блок питания {power} Вт перекрывает потребности сборки с небольшим запасом на нагрузочные пики"
        return f"Обеспечивает стабильное питание всех компонентов сборки"

    elif comp_type == 'case':
        return "Обеспечивает нормальный воздушный поток и вмещает все компоненты сборки"

    elif comp_type == 'cooler':
        if price >= 3000:
            return "Эффективное охлаждение снижает температуру и шум процессора под нагрузкой"
        return "Поддерживает нормальную рабочую температуру процессора"

    return ""


def format_recommendation(budget: float, purpose: str, result: dict, game_name: str = None):
    try:
        purpose_text = {'gaming': 'игрового', 'workstation': 'рабочей станции', 'office': 'офисного', 'streaming': 'стримингового'}.get(purpose, purpose)
        if game_name:
            response = f"Я подобрал для вас {purpose_text} ПК для игры «{game_name}»\n\n"
        else:
            response = f"Я подобрал для вас {purpose_text} ПК за {budget:,.0f} ₽\n\n".replace(',', ' ')

        components = result.get('components', {})
        order = ['cpu', 'motherboard', 'ram', 'gpu', 'storage', 'psu', 'case', 'cooler']
        names = {
            'cpu': '💻 Процессор', 'motherboard': '🔌 Материнская плата', 'ram': '🧠 Оперативная память',
            'gpu': '🎮 Видеокарта', 'storage': '💾 Накопитель', 'psu': '⚡ Блок питания',
            'case': '📦 Корпус', 'cooler': '❄️ Кулер'
        }

        for ct in order:
            if ct in components:
                comp = components[ct]
                name = names.get(ct, ct)
                comp_name = comp.get('name', 'Неизвестно')
                # удаляем дублирование типа
                type_words = {
                    'cpu': ['процессор', 'cpu', 'проц'],
                    'motherboard': ['материнская плата', 'материнка', 'motherboard', 'mb', 'мтб'],
                    'ram': ['оперативная память', 'озу', 'ram', 'память'],
                    'gpu': ['видеокарта', 'gpu', 'видео', 'видеоускоритель'],
                    'storage': ['накопитель', 'ssd', 'hdd', 'жесткий диск', 'твердотельный'],
                    'psu': ['блок питания', 'psu', 'бп'],
                    'case': ['корпус', 'case', 'системный блок'],
                    'cooler': ['кулер', 'cooler', 'охлаждение', 'сво', 'водянка']
                }
                if ct in type_words:
                    name_lower = comp_name.lower()
                    for w in type_words[ct]:
                        if name_lower.startswith(w):
                            comp_name = comp_name[len(w):].strip()
                            break
                        if f' {w} ' in name_lower:
                            comp_name = comp_name.replace(w, '', 1).strip()
                if not comp_name or len(comp_name) < 3:
                    comp_name = comp.get('name', 'Неизвестно')[:50]

                price = comp.get('price', 0)
                specs = []
                if ct == 'motherboard':
                    if comp.get('socket'): specs.append(f"сокет {comp['socket']}")
                    if comp.get('memory_type'): specs.append(f"{comp['memory_type']}")
                elif ct == 'cpu':
                    if comp.get('socket'): specs.append(f"сокет {comp['socket']}")
                    if comp.get('tdp'): specs.append(f"TDP {comp['tdp']}W")
                    if comp.get('cores'): specs.append(f"{comp['cores']} ядер")
                elif ct == 'ram':
                    if comp.get('memory_type'): specs.append(f"{comp['memory_type']}")
                    qty = comp.get('quantity', 1)
                    cap = comp.get('capacity', 0)
                    specs.append(f"{qty}x{cap} ГБ" if qty > 1 else f"{cap} ГБ")
                elif ct == 'gpu':
                    if comp.get('vram'): specs.append(f"{comp['vram']} ГБ VRAM")
                elif ct == 'storage':
                    if comp.get('capacity'):
                        cap = comp['capacity']
                        specs.append(f"{cap/1000:.1f} ТБ" if cap >= 1000 else f"{cap} ГБ")
                    if comp.get('storage_type'): specs.append(comp['storage_type'])
                elif ct == 'psu' and comp.get('power'):
                    specs.append(f"{comp['power']} Вт")

                spec_text = f" ({', '.join(specs)})" if specs else ""
                price_f = f"{price:,.0f}".replace(",", " ")
                if ct == 'ram':
                    total_price = price * comp.get('quantity', 1)
                    response += f"{name}: {comp_name}{spec_text} - {total_price:,.0f} ₽\n"
                else:
                    response += f"{name}: {comp_name}{spec_text} - {price_f} ₽\n"

                explanation = get_component_explanation(ct, comp, purpose)
                if explanation:
                    response += f"   -> {explanation}\n\n"
                else:
                    response += "\n"

        total = result.get('total_price', 0)
        utilization = result.get('budget_utilization', 0)
        remaining = budget - total
        response += f"\nИтоги:\n• Общая стоимость: {total:,.0f} ₽\n"
        if remaining > 0:
            response += f"• Осталось бюджета: {remaining:,.0f} ₽\n\n".replace(',', ' ')
        else:
            response += "\n"

        issues = result.get('issues', [])
        if issues:
            response += "⚠️ Предупреждения:\n" + "\n".join(f"   {i}" for i in issues) + "\n\n"

        if remaining > budget * 0.15:
            response += "💡 Совет: Осталось много бюджета. Вы можете:\n"
            if purpose == 'gaming':
                response += "   - Улучшить видеокарту\n   - Увеличить объем памяти\n   - Взять более мощный процессор\n"
            elif purpose == 'office':
                response += "   - Увеличить объем SSD\n   - Добавить больше оперативной памяти\n"
        elif utilization < 70:
            response += "💡 Совет: Можно улучшить компоненты для лучшей производительности.\n"
        elif utilization > 95:
            response += "✅ Отлично! Бюджет использован оптимально!\n"
        else:
            response += "👍 Хорошо! Сбалансированная сборка по цене и производительности.\n"
        response += "\n Сборка готова! Вы можете сохранить её в профиле."
        return response
    except Exception as e:
        import traceback
        print(f"[ERROR] format_recommendation: {traceback.format_exc()}", file=sys.stderr)
        return f"Ошибка при форматировании рекомендации: {str(e)}"


def main():
    if len(sys.argv) < 2:
        print(json.dumps({"success": False, "error": "Нет аргументов"}))
        return
    try:
        with open(sys.argv[1], 'r', encoding='utf-8') as f:
            args = json.load(f)
        action = args.get('action')
        db_path = args.get('db_path', 'database/pc_builder.db')
        game_req = args.get('game_requirements')

        if action == 'recommend_build':
            budget = args.get('budget', 50000)
            purpose = args.get('purpose', 'gaming')
            db_info = check_database(db_path)
            if not db_info['exists'] or db_info.get('total_components', 0) < 2:
                result = {
                    "success": True,
                    "type": "empty_db",
                    "message": f"База данных компонентов пуста или содержит мало данных.\n\nНайдено: {db_info.get('cpu_count', 0)} процессоров, {db_info.get('gpu_count', 0)} видеокарт, {db_info.get('ram_count', 0)} модулей RAM\n\nПараметры сборки:\n• Бюджет: {budget:,.0f} ₽\n• Цель: {purpose}\n\nЗапустите парсинг компонентов через админ-панель.",
                    "extracted_params": {"budget": budget, "purpose": purpose}
                }
            else:
                pinned_components = args.get('pinned_components')
                rec = recommend_build(db_path, budget, purpose, game_req=game_req, pinned_components=pinned_components)
                if rec['success']:
                    game_name = game_req.get('name') if game_req else None
                    msg = format_recommendation(budget, purpose, rec, game_name=game_name)
                    result = {
                        "success": True,
                        "type": "recommendation",
                        "message": msg,
                        "components": rec.get('components', {}),
                        "total_price": rec.get('total_price', 0),
                        "budget_utilization": rec.get('budget_utilization', 0),
                        "extracted_params": {"budget": budget, "purpose": purpose}
                    }
                else:
                    result = {
                        "success": False,
                        "type": "error",
                        "message": rec.get('error', 'Не удалось подобрать сборку.'),
                        "extracted_params": {"budget": budget, "purpose": purpose}
                    }
            print(json.dumps(result, ensure_ascii=False, default=str))
        else:
            print(json.dumps({"success": False, "error": f"Неизвестное действие: {action}"}))
    except Exception as e:
        print(json.dumps({"success": False, "error": f"Ошибка: {str(e)}"}))


if __name__ == "__main__":
    main()
