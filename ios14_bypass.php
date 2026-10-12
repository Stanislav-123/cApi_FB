<?php

function generateiOSUserAgent($country) {

    $browsers = [
        'Chrome' => 'Chrome/78.0.3904.108 Mobile Safari/537.36',
        'Safari' => 'Safari/605.1.15'
    ];

    $languages = [
        'US' => 'en-US',
        'FR' => 'fr-FR',
        'DE' => 'de-DE',
        'ES' => 'es-ES',
        'IT' => 'it-IT',
        'JP' => 'ja-JP',
        'CN' => 'zh-CN',
        'RU' => 'ru-RU',
        'BR' => 'pt-BR',
        'IN' => 'hi-IN',
        'CA' => 'en-CA',
        'AU' => 'en-AU',
        'GB' => 'en-GB',
        'MX' => 'es-MX',
        'KR' => 'ko-KR',
        'SA' => 'ar-SA',
        'SE' => 'sv-SE',
        'NL' => 'nl-NL',
        'TR' => 'tr-TR',
        'EG' => 'ar-EG',
        'ZA' => 'en-ZA',
        'NG' => 'en-NG',
        'AR' => 'es-AR',
        'CO' => 'es-CO',
        'PE' => 'es-PE',
        'VE' => 'es-VE',
        'CL' => 'es-CL',
        'BE' => 'nl-BE',
        'CH' => 'de-CH',
        'AT' => 'de-AT',
        'FI' => 'fi-FI',
        'NO' => 'no-NO',
        'DK' => 'da-DK',
        'GR' => 'el-GR',
        'PL' => 'pl-PL',
        'CZ' => 'cs-CZ',
        'HU' => 'hu-HU',
        'PT' => 'pt-PT',
        'RO' => 'ro-RO',
        'SK' => 'sk-SK',
        'BG' => 'bg-BG',
        'HR' => 'hr-HR',
        'SI' => 'sl-SI',
        'IE' => 'en-IE',
        'NZ' => 'en-NZ',
        'MY' => 'ms-MY',
        'SG' => 'en-SG',
        'TH' => 'th-TH',
        'VN' => 'vi-VN',
        'PH' => 'en-PH',
        'ID' => 'id-ID',
        'BD' => 'bn-BD',
        'PK' => 'ur-PK',
        'NG' => 'en-NG',
        'KE' => 'en-KE',
        'GH' => 'en-GH',
        'ET' => 'am-ET',
        'TZ' => 'sw-TZ',

    ];

    $browser = $browsers[array_rand($browsers)];

    $language = isset($languages[$country]) ? $languages[$country] : 'en-US';

    $iosVersions = [
        '13.3.1', '13.3', '13.2.3', '13.2.2', '13.2', '13.1.3', '13.1.2', '13.1.1', '13.1', '13.0'
    ];
    $iosVersion = $iosVersions[array_rand($iosVersions)];

    $userAgent = "Mozilla/5.0 (iPhone; CPU iPhone OS $iosVersion like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0 $browser $language";

    return $userAgent;
}

$country_ios = isset($_GET['country']) ? strtoupper($_GET['country']) : 'US';
$client_user_agent = generateiOSUserAgent($country_ios);
?>