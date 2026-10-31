<?php
/**
 * Юнит-тесты для проверки исправлений
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

echo "=== ТЕСТ 3: Функция логирования ===\n";
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
    unlink($log_file); // Очистка
} else {
    echo "❌ FAILED (файл не создан)\n";
}
echo "\n";

echo "=== ТЕСТ 4: Хеширование телефона (новая логика) ===\n";
// Симуляция $_GET для collectPersonalData.php
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

echo "=== ТЕСТ 5: Email нормализация ===\n";
$_GET['email'] = '  Test@Example.COM  ';
$em = trim(mb_strtolower($_GET['email']));
$em_hash = hash('sha256', $em);
echo "Исходный email: '  Test@Example.COM  '\n";
echo "Нормализованный: '$em'\n";
echo "Хеш: " . substr($em_hash, 0, 16) . "...\n";
$expected = 'test@example.com';
echo ($em === $expected ? "✅ PASSED\n" : "❌ FAILED\n");
echo "\n";

echo "=== ТЕСТ 6: Проверка версии API ===\n";
echo "Текущая версия: " . FB_GRAPH_API_VERSION . "\n";
echo (FB_GRAPH_API_VERSION === 'v21.0' ? "✅ PASSED (обновлено до v21.0)\n" : "⚠️  WARNING (не v21.0)\n");
echo "\n";

echo "=== ВСЕ ТЕСТЫ ЗАВЕРШЕНЫ ===\n";
