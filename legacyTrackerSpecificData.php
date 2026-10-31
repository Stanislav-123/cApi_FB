<?php

// Fetch SubID/ClickID
$external_id = $_GET['external_id'] ?? null;

// Timestamp (date или date_old из постбека)
$event_time = isset($_GET['date']) ? (int) $_GET['date'] : (isset($_GET['date_old']) ? (int) $_GET['date_old'] : time());

// Check for FBCLID presence (sub_id_10) для формирования fbc
$fbclid_raw = $_GET['sub_id_10'] ?? null;
if (empty($fbclid_raw) || $fbclid_raw === "null" || $fbclid_raw === "{fbclid}" || $fbclid_raw === "{{fbclid}}") {
    $fbclid = '';
} else {
    $fbclid = (strpos($fbclid_raw, "fb.") !== false) ? $fbclid_raw : ("fb.1." . $event_time . "." . $fbclid_raw);
}

// FBP cookie (sub_id_14) — если передается из браузерного пикселя
$fbp = isset($_GET['sub_id_14']) && $_GET['sub_id_14'] !== '' && $_GET['sub_id_14'] !== 'null' ? trim((string) $_GET['sub_id_14']) : '';

// Pixel ID (sub_id_11)
$pixel_id = isset($_GET['sub_id_11']) ? trim((string) $_GET['sub_id_11']) : DEFAULT_PIXEL_ID;
if ($pixel_id === '' || $pixel_id === "null") {
    die("Pixel ID is missing");
}

// Access Token (sub_id_12)
$access_token = isset($_GET['sub_id_12']) ? trim((string) $_GET['sub_id_12']) : DEFAULT_ACCESS_TOKEN;
if ($access_token === '' || $access_token === "null") {
    die("Access Token is missing");
}

// Event URL
$event_source_url = $_GET['sub_id_13'] ?? "https://facebook.com/";
if (!preg_match('#^https?://#', $event_source_url)) {
    $event_source_url = "https://" . $event_source_url;
}

// Revenue
$revenue = $_GET['revenue'] ?? 1;
$revenue = floatval($revenue);
if ($revenue <= 0) {
    $revenue = 1;
}

// Currency (из GET или .env)
$currency = isset($_GET['currency']) && $_GET['currency'] !== '' 
    ? strtoupper(trim((string) $_GET['currency'])) 
    : DEFAULT_CURRENCY;
