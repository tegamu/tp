]<?php
// session_status.php
// - 특정 게임 세션 ID의 상태를 조회
// - status 가 READY 이고 dcvEndpoint/port 가 내려오면 "게임 접속하기 (DCV)" 버튼 노출

include 'config.php'; // 여기에서 $API_BASE 가 정의되어 있다고 가정

if (!isset($_COOKIE['sessionId'])) {
    echo "로그인 후 이용 가능합니다. <a href='login.php'>로그인하기</a>";
    exit;
}

$sessionIdCookie = $_COOKIE['sessionId'];
$targetSessionId = $_GET['id'] ?? '';

if ($targetSessionId === '') {
    echo "sessionId가 없습니다. <a href='session_create.php'>세션 생성 페이지로</a>";
    exit;
}

$error = "";
$data  = null;
$rawBody = null;
$httpCode = null;

// Lambda (GET /game-sessions/{id}) 호출
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $API_BASE . "/game-sessions/" . urlencode($targetSessionId));
curl_setopt($ch, CURLOPT_HTTPGET, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Cookie: sessionId=" . $sessionIdCookie,
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);

$resp = curl_exec($ch);
if ($resp === false) {
    $error = "세션 상태 조회 실패: " . curl_error($ch);
} else {
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header     = substr($resp, 0, $headerSize);
    $body       = substr($resp, $headerSize);
    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    $rawBody  = $body;
    $httpCode = $statusCode;

    $decoded = json_decode($body, true);
    if ($statusCode >= 200 && $statusCode < 300 && is_array($decoded)) {
        $data = $decoded;
    } else {
        $error = "세션 상태 조회 실패 (HTTP {$statusCode}): " . htmlspecialchars($body);
    }
}
curl_close($ch);

// dcvEndpoint / port 계산
$dcvUrl = "";
if ($data && isset($data['dcvEndpoint'])) {
    $dcvEndpoint = $data['dcvEndpoint'];
} elseif ($data && isset($data['dcv_endpoint'])) {
    $dcvEndpoint = $data['dcv_endpoint'];
} else {
    $dcvEndpoint = "";
}

$port = 8443;
if ($data && isset($data['port']) && is_numeric($data['port'])) {
    $port = intval($data['port']);
}

$status = $data['status'] ?? ($data['ec2State'] ?? 'UNKNOWN');

if ($dcvEndpoint && strtoupper($status) === 'READY') {
    // 예: https://<public-ip>:8443
    $dcvUrl = "https://" . $dcvEndpoint . ":" . $port;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>게임 세션 상태</title>
</head>
<body>
    <h1>게임 세션 상태</h1>
    <p><a href="session_create.php">← 세션 생성 페이지로</a></p>

    <p>조회 중인 세션 ID: <code><?php echo htmlspecialchars($targetSessionId); ?></code></p>

    <?php if ($error): ?>
        <p style="color: red;"><?php echo $error; ?></p>
    <?php endif; ?>

    <?php if ($data): ?>
        <h2>상태 정보</h2>
        <pre><?php echo htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
    <?php endif; ?>

    <?php if ($dcvUrl): ?>
        <p style="margin-top: 16px;">
            <a href="<?php echo htmlspecialchars($dcvUrl); ?>" target="_blank" rel="noopener noreferrer">
                <button type="button">게임 접속하기 (DCV)</button>
            </a>
        </p>
        <p>
            현재는 <code><?php echo htmlspecialchars($dcvUrl); ?></code> 로 바로 접속합니다.<br>
            이후 CloudFront/게이트웨이를 붙이면 이 URL만 교체하면 됩니다.
        </p>
    <?php else: ?>
        <p>status가 READY이고 dcvEndpoint/port가 내려오면 여기 DCV 접속 버튼이 나옵니다.</p>
    <?php endif; ?>
</body>
</html>
