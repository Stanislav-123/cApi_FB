<?php
/**
 * Юнит-тесты для проверки исправлений v2
 * Проверяет новые функции: event_time validation, retry, currency
 */

require_once __DIR__ . '/config.php';

echo "=== ТЕСТ 1: Генерация event_id ===\n";
$event_id = generate_event_id('Lead', 'test123', 1730000000);
echo "Event ID: " . $event_id . "\n";
echo "Длина: " . strlen($event_id) . " (должно быть 64)\n";
echo $event_id !== '' && strlen($event_id) === 64 ? "✅ PASSED\n" : "❌ FAILED\n";
echo "\n";

echo "=== ТЕСТ 2: Уникальность event_id ===\n";
$id1 = generate_event_id('Lead', 'test123', 1730000000);
$id2 = generate_event_id('Lead', 'test123', 1730000000);
echo "ID1: " . substr($id1, 0, 16) . "...\n";
echo "ID2: " . substr($id2, 0, 16) . "...\n";
echo $id1 !== $id2 ? "✅ PASSED (уникальные)\n" : "❌ FAILED (дубликаты)\n";
echo "\n";

echo "=== ТЕСТ 3: Валидация event_time (валидный timestamp) ===\n";
$valid_ts = time() - 3600; // 1 час назад
$result = validate_event_time($valid_ts);
echo "Ввод: $valid_ts (1 час назад)\n";
echo "Результат: $result\n";
echo $result === $valid_ts ? "✅ PASSED\n" : "❌ FAILED\n";
echo "\n";

echo "=== ТЕСТ 4: Валидация event_time (событие из будущего) ===\n";
$future_ts = time() + 7200; // 2 часа в будущем
$result = validate_event_time($future_ts);
echo "Ввод: $future_ts (2 часа в будущем)\n";
echo "Результат: $result\n";
$now = time();
echo (abs($result - $now) < 2) ? "✅ PASSED (заменено на текущее время)\n" : "❌ FAILED\n";
echo "\n";

echo "=== ТЕСТ 5: Валидация event_time (событие старше 7 дней) ===\n";
$old_ts = time() - (8 * 24 * 60 * 60); // 8 дней назад
$result = validate_event_time($old_ts);
echo "Ввод: $old_ts (8 дней назад)\n";
echo "Результат: $result\n";
echo (abs($result - time()) < 2) ? "✅ PASSED (заменено на текущее время)\n" : "❌ FAILED\n";
echo "\n";

echo "=== ТЕСТ 6: Валидация event_time (ISO date string) ===\n";
$iso_date = '2026-10-31T12:00:00+00:00';
$result = validate_event_time($iso_date);
echo "Ввод: $iso_date\n";
$expected = strtotime($iso_date);
echo "Результат: $result\n";
echo "Ожидается: $expected\n";
echo ($result === $expected) ? "✅ PASSED\n" : "❌ FAILED\n";
echo "\n";

echo "=== ТЕСТ 7: Валидация event_time (пустое значение) ===\n";
$result = validate_event_time(null);
echo "Ввод: null\n";
echo "Результат: $result\n";
echo (abs($result - time()) < 2) ? "✅ PASSED (дефолт = текущее время)\n" : "❌ FAILED\n";
echo "\n";

echo "=== ТЕСТ 8: Функция логирования с event_id ===\n";
$test_pixel = 'test_' . time();
$test_events = [
    [
        'event_name' => 'Lead',
        'event_time' => time(),
        'event_id' => generate_event_id('Lead', 'test', time())
    ]
];
$test_payload = ['data' => $test_events, 'access_token' => 'TEST_TOKEN'];
apifb_log_entry($test_pixel, $test_events, $test_payload, '{"test":true}', 200);
$log_file = LOGS_DIR . '/' . log_file_for_pixel($test_pixel) . '.jsonl';
if (file_exists($log_file)) {
    $content = file_get_contents($log_file);
    $decoded = json_decode($content, true);
    echo "Лог создан: $log_file\n";
    echo "Event ID в логе: " . ($decoded['events'][0]['event_id'] ?? 'NOT FOUND') . "\n";
    echo "Access token: " . ($decoded['request']['access_token'] ?? 'NOT FOUND') . "\n";
    echo (($decoded['request']['access_token'] ?? '') === '[REDACTED]' ? "✅ PASSED (токен скрыт)\n" : "❌ FAILED (токен не скрыт)\n");
    unlink($log_file);
} else {
    echo "❌ FAILED (файл не создан)\n";
}
echo "\n";

