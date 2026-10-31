<?php
/**
 * Общая конфигурация apifb
 * Facebook Graph API: https://developers.facebook.com/docs/graph-api/
 * Актуальная версия (октябрь 2026): v21.0. Используем v21.0 как стабильную.
 */
define('FB_GRAPH_API_VERSION', 'v21.0');
define('FB_EVENTS_ENDPOINT', 'https://graph.facebook.com/' . FB_GRAPH_API_VERSION . '/%s/events');

define('LOGS_DIR', __DIR__ . '/logs');

if (!is_dir(LOGS_DIR)) {
    mkdir(LOGS_DIR, 0755, true);
}

/**
 * Безопасное имя файла лога по pixel_id (только цифры)
 */
function log_file_for_pixel($pixel_id) {
    $safe = preg_replace('/[^0-9]/', '', (string) $pixel_id);
    return $safe ?: 'unknown';
}

/**
 * Записать одну отправку в лог по пикселю (logs/{pixel_id}.jsonl)
 * $events — массив событий (data), $payload — полный payload с access_token (будет заредактирован)
 */
function apifb_log_entry($pixel_id, array $events, array $payload, $response, $http_code) {
    $logPayload = $payload;
    $logPayload['access_token'] = '[REDACTED]';
    $entry = [
        'date'      => date(DATE_ATOM),
        'events'    => array_map(function ($e) {
            return [
                'event_name' => $e['event_name'] ?? 'unknown', 
                'event_time' => $e['event_time'] ?? null,
                'event_id' => $e['event_id'] ?? null
            ];
        }, $events),
        'request'   => $logPayload,
        'response'  => $response,
        'http_code' => (int) $http_code,
    ];
    $path = LOGS_DIR . '/' . log_file_for_pixel($pixel_id) . '.jsonl';
    file_put_contents($path, json_encode($entry) . "\n", FILE_APPEND | LOCK_EX);
}

/**
 * Генерация уникального event_id для дедупликации
 */
function generate_event_id($event_name, $external_id, $timestamp) {
    return hash('sha256', $event_name . '_' . $external_id . '_' . $timestamp . '_' . uniqid('', true));
}

/**
 * Записать отправку в старый текстовый лог ({event}_facebook_log.txt)
 * Формат как в прошлой версии: блок с разделителем, Date, Action, Click ID, fbc, Raw request, URL, Data, Response
 */
function apifb_log_text($event, $external_id, $fbclid, $raw_request, $url, array $payload, $response) {
    $payloadSafe = $payload;
    $payloadSafe['access_token'] = '[REDACTED]';
    $filename = strtolower($event) . '_facebook_log.txt';
    if (file_exists($filename)) {
        $fileSize = filesize($filename);
        if ($fileSize / 1024 / 1024 > 50) {
            rename($filename, $filename . '_' . time());
            file_put_contents($filename, "Last cleared (automatically): " . date(DATE_ATOM) . "\n", FILE_APPEND);
        }
    }
    $text  = PHP_EOL . PHP_EOL . "-------------------------------";
    $text .= PHP_EOL . "Date: " . date(DATE_ATOM);
    $text .= PHP_EOL . "Action: " . $event;
    $text .= PHP_EOL . "Click ID/Subid: " . ($external_id !== null && $external_id !== '' ? $external_id : 'EXTERNAL_ID_PLACEHOLDER');
    $text .= PHP_EOL . "FB Click ID (fbc/fbclid): " . ($fbclid !== null && $fbclid !== '' ? $fbclid : 'FBC_PLACEHOLDER');
    $text .= PHP_EOL . "Raw request: " . ($raw_request !== null && $raw_request !== '' ? $raw_request : 'https://example.com/request');
    $text .= PHP_EOL . "URL: " . $url;
    $text .= PHP_EOL . "Data: " . PHP_EOL . json_encode($payloadSafe, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    $text .= PHP_EOL . "Response: " . PHP_EOL . $response;
    file_put_contents($filename, $text, FILE_APPEND | LOCK_EX);
}
