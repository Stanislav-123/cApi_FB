<?php
/**
 * Отправка событий в Facebook CAPI с записью лога по пикселю
 * POST: pixel_id, access_token, data (JSON array of events) [, test_event_code ]
 */
header('Content-Type: application/json; charset=utf-8');

require_once dirname(__DIR__) . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$input = file_get_contents('php://input');
$body = json_decode($input, true);
if (!$body) {
    $body = $_POST;
    if (!empty($body['data']) && is_string($body['data'])) {
        $body['data'] = json_decode($body['data'], true);
    }
}

$pixel_id     = trim((string) ($body['pixel_id'] ?? ''));
$access_token = trim((string) ($body['access_token'] ?? ''));
$events       = $body['data'] ?? null;
$test_event_code = isset($body['test_event_code']) ? trim((string) $body['test_event_code']) : null;

if (!$pixel_id || !$access_token) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Требуются pixel_id и access_token']);
    exit;
}

if (!is_array($events) || empty($events)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Требуется массив data с событиями']);
    exit;
}

$payload = [
    'data' => $events,
    'access_token' => $access_token,
];
if ($test_event_code !== null && $test_event_code !== '') {
    $payload['test_event_code'] = $test_event_code;
}

// event_time — текущее, если не задано; event_id — если не задан, генерируем
$t = time();
foreach ($payload['data'] as &$ev) {
    if (empty($ev['event_time'])) {
        $ev['event_time'] = $t;
    } else {
        $ev['event_time'] = validate_event_time($ev['event_time']);
    }
    if (empty($ev['event_id'])) {
        $external_id = isset($ev['user_data']['external_id']) ? $ev['user_data']['external_id'] : 'api_' . uniqid();
        $ev['event_id'] = hash('sha256', ($ev['event_name'] ?? 'Event') . '_' . $external_id . '_' . $ev['event_time'] . '_' . uniqid('', true));
    }
    // Добавляем currency по умолчанию если это Purchase и не указано
    if (($ev['event_name'] ?? '') === 'Purchase' && empty($ev['custom_data']['currency'])) {
        if (!isset($ev['custom_data'])) {
            $ev['custom_data'] = [];
        }
        $ev['custom_data']['currency'] = DEFAULT_CURRENCY;
    }
}
unset($ev);

$url = sprintf(FB_EVENTS_ENDPOINT, $pixel_id);

$result = curl_post_with_retry($url, $payload);

$logPayload = $payload;
unset($logPayload['access_token']);
$logPayload['access_token'] = '[REDACTED]';

$logEntry = [
    'date'    => date(DATE_ATOM),
    'events'  => array_map(function ($e) {
        return [
            'event_name' => $e['event_name'] ?? 'unknown',
            'event_time' => $e['event_time'] ?? null,
            'event_id' => $e['event_id'] ?? null,
        ];
    }, $events),
    'request' => $logPayload,
    'response' => $result['response'],
    'http_code' => $result['http_code'],
];

$logName = log_file_for_pixel($pixel_id);
$logPath = LOGS_DIR . '/' . $logName . '.jsonl';
file_put_contents($logPath, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);

$ok = ($result['http_code'] >= 200 && $result['http_code'] < 300) && empty($result['error']);

echo json_encode([
    'ok' => $ok,
    'http_code' => $result['http_code'],
    'response' => $result['response'],
    'curl_error' => $result['error'],
    'pixel_id' => $pixel_id,
]);
exit;
