<?php
include 'config.php'; // 여기서 $API_BASE 만 쓰면 됨. IP 안 씀.

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

// 1) 게이트웨이 URL 조회
$cfgUrl = rtrim($API_BASE, '/') . '/config/dcv-gateway';
$ch1 = curl_init($cfgUrl);
curl_setopt($ch1, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch1, CURLOPT_TIMEOUT, 5);
$cfgBody = curl_exec($ch1);
$cfgCode = curl_getinfo($ch1, CURLINFO_HTTP_CODE);
curl_close($ch1);

$gatewayUrl = '';
if ($cfgCode === 200 && $cfgBody !== false) {
    $cfgJson = json_decode($cfgBody, true);
    if (is_array($cfgJson) && isset($cfgJson['dcvGatewayUrl'])) {
        $gatewayUrl = $cfgJson['dcvGatewayUrl'];
    }
}

// 2) 세션 상태 조회 (기존 API)
$statusUrl = rtrim($API_BASE, '/') . '/game/session/status?sessionId=' . urlencode($targetSessionId);
$ch2 = curl_init($statusUrl);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_TIMEOUT, 5);
$statusBody = curl_exec($ch2);
$statusCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

$statusJson = null;
if ($statusCode === 200 && $statusBody !== false) {
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

  <p>조회 중인 세션 ID: <strong><?php echo htmlspecialchars($targetSessionId); ?></strong></p>

  <h2>세션 상태 API 응답</h2>
  <p>HTTP STATUS: <?php echo htmlspecialchars((string)$statusCode); ?></p>
  <pre><?php echo htmlspecialchars($statusBody ?? '', ENT_QUOTES, 'UTF-8'); ?></pre>

  <?php if ($statusJson): ?>
    <h3>파싱된 데이터</h3>
    <pre><?php echo htmlspecialchars(json_encode($statusJson, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8'); ?></pre>
  <?php endif; ?>

  <hr>

  <?php if ($gatewayUrl): ?>
    <?php
      // 게이트웨이 URL은 Lambda에서 내려준 값 사용
      // PHP 쪽은 IP/도메인 전혀 모름
      $fullGatewayUrl = $gatewayUrl . '?sessionId=' . urlencode($targetSessionId);
    ?>
    <p>
      <a href="<?php echo htmlspecialchars($fullGatewayUrl, ENT_QUOTES, 'UTF-8'); ?>"
         target="_blank" rel="noopener noreferrer">
        <button type="button">게임 접속하기 (DCV Gateway)</button>
      </a>
    </p>
    <p style="font-size: 0.9rem; color: #666;">
      ※ 게이트웨이 주소는 API에서 받아온 값이며, PHP 코드에는 IP/도메인을 직접 적지 않습니다.
    </p>
  <?php else: ?>
    <p style="color:red;">게이트웨이 설정 정보를 가져오지 못했습니다. (<?php echo htmlspecialchars((string)$cfgCode); ?>)</p>
    <pre><?php echo htmlspecialchars($cfgBody ?? '', ENT_QUOTES, 'UTF-8'); ?></pre>
  <?php endif; ?>

  <p><a href="session_create.php">세션 생성 페이지로 돌아가기</a></p>
</body>
</html>
