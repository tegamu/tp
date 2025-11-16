<?php
// session_status.php
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

// 예시: GET /game-sessions/{id}
$url = "$API_BASE/game-sessions/" . urlencode($targetSessionId);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Cookie: sessionId=$sessionIdCookie"
]);

$response = curl_exec($ch);
$status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>게임 세션 상태</title>
</head>
<body>
  <h2>게임 세션 상태</h2>
  <p><a href="session_create.php">← 세션 생성</a></p>

  <p>조회 중인 세션 ID: <strong><?php echo htmlspecialchars($targetSessionId); ?></strong></p>

  <pre>HTTP STATUS: <?php echo $status; ?>

<?php echo htmlspecialchars($response); ?></pre>

  <p>status가 READY이고, 응답에 DCV 주소/포트가 들어오면 그걸로
     나중에 DCV Web Client 쪽으로 연결 버튼 만들면 됨.</p>
</body>
</html>

