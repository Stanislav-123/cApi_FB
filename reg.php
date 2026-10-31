<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

$event = "CompleteRegistration";

require_once __DIR__ . '/legacyTrackerSpecificData.php';
require_once __DIR__ . '/collectPersonalData.php';
require_once __DIR__ . '/config.php';

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

$event_id = generate_event_id($event, $external_id ?? 'unknown', $event_time ?? time());

$data = [
    'data' => [
        [
            'event_name' => $event,
            'event_time' => $event_time ?? time(),
            'event_id' => $event_id,
            'user_data' => $user_data,
            'event_source_url' => $event_source_url ?? 'https://facebook.com/',
            'action_source' => 'website',
        ],
    ],
    'access_token' => $access_token,
];

$url = sprintf(FB_EVENTS_ENDPOINT, $pixel_id);
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

$response = curl_exec($ch);
$httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

apifb_log_entry($pixel_id, $data['data'], $data, $response, $httpCode);

$raw_request = isset($_SERVER['REQUEST_URI'])
    ? ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? '') . $_SERVER['REQUEST_URI'])
    : '';
apifb_log_text($event, $external_id ?? '', $fbclid ?? '', $raw_request, $url, $data, $response);
