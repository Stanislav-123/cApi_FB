<?php

if (empty($_GET['name']))
{
    $name = "";

    if (empty($_GET['firstname'])){
        $firstname = "";
    }else{
        $firstname = hash('sha256', mb_strtolower($_GET['firstname']));
    }

    if (empty($_GET['lastname'])){
        $lastname = "";
    }else{
        $lastname = hash('sha256', mb_strtolower($_GET['lastname']));
    }
}
else
{
    $name = mb_strtolower($_GET['name']);
    $nameArr = explode(" ", $name);

    if (count($nameArr) == 1)
    {
        array_push($nameArr, "");
    }

    $firstname = hash('sha256', $nameArr[0]);
    $lastname = hash('sha256', $nameArr[1]);
}

if (empty($_GET['phone']))
{
    $phone = "";
}
else
{
    $phone = str_replace(" ", '', $_GET['phone']);
    $phone = str_replace("+", '', $phone);
    $phone = intval($phone);
    $phone = hash('sha256', $phone);
}

if (empty($_GET['sid'])){
        $subscription_id = "";
}
else
{
        $subscription_id = $_GET['sid'];
}

if (empty($_GET['dob']))
{
    $db = "";
}
else 
{
	$db = $_GET['db']; //Format: 19970216 = February, 16th 1997
    $db = hash('sha256', $db);
}

if (empty($_GET['email'])) {
    $em = "";
} else {
    $em = mb_strtolower($_GET['email']);
    $em = hash('sha256', $em);
}

$ct = isset($_GET['ct']) && $_GET['ct'] !== '' ? hash('sha256', mb_strtolower(trim((string) $_GET['ct']))) : '';
$country = isset($_GET['country']) && $_GET['country'] !== '' ? hash('sha256', mb_strtolower(trim((string) $_GET['country']))) : '';
$st_raw = isset($_GET['st']) ? mb_strtolower(trim((string) $_GET['st'])) : '';
$st = $st_raw !== '' ? (strpos($st_raw, '_') !== false ? hash('sha256', explode('_', $st_raw)[1]) : hash('sha256', $st_raw)) : '';
$client_user_agent = isset($_GET['client_user_agent']) ? (string) $_GET['client_user_agent'] : '';
$client_ip_address = isset($_GET['client_ip_address']) ? (string) $_GET['client_ip_address'] : '';