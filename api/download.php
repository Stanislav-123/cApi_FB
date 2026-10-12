<?php
/**
 * Скачивание данных по пикселю (все записи лога в JSON)
 * GET: pixel_id
 */
require_once dirname(__DIR__) . '/config.php';

$pixel_id = isset($_GET['pixel_id']) ? trim($_GET['pixel_id']) : '';

if ($pixel_id === '') {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'Требуется pixel_id']);
    exit;
}

$logName = log_file_for_pixel($pixel_id);
$logPath = LOGS_DIR . '/' . $logName . '.jsonl';

if (!is_file($logPath)) {
    header('Content-Type: application/json');
    http_response_code(404);
    echo json_encode(['error' => 'Лог для пикселя не найден']);
    exit;
}

$lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$entries = [];

foreach ($lines as $line) {
    $decoded = json_decode($line, true);
    if (is_array($decoded)) {
        $entries[] = $decoded;
    }
}

$filename = 'fb_events_pixel_' . $logName . '_' . date('Y-m-d_His') . '.json';

header('Content-Type: application/json; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

echo json_encode(['pixel_id' => $pixel_id, 'entries' => $entries], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
