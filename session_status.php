<?php
include 'config.php'; // $API_BASE

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

// 1) 게임 세션 상태 조회
$statusUrl = rtrim($API_BASE, '/') . '/game-sessions/' . urlencode($targetSessionId);

$ch = curl_init($statusUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    'Cookie: sessionId=' . $sessionIdCookie,
]);

$statusBody = curl_exec($ch);
$statusErr  = curl_error($ch);
$statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$statusJson = null;
if ($statusBody !== false) {
    $statusJson = json_decode($statusBody, true);
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8" />
  <title>세션 상태</title>
</head>
<body>
  <h1>세션 상태</h1>

  <p>조회 중인 세션 ID: <?php echo htmlspecialchars($targetSessionId, ENT_QUOTES, 'UTF-8'); ?></p>

  <h2>세션 상태 API 응답</h2>
  <p>HTTP STATUS: <?php echo htmlspecialchars((string)$statusCode, ENT_QUOTES, 'UTF-8'); ?></p>
  <pre><?php
    if ($statusBody === false) {
        echo htmlspecialchars("cURL error: " . $statusErr, ENT_QUOTES, 'UTF-8');
    } else {
        echo htmlspecialchars($statusBody, ENT_QUOTES, 'UTF-8');
    }
  ?></pre>

  <?php if ($statusCode === 200 && is_array($statusJson)): ?>
    <h3>파싱된 상태</h3>
    <ul>
      <li>status: <?php echo htmlspecialchars($statusJson['status'] ?? '', ENT_QUOTES, 'UTF-8'); ?></li>
      <li>dcvEndpoint: <?php echo htmlspecialchars($statusJson['dcvEndpoint'] ?? '', ENT_QUOTES, 'UTF-8'); ?></li>
      <li>instanceId: <?php echo htmlspecialchars($statusJson['instanceId'] ?? '', ENT_QUOTES, 'UTF-8'); ?></li>
    </ul>

    <?php
      $dcvEndpoint = $statusJson['dcvEndpoint'] ?? '';
      $port        = $statusJson['port'] ?? 8443;
      $statusVal   = $statusJson['status'] ?? '';
      $dcvUrl      = '';

      if (!empty($dcvEndpoint)) {
          $dcvUrl = 'https://' . $dcvEndpoint;
          if (!empty($port)) {
              $dcvUrl .= ':' . (int)$port;
          }
          // 필요하면 경로 추가:
          // $dcvUrl .= '/#connect?...';
      }
    ?>

    <?php if (!empty($dcvUrl)): ?>
      <h3>DCV 접속</h3>
      <p>현재 상태: <?php echo htmlspecialchars($statusVal, ENT_QUOTES, 'UTF-8'); ?></p>
      <p>
        <a href="<?php echo htmlspecialchars($dcvUrl, ENT_QUOTES, 'UTF-8'); ?>"
           target="_blank" rel="noopener noreferrer">
          <button type="button">게임 접속하기 (DCV Gateway)</button>
        </a>
      </p>
      <p style="font-size: 0.9rem; color: #666;">
        ※ 이 주소는 Lambda에서 내려준 dcvEndpoint와 port를 사용합니다.  
        PHP 코드에는 게이트웨이 IP/도메인을 직접 적지 않습니다.
      </p>
    <?php else: ?>
      <p style="color:red;">dcvEndpoint 정보가 아직 없습니다. (인스턴스 부팅 중일 수 있음)</p>
    <?php endif; ?>

  <?php endif; ?>

  <p><a href="session_create.php">세션 생성 페이지로 돌아가기</a></p>
</body>
</html>