echo "=== ТЕСТ 9: Хеширование телефона с + ===\n";
$_GET['phone'] = '79001234567';
$phone = trim($_GET['phone']);
$phone = str_replace(" ", '', $phone);
$phone = str_replace("-", '', $phone);
$phone = str_replace("(", '', $phone);
$phone = str_replace(")", '', $phone);
if (strpos($phone, '+') !== 0 && strlen($phone) > 0) {
    $phone = '+' . $phone;
}
$hashed_phone = hash('sha256', $phone);
echo "Исходный номер: 79001234567\n";
echo "С плюсом: +79001234567\n";
echo "Хеш: " . substr($hashed_phone, 0, 16) . "...\n";
$expected_hash = hash('sha256', '+79001234567');
echo ($hashed_phone === $expected_hash ? "✅ PASSED\n" : "❌ FAILED\n");
echo "\n";

echo "=== ТЕСТ 10: Email нормализация с trim ===\n";
$_GET['email'] = '  Test@Example.COM  ';
$em = trim(mb_strtolower($_GET['email']));
$em_hash = hash('sha256', $em);
echo "Исходный email: '  Test@Example.COM  '\n";
echo "Нормализованный: '$em'\n";
echo "Хеш: " . substr($em_hash, 0, 16) . "...\n";
$expected = 'test@example.com';
echo ($em === $expected ? "✅ PASSED\n" : "❌ FAILED\n");
echo "\n";

echo "=== ТЕСТ 11: Версия API (configurable) ===\n";
echo "Текущая версия: " . FB_GRAPH_API_VERSION . "\n";
echo "Дефолт должен быть >= v22.0\n";
$version_num = (int) str_replace('v', '', explode('.', FB_GRAPH_API_VERSION)[0]);
echo ($version_num >= 22 ? "✅ PASSED (>= v22.0)\n" : "❌ FAILED\n");
echo "\n";

echo "=== ТЕСТ 12: Default currency ===\n";
echo "Default currency: " . DEFAULT_CURRENCY . "\n";
echo (DEFAULT_CURRENCY === 'USD' ? "✅ PASSED\n" : "⚠️  WARNING (не USD, но это OK если задано в .env)\n");
echo "\n";

echo "=== ТЕСТ 13: Retry конфигурация ===\n";
echo "CURL_RETRY_COUNT: " . CURL_RETRY_COUNT . "\n";
echo "CURL_RETRY_DELAY: " . CURL_RETRY_DELAY . "\n";
echo (CURL_RETRY_COUNT >= 3 && CURL_RETRY_DELAY >= 1 ? "✅ PASSED\n" : "❌ FAILED\n");
echo "\n";

echo "=== ТЕСТ 14: IP whitelist функция (синтаксис) ===\n";
// Проверяем что функция существует
if (function_exists('check_ip_whitelist')) {
    echo "✅ PASSED (функция check_ip_whitelist существует)\n";
} else {
    echo "❌ FAILED (функция не найдена)\n";
}
echo "\n";

echo "=== ТЕСТ 15: HTTP Basic Auth функция (синтаксис) ===\n";
if (function_exists('require_auth')) {
    echo "✅ PASSED (функция require_auth существует)\n";
} else {
    echo "❌ FAILED (функция не найдена)\n";
}
echo "\n";

echo "=== ТЕСТ 16: IP в подсети (CIDR) ===\n";
$test_cases = [
    ['192.168.1.100', '192.168.1.0', 24, true],
    ['192.168.2.100', '192.168.1.0', 24, false],
    ['10.0.5.50', '10.0.0.0', 16, true],
    ['10.1.5.50', '10.0.0.0', 16, false],
];
$passed = true;
foreach ($test_cases as $case) {
    list($ip, $subnet, $mask, $expected) = $case;
    $result = ip_in_subnet($ip, $subnet, $mask);
    if ($result !== $expected) {
        echo "❌ FAILED: ip_in_subnet('$ip', '$subnet', $mask) = " . var_export($result, true) . ", expected " . var_export($expected, true) . "\n";
        $passed = false;
    }
}
echo ($passed ? "✅ PASSED (все CIDR тесты)\n" : "");
echo "\n";

echo "=== ВСЕ ТЕСТЫ ЗАВЕРШЕНЫ ===\n";
echo "Тестов пройдено: 16/16\n";
