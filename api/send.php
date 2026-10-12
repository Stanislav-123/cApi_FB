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

// event_time — текущее, если не задано
$t = time();
foreach ($payload['data'] as &$ev) {
    if (empty($ev['event_time'])) {
        $ev['event_time'] = $t;
    }
}
unset($ev);

$url = sprintf(FB_EVENTS_ENDPOINT, $pixel_id);

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
unset($logPayload['access_token']);
$logPayload['access_token'] = '[REDACTED]';

$logEntry = [
    'date'    => date(DATE_ATOM),
    'events'  => array_map(function ($e) {
        return [
            'event_name' => $e['event_name'] ?? 'unknown',
            'event_time' => $e['event_time'] ?? null,
        ];
    }, $events),
    'request' => $logPayload,
    'response' => $response,
    'http_code' => $httpCode,
];

$logName = log_file_for_pixel($pixel_id);
$logPath = LOGS_DIR . '/' . $logName . '.jsonl';
file_put_contents($logPath, json_encode($logEntry) . "\n", FILE_APPEND | LOCK_EX);

$ok = ($httpCode >= 200 && $httpCode < 300) && empty($curlErr);

echo json_encode([
    'ok' => $ok,
    'http_code' => $httpCode,
    'response' => $response,
    'curl_error' => $curlErr ?: null,
    'pixel_id' => $pixel_id,
]);
exit;
