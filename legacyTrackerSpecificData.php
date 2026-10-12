<?php

// Fetch SubID/ClickID
$external_id = $_GET['external_id'] ?? null;

// Timestamp (date или date_old из постбека)
$time = isset($_GET['date']) ? (int) $_GET['date'] : (isset($_GET['date_old']) ? (int) $_GET['date_old'] : time());

// Check for FBCLID presence (sub_id_10)
$fbclid_raw = $_GET['sub_id_10'] ?? null;
if (empty($fbclid_raw) || $fbclid_raw === "null" || $fbclid_raw === "{fbclid}" || $fbclid_raw === "{{fbclid}}") {
    $fbclid = '';
} else {
    $fbclid = (strpos($fbclid_raw, "fb.") !== false) ? $fbclid_raw : ("fb.1." . $time . "." . $fbclid_raw);
}

// Pixel ID (sub_id_11)
$pixel_id = isset($_GET['sub_id_11']) ? trim((string) $_GET['sub_id_11']) : '';
if ($pixel_id === '' || $pixel_id === "null") {
    die("Pixel ID is missing");
}

// Access Token (sub_id_12)
$access_token = isset($_GET['sub_id_12']) ? trim((string) $_GET['sub_id_12']) : '';
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
