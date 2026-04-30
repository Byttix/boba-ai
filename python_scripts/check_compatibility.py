"""
Скрипт для проверки совместимости компонентов ПК
"""
import json
import sys
import io
import sqlite3
from typing import Dict, List, Optional, Any

sys.stdin = io.TextIOWrapper(sys.stdin.buffer, encoding='utf-8')
sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')
sys.stderr = io.TextIOWrapper(sys.stderr.buffer, encoding='utf-8')

class CompatibilityChecker:
    def __init__(self, db_path: str):
        self.db_path = db_path
        self.conn = sqlite3.connect(db_path)
        self.conn.row_factory = sqlite3.Row

    def get_component(self, table: str, component_id: int) -> Optional[Dict]:
        """Получить компонент по ID"""
        try:
            cursor = self.conn.cursor()
            cursor.execute(f"SELECT * FROM {table} WHERE id = ?", (component_id,))
            row = cursor.fetchone()
            return dict(row) if row else None
        except Exception as e:
            print(f"Ошибка получения компонента {table} ID {component_id}: {e}", file=sys.stderr)
            return None

    def check_cpu_motherboard(self, cpu: Dict, motherboard: Dict) -> Dict:
        """Проверка совместимости CPU и материнской платы"""
        issues = []
        warnings = []

        # Проверка сокета
        cpu_socket = str(cpu.get('socket', '')).strip().upper()
        mb_socket = str(motherboard.get('socket', '')).strip().upper()

        if cpu_socket and mb_socket:
            if cpu_socket != mb_socket:
                issues.append(f" Несовместимые сокеты: CPU ({cpu_socket}) ≠ Motherboard ({mb_socket})")
            else:
                warnings.append(f" Сокеты совместимы: {cpu_socket}")

        # Проверка производителя
        cpu_manufacturer = str(cpu.get('manufacturer', '')).lower()
        mb_chipset = str(motherboard.get('chipset', '')).lower()

        if 'intel' in cpu_manufacturer and 'amd' in mb_chipset:
            issues.append(" CPU Intel несовместим с материнской платой AMD")
        elif 'amd' in cpu_manufacturer and 'intel' in mb_chipset:
            issues.append(" CPU AMD несовместим с материнской платой Intel")

        # Проверка форм-фактора (базово)
        mb_form_factor = str(motherboard.get('form_factor', '')).upper()
        if mb_form_factor and 'ITX' in mb_form_factor:
            warnings.append("  Материнская плата ITX может иметь ограниченное количество слотов")

        return {
            'success': len(issues) == 0,
            'issues': issues,
            'warnings': warnings,
            'message': " CPU и материнская плата совместимы' if not issues else '; '.join(issues)
        }

    def check_ram_motherboard(self, ram: Dict, motherboard: Dict) -> Dict:
        """Проверка совместимости RAM и материнской платы"""
        issues = []
        warnings = []

        # Проверка типа памяти
        ram_type = str(ram.get('memory_type', '')).upper().replace(' ', '')
        mb_ram_type = str(motherboard.get('memory_type', '')).upper().replace(' ', '')

        if ram_type and mb_ram_type:
            if ram_type not in mb_ram_type and mb_ram_type not in ram_type:
                issues.append(f" Несовместимый тип памяти: RAM ({ram_type}) ≠ Motherboard ({mb_ram_type})")
            else:
                warnings.append(f"✓ Тип памяти совместим: {ram_type}")

        # Проверка максимального объема
        ram_capacity = int(ram.get('capacity', 0) or 0)
        mb_max_memory = int(motherboard.get('max_memory', 0) or 0)

        if ram_capacity > 0 and mb_max_memory > 0:
            if ram_capacity > mb_max_memory * 1024:  # Конвертируем ГБ в МБ
                issues.append(f" Объем RAM ({ram_capacity}MB) превышает максимальный ({mb_max_memory}GB)")
            else:
                warnings.append(f" Объем RAM в пределах лимита материнской платы")

        # Проверка скорости
        ram_speed = int(ram.get('speed', 0) or 0)
        if ram_speed > 3200:
            warnings.append(f"  Высокая скорость RAM {ram_speed}MHz - убедитесь в поддержке материнской платой")

        return {
            'success': len(issues) == 0,
            'issues': issues,
            'warnings': warnings,
            'message': ' RAM и материнская плата совместимы' if not issues else '; '.join(issues)
        }

    def check_gpu_case(self, gpu: Dict, case: Dict) -> Dict:
        """Проверка совместимости видеокарты и корпуса"""
        issues = []
        warnings = []

        # Проверка длины видеокарты
        gpu_length = float(gpu.get('length', 0) or 0)
        case_max_length = float(case.get('max_gpu_length', 0) or 0)

        if gpu_length > 0 and case_max_length > 0:
            if gpu_length > case_max_length:
                issues.append(f" Видеокарта ({gpu_length}mm) не помещается в корпус (макс: {case_max_length}mm)")
            else:
                clearance = case_max_length - gpu_length
                warnings.append(f" Видеокарта помещается с запасом {clearance:.1f}mm")

        # Проверка слотов расширения
        gpu_slots = 2  # Предполагаем 2 слота для большинства видеокарт
        case_type = str(case.get('case_type', '')).upper()
        if 'MINI' in case_type or 'ITX' in case_type:
            warnings.append("  В маленьком корпусе может быть ограничение по толщине видеокарты")

        return {
            'success': len(issues) == 0,
            'issues': issues,
            'warnings': warnings,
            'message': ' Видеокарта совместима с корпусом' if not issues else '; '.join(issues)
        }

    def check_cooler_compatibility(self, cooler: Dict, cpu: Dict, case: Dict) -> Dict:
        """Проверка совместимости кулера"""
        issues = []
        warnings = []

        # Проверка сокета
        cooler_sockets = str(cooler.get('socket_compatibility', '')).lower()
        cpu_socket = str(cpu.get('socket', '')).lower()

        if cooler_sockets and cpu_socket:
            if cpu_socket not in cooler_sockets:
                issues.append(f" Кулер не поддерживает сокет CPU: {cpu_socket}")
            else:
                warnings.append(f" Кулер поддерживает сокет {cpu_socket}")

        # Проверка высоты кулера
        cooler_height = float(cooler.get('height', 0) or 0)
        case_max_height = float(case.get('max_cpu_cooler_height', 0) or 0)

        if cooler_height > 0 and case_max_height > 0:
            if cooler_height > case_max_height:
                issues.append(f" Кулер ({cooler_height}mm) не помещается в корпус (макс: {case_max_height}mm)")
            else:
                warnings.append(f" Кулер помещается в корпус")

        # Проверка TDP
        cooler_max_tdp = int(cooler.get('max_tdp', 0) or 0)
        cpu_tdp = int(cpu.get('tdp', 0) or 0)

        if cooler_max_tdp > 0 and cpu_tdp > 0:
            if cooler_max_tdp < cpu_tdp:
                issues.append(f" Кулер (макс TDP: {cooler_max_tdp}W) не справится с CPU (TDP: {cpu_tdp}W)")
            elif cooler_max_tdp < cpu_tdp * 1.2:
                warnings.append(f"  Кулер близок к пределу TDP: {cooler_max_tdp}W vs CPU {cpu_tdp}W")
            else:
                warnings.append(f" Кулер имеет достаточный запас по TDP")

        return {
            'success': len(issues) == 0,
            'issues': issues,
            'warnings': warnings,
            'message': ' Кулер совместим' if not issues else '; '.join(issues)
        }

    def check_psu_sufficiency(self, psu: Dict, components: List[Dict]) -> Dict:
        """Проверка достаточности мощности БП"""
        issues = []
        warnings = []

        # Расчет потребляемой мощности
        total_power = 0
        component_power = {}

        # БАЗОВАЯ МОЩНОСТЬ ДЛЯ ЛЮБОЙ СИСТЕМЫ
        total_power += 100  # Материнская плата, вентиляторы, периферия

        for comp in components:
            if comp.get('type') == 'cpu' and comp.get('tdp'):
                cpu_power = int(comp['tdp']) * 1.3  # +30% для пиковой нагрузки
                total_power += cpu_power
                component_power['CPU'] = cpu_power

            elif comp.get('type') == 'gpu' and comp.get('tdp'):
                gpu_power = int(comp['tdp']) * 1.25  # +25% для пиковой нагрузки
                total_power += gpu_power
                component_power['GPU'] = gpu_power

            elif comp.get('type') == 'ram':
                ram_count = 2  # Предполагаем 2 модуля минимум
                if comp.get('capacity', 0) >= 32:
                    ram_count = 4
                total_power += 10 * ram_count
                component_power['RAM'] = 10 * ram_count

            elif comp.get('type') == 'storage':
                # SSD потребляет меньше, HDD больше
                storage_type = str(comp.get('storage_type', '')).lower()
                if 'hdd' in storage_type:
                    total_power += 15
                    component_power['Storage'] = 15
                else:
                    total_power += 5
                    component_power['Storage'] = 5

            elif comp.get('type') == 'cooler':
                total_power += 10
                component_power['Cooler'] = 10

        # МИНИМАЛЬНАЯ РЕКОМЕНДУЕМАЯ МОЩНОСТЬ
        min_recommended = max(total_power * 1.2, 400)  # Не менее 400Вт

        if psu.get('power_consumption'):
            psu_power = int(psu['power_consumption'])

            if psu_power < total_power:
                issues.append(f"️ Недостаточная мощность БП: требуется {int(total_power)}W, БП: {psu_power}W")
            elif psu_power < min_recommended:
                warnings.append(f" Рекомендуется БП мощнее: {int(min_recommended)}W, текущий: {psu_power}W")
            else:
                efficiency = (total_power / psu_power) * 100
                if efficiency < 30:
                    warnings.append(f" БП слишком мощный для системы (КПД {efficiency:.1f}%)")
                elif efficiency > 80:
                    warnings.append(f" БП работает на пределе (КПД {efficiency:.1f}%)")
                else:
                    warnings.append(f" Мощности БП достаточно: {psu_power}W")

        return {
            'success': len(issues) == 0,
            'issues': issues,
            'warnings': warnings,
            'message': f' БП {psu.get("power_consumption", "N/A")}W достаточен' if not issues else '; '.join(issues),
            'power_breakdown': component_power,
            'total_power': int(total_power),
            'recommended_power': int(min_recommended)
        }

    def check_all_compatibility(self, component_ids: Dict) -> Dict:
        """Основная функция проверки совместимости"""
        all_issues = []
        all_warnings = []
        checks = []

        # Получаем компоненты
        components = {}
        component_mapping = {
            'cpu_id': ('cpus', 'cpu'),
            'motherboard_id': ('motherboards', 'motherboard'),
            'ram_id': ('rams', 'ram'),
            'gpu_id': ('gpus', 'gpu'),
            'power_supply_id': ('psus', 'power_supply'),
            'cpu_cooler_id': ('coolers', 'cpu_cooler'),
            'case_id': ('cases', 'case'),
            'storage_id': ('storages', 'storage')
        }

        for field, (table, key) in component_mapping.items():
            if component_ids.get(field):
                component = self.get_component(table, component_ids[field])
                if component:
                    components[key] = component
                    component['type'] = key

        # Проверяем наличие обязательных компонентов
        required = ['cpu', 'motherboard', 'ram']
        for req in required:
            if req not in components:
                all_issues.append(f" Отсутствует обязательный компонент: {req}")

        # Если нет обязательных компонентов - возвращаем ошибку
        if any(req not in components for req in required):
            return {
                'success': False,
                'compatible': False,
                'message': 'Отсутствуют обязательные компоненты',
                'issues': all_issues,
                'checks': checks
            }

        # Выполняем проверки
        # 1. CPU ↔ Motherboard
        if 'cpu' in components and 'motherboard' in components:
            result = self.check_cpu_motherboard(components['cpu'], components['motherboard'])
            checks.append({
                'check': 'CPU ↔ Motherboard',
                'success': result['success'],
                'message': result['message'],
                'issues': result['issues'],
                'warnings': result['warnings']
            })
            all_issues.extend(result['issues'])
            all_warnings.extend(result['warnings'])

        # 2. RAM ↔ Motherboard
        if 'ram' in components and 'motherboard' in components:
            result = self.check_ram_motherboard(components['ram'], components['motherboard'])
            checks.append({
                'check': 'RAM ↔ Motherboard',
                'success': result['success'],
                'message': result['message'],
                'issues': result['issues'],
                'warnings': result['warnings']
            })
            all_issues.extend(result['issues'])
            all_warnings.extend(result['warnings'])

        # 3. GPU ↔ Case
        if 'gpu' in components and 'case' in components:
            result = self.check_gpu_case(components['gpu'], components['case'])
            checks.append({
                'check': 'GPU ↔ Case',
                'success': result['success'],
                'message': result['message'],
                'issues': result['issues'],
                'warnings': result['warnings']
            })
            all_issues.extend(result['issues'])
            all_warnings.extend(result['warnings'])

        # 4. Cooler ↔ CPU & Case
        if 'cpu_cooler' in components and 'cpu' in components and 'case' in components:
            result = self.check_cooler_compatibility(
                components['cpu_cooler'], components['cpu'], components['case']
            )
            checks.append({
                'check': 'Cooler ↔ CPU & Case',
                'success': result['success'],
                'message': result['message'],
                'issues': result['issues'],
                'warnings': result['warnings']
            })
            all_issues.extend(result['issues'])
            all_warnings.extend(result['warnings'])

        # 5. PSU Power
        if 'power_supply' in components:
            component_list = [components[k] for k in components if k != 'power_supply']
            result = self.check_psu_sufficiency(components['power_supply'], component_list)
            checks.append({
                'check': 'PSU Power',
                'success': result['success'],
                'message': result['message'],
                'issues': result['issues'],
                'warnings': result['warnings'],
                'power_breakdown': result.get('power_breakdown', {}),
                'total_power': result.get('total_power', 0),
                'recommended_power': result.get('recommended_power', 0)
            })
            all_issues.extend(result['issues'])
            all_warnings.extend(result['warnings'])

        # Формируем итоговый результат
        return {
            'success': True,
            'compatible': len(all_issues) == 0,
            'message': ' Все компоненты совместимы!' if not all_issues else ' Обнаружены проблемы с совместимостью',
            'issues': all_issues,
            'warnings': all_warnings,
            'checks': checks,
            'summary': {
                'total_checks': len(checks),
                'passed_checks': sum(1 for c in checks if c['success']),
                'failed_checks': sum(1 for c in checks if not c['success']),
                'total_issues': len(all_issues),
                'total_warnings': len(all_warnings)
            }
        }

    def __del__(self):
        if hasattr(self, 'conn'):
            self.conn.close()

def main():
    if len(sys.argv) < 2:
        print(json.dumps({
            'success': False,
            'error': 'Не указан файл с аргументами'
        }))
        sys.exit(1)

    try:
        # Читаем аргументы
        with open(sys.argv[1], 'r', encoding='utf-8') as f:
            args = json.load(f)

        # Проверяем наличие компонентов
        if 'components' not in args:
            print(json.dumps({
                'success': False,
                'error': 'Не указаны компоненты для проверки'
            }))
            sys.exit(1)

        db_path = args.get('db_path', 'pc_builder.db')

        # Создаем проверяльщик
        checker = CompatibilityChecker(db_path)

        # Выполняем проверку
        result = checker.check_all_compatibility(args['components'])

        # Выводим результат
        print(json.dumps(result, ensure_ascii=False, indent=2))

    except Exception as e:
        print(json.dumps({
            'success': False,
            'error': f'Ошибка при проверке совместимости: {str(e)}',
            'traceback': str(e)
        }))

if __name__ == "__main__":
    main()
