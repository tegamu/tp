<?php
include 'config.php';

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

// API 호출
$url = "$API_BASE/game-sessions/" . urlencode($targetSessionId);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Cookie: sessionId=$sessionIdCookie",
]);

$response = curl_exec($ch);
$status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// JSON 파싱
$data = null;
if ($response !== false && $status === 200) {
    $data = json_decode($response, true);
}

// DCV 접속 URL 만들기 (임시: https://{endpoint}:{port})
$dcvUrl = null;
if ($data &&
    ($data['status'] ?? '') === 'READY' &&
    !empty($data['dcvEndpoint']) &&
    !empty($data['port'])
) {
    // 나중에 CloudFront 도메인으로 바꾸면 여기만 수정하면 됨
    $host = $data['dcvEndpoint'];
    $port = $data['port'];
    $dcvUrl = "https://{$host}:{$port}";
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>게임 세션 상태</title>
</head>
<body>
  <h2>게임 세션 상태</h2>
  <p>
    <a href="session_create.php">← 세션 생성</a> |
    <a href="login.php">로그인</a> |
    <a href="me.php">내 정보</a>
  </p>

  <p>조회 중인 세션 ID: <strong><?php echo htmlspecialchars($targetSessionId); ?></strong></p>

  <pre>HTTP STATUS: <?php echo $status; ?>


<?php echo htmlspecialchars($response); ?></pre>

  <?php if ($dcvUrl): ?>
    <p style="margin-top: 16px;">
      <a href="<?php echo htmlspecialchars($dcvUrl); ?>" target="_blank" rel="noopener noreferrer">
        <button type="button">게임 접속하기 (DCV)</button>
      </a>
    </p>
    <p>※ 현재는 <code>https://<?php echo htmlspecialchars($data['dcvEndpoint']); ?>:<?php echo htmlspecialchars($data['port']); ?></code> 로 바로 접속합니다.<br>
       나중에 CloudFront 도메인/토큰 구조가 정해지면 이 URL만 수정</p>
  <?php else: ?>
    <p>status가 READY이고 dcvEndpoint/port가 내려오면 여기 DCV 접속 버튼이 나옴</p>
  <?php endif; ?>
</body>
</html>

