<?php
include 'config.php';

if (!isset($_COOKIE['sessionId'])) {
    echo "로그인 필요함";
    exit;
}

$session = $_COOKIE['sessionId'];

$ch = curl_init("$API_BASE/me");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Cookie: sessionId=$session"
]);

$response = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

echo "<pre>STATUS: $status\n$response</pre>";

