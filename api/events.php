<?php
/**
 * События по пикселю (чтение лога)
 * GET: pixel_id — возвращает JSON { "pixel_id": "...", "entries": [ ... ] }
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/config.php';

$pixel_id = isset($_GET['pixel_id']) ? trim($_GET['pixel_id']) : '';

if ($pixel_id === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Требуется pixel_id']);
    exit;
}

$logName = log_file_for_pixel($pixel_id);
$logPath = LOGS_DIR . '/' . $logName . '.jsonl';

if (!is_file($logPath)) {
    echo json_encode(['pixel_id' => $pixel_id, 'entries' => []]);
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

$entries = array_reverse($entries);

echo json_encode(['pixel_id' => $pixel_id, 'entries' => $entries]);
