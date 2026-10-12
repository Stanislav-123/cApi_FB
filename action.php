<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

$event = $_GET['action'] ?? "Lead";

// Safe placeholders for personal data
$em = "example@example.com";
$phone = hash('sha256', "0000000000");
$firstname = hash('sha256', "John");
$lastname = hash('sha256', "Doe");
$external_id = "EXTERNAL_ID_PLACEHOLDER";
$subscription_id = "SUBSCRIPTION_ID_PLACEHOLDER";
$client_ip_address = "127.0.0.1";
$client_user_agent = "Mozilla/5.0 (iPhone; CPU iPhone OS 13_3 like Mac OS X)";
$fbclid = "FBC_PLACEHOLDER";
$ct = hash('sha256', "city");
$db = hash('sha256', "20000101");
$st = hash('sha256', "state");
$country = hash('sha256', "US");
$event_source_url = "https://example.com";
$revenue = 1.0;

// Conversion Data
$data = array(
    'data' => array(
        array(
            'event_name' => $event,
            'event_time' => time(),
            'user_data' => array(
                "em" => $em,
                "ph" => $phone,
                "external_id" => $external_id,
                "client_ip_address" => $client_ip_address,
                "client_user_agent" => $client_user_agent,
                "fbc" => $fbclid,
                "subscription_id" => $subscription_id,
                "fn" => $firstname,
                "ln" => $lastname,
                "ct" => $ct,
                "db" => $db,
                "st" => $st,
                "country" => $country,
            ),
            'custom_data' => array(
                'currency' => 'USD',
                'value' => $revenue
            ),
            'event_source_url' => $event_source_url,
            'action_source' => 'website'
        )
    ),
    'access_token' => "ACCESS_TOKEN_PLACEHOLDER"
);

require_once(__DIR__ . '/config.php');
// API URL (safe)
$pixel_id = "PIXEL_ID_PLACEHOLDER";
$url = sprintf(FB_EVENTS_ENDPOINT, $pixel_id);

// Initialize cURL
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

// Execute cURL request
$response = curl_exec($ch);

// ---------- Logging (SAFE) ----------
$filename = 'action_facebook_log.txt';
if (file_exists($filename) && filesize($filename) / 1024 / 1024 > 50) {
    $newFilename = $filename . '_' . time();
    rename($filename, $newFilename);
    file_put_contents($filename, "Last cleared (automatically): " . date(DATE_ATOM), FILE_APPEND);
}

$text  = PHP_EOL . "-------------------------------";
$text .= PHP_EOL . "Date: " . date(DATE_ATOM);
$text .= PHP_EOL . "Action: " . $event;
$text .= PHP_EOL . "Click ID/Subid: EXTERNAL_ID_PLACEHOLDER";
$text .= PHP_EOL . "FB Click ID (fbc/fbclid): FBC_PLACEHOLDER";
$text .= PHP_EOL . "Raw request: https://example.com/request";
$text .= PHP_EOL . "URL: " . $url;
$text .= PHP_EOL . "Data: " . PHP_EOL . json_encode($data, JSON_PRETTY_PRINT);
$text .= PHP_EOL . "Response: " . PHP_EOL . $response;

file_put_contents($filename, $text, FILE_APPEND);
?>
