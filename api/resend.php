<?php
/**
 * Переотправка событий на другой пиксель
 * Два варианта:
 * 1) source_pixel_id — данные берутся из лога logs/{pixel_id}.jsonl
 * 2) file — загруженный JSON/JSONL с событиями
 * Обязательно: new_pixel_id, new_access_token
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$source_pixel_id = isset($_POST['source_pixel_id']) ? trim($_POST['source_pixel_id']) : '';
$new_pixel_id    = isset($_POST['new_pixel_id']) ? trim($_POST['new_pixel_id']) : '';
$new_token       = isset($_POST['new_access_token']) ? trim($_POST['new_access_token']) : '';
$test_event_code = isset($_POST['test_event_code']) ? trim($_POST['test_event_code']) : null;

if (!$new_pixel_id || !$new_token) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Требуются new_pixel_id и new_access_token']);
    exit;
}

$eventsToSend = [];

// Вариант 1: загружен файл
if (!empty($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name'])) {
    $raw = file_get_contents($_FILES['file']['tmp_name']);
    $decoded = json_decode($raw, true);

    if (is_array($decoded)) {
        if (isset($decoded['data']) && is_array($decoded['data'])) {
            $eventsToSend = $decoded['data'];
        } elseif (isset($decoded['entries']) && is_array($decoded['entries'])) {
            foreach ($decoded['entries'] as $entry) {
                if (!empty($entry['request']['data']) && is_array($entry['request']['data'])) {
                    foreach ($entry['request']['data'] as $ev) {
                        $eventsToSend[] = $ev;
                    }
                }
            }
        } else {
            $eventsToSend = isset($decoded['data']) ? $decoded['data'] : (array) $decoded;
        }
    } else {
        $lines = array_filter(explode("\n", $raw));
        foreach ($lines as $line) {
            $row = json_decode($line, true);
            if (is_array($row) && !empty($row['request']['data'])) {
                foreach ($row['request']['data'] as $ev) {
                    $eventsToSend[] = $ev;
                }
            }
        }
    }
}
// Вариант 2: пиксель-источник из логов
elseif ($source_pixel_id !== '') {
    $logName = log_file_for_pixel($source_pixel_id);
    $logPath = LOGS_DIR . '/' . $logName . '.jsonl';

    if (!is_file($logPath)) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Лог для пикселя-источника не найден']);
        exit;
    }

    $lines = file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $row = json_decode($line, true);
        if (is_array($row) && !empty($row['request']['data']) && is_array($row['request']['data'])) {
            foreach ($row['request']['data'] as $ev) {
                $eventsToSend[] = $ev;
            }
        }
    }
}

if (empty($eventsToSend)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Нет событий для отправки. Выберите пиксель-источник или загрузите файл (JSON/JSONL).']);
    exit;
}

$payload = [
    'data' => $eventsToSend,
    'access_token' => $new_token,
];
if ($test_event_code !== null && $test_event_code !== '') {
    $payload['test_event_code'] = $test_event_code;
}

$t = time();
foreach ($payload['data'] as &$ev) {
    if (empty($ev['event_time'])) {
        $ev['event_time'] = $t;
    }
    if (empty($ev['event_id'])) {
        $external_id = isset($ev['user_data']['external_id']) ? $ev['user_data']['external_id'] : 'resend_' . uniqid();
        $ev['event_id'] = hash('sha256', ($ev['event_name'] ?? 'Event') . '_' . $external_id . '_' . $ev['event_time'] . '_' . uniqid('', true));
    }
}
unset($ev);

$url = sprintf(FB_EVENTS_ENDPOINT, $new_pixel_id);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload),
]);

$response = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

$logPayload = $payload;
$logPayload['access_token'] = '[REDACTED]';
$logEntry = [
    'date'     => date(DATE_ATOM),
    'events'   => array_map(function ($e) {
        return [
            'event_name' => $e['event_name'] ?? 'unknown', 
            'event_time' => $e['event_time'] ?? null,
            'event_id' => $e['event_id'] ?? null
        ];
    }, $eventsToSend),
    'request'  => $logPayload,
    'response' => $response,
    'http_code'=> $httpCode,
];

$newLogName = log_file_for_pixel($new_pixel_id);
$newLogPath = LOGS_DIR . '/' . $newLogName . '.jsonl';
file_put_contents($newLogPath, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);

$ok = ($httpCode >= 200 && $httpCode < 300) && empty($curlErr);

$out = [
    'ok' => $ok,
    'http_code' => $httpCode,
    'response' => $response,
    'curl_error' => $curlErr ?: null,
    'pixel_id' => $new_pixel_id,
    'events_sent' => count($eventsToSend),
];
if ($source_pixel_id !== '') {
    $out['source_pixel_id'] = $source_pixel_id;
}
echo json_encode($out);
