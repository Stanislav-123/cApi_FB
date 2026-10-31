<?php
/**
 * Общая конфигурация apifb
 * Facebook Graph API: https://developers.facebook.com/docs/graph-api/
 * Актуальная версия (октябрь 2026): v26.0. Используем v22.0 как минимальную стабильную.
 */

// Загрузка .env (если есть)
if (file_exists(__DIR__ . '/.env')) {
    $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0 || strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key && !isset($_ENV[$key])) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

define('FB_GRAPH_API_VERSION', getenv('FB_GRAPH_API_VERSION') ?: 'v22.0');
define('FB_EVENTS_ENDPOINT', 'https://graph.facebook.com/' . FB_GRAPH_API_VERSION . '/%s/events');

define('LOGS_DIR', __DIR__ . '/logs');

// Retry конфигурация
define('CURL_RETRY_COUNT', (int) (getenv('CURL_RETRY_COUNT') ?: 3));
define('CURL_RETRY_DELAY', (int) (getenv('CURL_RETRY_DELAY') ?: 2));

// Валюта по умолчанию
define('DEFAULT_CURRENCY', getenv('DEFAULT_CURRENCY') ?: 'USD');

// Default credentials (опционально)
define('DEFAULT_PIXEL_ID', getenv('DEFAULT_PIXEL_ID') ?: '');
define('DEFAULT_ACCESS_TOKEN', getenv('DEFAULT_ACCESS_TOKEN') ?: '');

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
 * Валидация event_time (Unix timestamp или ISO date)
 * Возвращает валидный Unix timestamp или null
 * Facebook отклоняет события старше 7 дней или из будущего
 */
function validate_event_time($input) {
    if (empty($input)) {
        return time();
    }
    
    // Если это Unix timestamp
    if (is_numeric($input)) {
        $ts = (int) $input;
    } else {
        // Попытка парсинга даты (ISO 8601, RFC 2822, etc)
        $ts = strtotime($input);
        if ($ts === false) {
            return time(); // Невалидный формат
        }
    }
    
    $now = time();
    $seven_days_ago = $now - (7 * 24 * 60 * 60);
    
    // Проверка: не из будущего и не старше 7 дней
    if ($ts > $now + 300) { // +5 минут на clock skew
        return time();
    }
    if ($ts < $seven_days_ago) {
        return time();
    }
    
    return $ts;
}

/**
 * Отправка запроса с retry и exponential backoff
 * @return array ['response' => string, 'http_code' => int, 'error' => string|null]
 */
function curl_post_with_retry($url, $payload, $max_retries = CURL_RETRY_COUNT, $base_delay = CURL_RETRY_DELAY) {
    $attempt = 0;
    $last_error = null;
    
    while ($attempt < $max_retries) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);
        
        $response = curl_exec($ch);
        $http_code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        // Успех (200-299) или клиентская ошибка (400-499) — не ретраим
        if (($http_code >= 200 && $http_code < 300) || ($http_code >= 400 && $http_code < 500)) {
            return [
                'response' => $response,
                'http_code' => $http_code,
                'error' => $curl_error ?: null,
            ];
        }
        
        // Сетевая ошибка или 5xx — ретраим
        $last_error = $curl_error ?: "HTTP $http_code";
        $attempt++;
        
        if ($attempt < $max_retries) {
            $delay = $base_delay * pow(2, $attempt - 1); // Exponential backoff
            sleep($delay);
        }
    }
    
    return [
        'response' => '',
        'http_code' => 0,
        'error' => "Failed after $max_retries attempts: $last_error",
    ];
}

/**
 * HTTP Basic Auth для веб-интерфейса
 * Вызывать в начале index.php
 */
function require_auth() {
    $user = getenv('AUTH_USER');
    $pass = getenv('AUTH_PASS');
    
    if (!$user || !$pass) {
        return; // Auth отключен
    }
    
    if (!isset($_SERVER['PHP_AUTH_USER']) || 
        $_SERVER['PHP_AUTH_USER'] !== $user || 
        $_SERVER['PHP_AUTH_PW'] !== $pass) {
        header('WWW-Authenticate: Basic realm="apifb Admin"');
        header('HTTP/1.0 401 Unauthorized');
        echo 'Authentication required';
        exit;
    }
}

/**
 * IP Whitelist для постбеков
 * Вызывать в начале lead.php, sale.php, reg.php, rebill.php
 */
function check_ip_whitelist() {
    $allowed = getenv('ALLOWED_IPS');
    
    if (!$allowed) {
        return; // Whitelist отключен
    }
    
    $client_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!$client_ip) {
        die('IP detection failed');
    }
    
    $allowed_ips = array_map('trim', explode(',', $allowed));
    
    foreach ($allowed_ips as $allowed_ip) {
        // Поддержка CIDR (например: 192.168.1.0/24)
        if (strpos($allowed_ip, '/') !== false) {
            list($subnet, $mask) = explode('/', $allowed_ip);
            if (ip_in_subnet($client_ip, $subnet, (int) $mask)) {
                return;
            }
        } else {
            if ($client_ip === $allowed_ip) {
                return;
            }
        }
    }
    
    http_response_code(403);
    die('IP not whitelisted');
}

/**
 * Проверка IP в подсети (CIDR)
 */
function ip_in_subnet($ip, $subnet, $mask) {
    $ip_long = ip2long($ip);
    $subnet_long = ip2long($subnet);
    
    if ($ip_long === false || $subnet_long === false) {
        return false;
    }
    
    $mask_long = -1 << (32 - $mask);
    return ($ip_long & $mask_long) === ($subnet_long & $mask_long);
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
