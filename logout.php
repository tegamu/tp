<?php
include 'config.php';

if (!isset($_COOKIE['sessionId'])) {
    echo "로그인 안됨";
    exit;
}

$session = $_COOKIE['sessionId'];

$ch = curl_init("$API_BASE/logout");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Cookie: sessionId=$session"
]);

$response = curl_exec($ch);

setcookie("sessionId", "", time()-3600, "/"); // 삭제

echo "<pre>$response</pre>";

