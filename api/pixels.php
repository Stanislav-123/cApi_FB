<?php
/**
 * Список пикселей (по файлам логов)
 * GET: возвращает JSON { "pixels": [ { "id": "123", "file": "123.jsonl" }, ... ] }
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/config.php';

$files = glob(LOGS_DIR . '/*.jsonl');
$pixels = [];

foreach ($files as $path) {
    $base = basename($path, '.jsonl');
    if ($base !== '' && $base !== 'unknown') {
        $pixels[] = ['id' => $base, 'file' => $base . '.jsonl'];
    }
}

usort($pixels, function ($a, $b) {
    return strcmp($b['id'], $a['id']);
});

echo json_encode(['pixels' => $pixels]);
