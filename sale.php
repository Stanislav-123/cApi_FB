<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

$event = "Purchase";

require_once __DIR__ . '/config.php';
check_ip_whitelist(); // Security: IP Whitelist

require_once __DIR__ . '/legacyTrackerSpecificData.php';
require_once __DIR__ . '/collectPersonalData.php';

$user_data = array_filter([
    'em' => $em ?? '',
    'ph' => $phone ?? '',
    'external_id' => $external_id ?? '',
    'client_ip_address' => $client_ip_address ?? '',
    'client_user_agent' => $client_user_agent ?? '',
    'fbc' => $fbclid ?? '',
    'fbp' => $fbp ?? '',
    'subscription_id' => $subscription_id ?? '',
    'fn' => $firstname ?? '',
    'ln' => $lastname ?? '',
    'ct' => $ct ?? '',
    'db' => $db ?? '',
    'st' => $st ?? '',
    'country' => $country ?? '',
], function ($v) { return $v !== null && $v !== ''; });

$validated_event_time = validate_event_time($event_time ?? null);
$event_id = generate_event_id($event, $external_id ?? 'unknown', $validated_event_time);

$data = [
    'data' => [
        [
            'event_name' => $event,
            'event_time' => $validated_event_time,
            'event_id' => $event_id,
            'user_data' => $user_data,
            'custom_data' => [
                'currency' => $currency ?? DEFAULT_CURRENCY,
                'value' => (float) ($revenue ?? 1),
            ],
            'event_source_url' => $event_source_url ?? 'https://facebook.com/',
            'action_source' => 'website',
        ],
    ],
    'access_token' => $access_token,
];

$url = sprintf(FB_EVENTS_ENDPOINT, $pixel_id);
$result = curl_post_with_retry($url, $data);

apifb_log_entry($pixel_id, $data['data'], $data, $result['response'], $result['http_code']);

$raw_request = isset($_SERVER['REQUEST_URI'])
    ? ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $_SERVER['REQUEST_URI'])
    : '';
apifb_log_text($event, $external_id ?? '', $fbclid ?? '', $raw_request, $url, $data, $result['response']);
