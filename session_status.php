<?php
include 'config.php'; // 여기서 $API_BASE 만 씀

// ⚙ DCV 게이트웨이 설정용 API (depxmf6mcb) – 필요 없으면 $API_BASE 재사용해도 됨
$GATEWAY_API_BASE = 'https://depxmf6mcb.execute-api.ap-northeast-2.amazonaws.com';

// 1) 로그인 세션 쿠키 확인
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

// -------------------------
// 2) 게임 세션 상태 조회 호출
// -------------------------
$statusUrl = rtrim($API_BASE, '/') . '/game-sessions/' . urlencode($targetSessionId);

$ch = curl_init($statusUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    // ❗ 로그인 세션 쿠키를 Lambda로 전달
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

// -----------------------------
// 3) DCV 게이트웨이 설정 조회 호출
// -----------------------------
$cfgUrl = rtrim($GATEWAY_API_BASE, '/') . '/config/dcv-gateway?sessionId=' . urlencode($targetSessionId);

$ch2 = curl_init($cfgUrl);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_TIMEOUT, 5);
curl_setopt($ch2, CURLOPT_HTTPHEADER, [
    'Accept: application/json',
    // 여기서도 동일하게 로그인 쿠키 전달 (필요 없으면 제거 가능)
    'Cookie: sessionId=' . $sessionIdCookie,
]);

$cfgBody = curl_exec($ch2);
$cfgErr  = curl_error($ch2);
$cfgCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

// 게이트웨이 응답 파싱
$cfgJson = null;
if ($cfgBody !== false) {
    $cfgJson = json_decode($cfgBody, true);
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
  <?php endif; ?>

  <hr />

  <h2>DCV 게이트웨이 설정 조회</h2>
  <p>요청 URL: <?php echo htmlspecialchars($cfgUrl, ENT_QUOTES, 'UTF-8'); ?></p>
  <p>HTTP STATUS: <?php echo htmlspecialchars((string)$cfgCode, ENT_QUOTES, 'UTF-8'); ?></p>

  <?php if ($cfgBody === false): ?>
    <pre><?php echo htmlspecialchars("cURL error: " . $cfgErr, ENT_QUOTES, 'UTF-8'); ?></pre>
  <?php else: ?>
    <pre><?php echo htmlspecialchars($cfgBody, ENT_QUOTES, 'UTF-8'); ?></pre>
  <?php endif; ?>

  <?php if ($cfgCode === 200 && is_array($cfgJson) && !empty($cfgJson['dcvGatewayUrl'])): ?>
    <p>
      <a href="<?php echo htmlspecialchars($cfgJson['dcvGatewayUrl'], ENT_QUOTES, 'UTF-8'); ?>"
         target="_blank" rel="noopener noreferrer">
        <button type="button">게임 접속하기 (DCV Gateway)</button>
      </a>
    </p>
    <p style="font-size: 0.9rem; color: #666;">
      ※ 게이트웨이 주소는 API에서 받아온 값이며, PHP 코드에는 IP/도메인을 직접 적지 않습니다.
    </p>
  <?php else: ?>
    <p style="color:red;">게이트웨이 설정 정보를 가져오지 못했습니다. (<?php echo htmlspecialchars((string)$cfgCode, ENT_QUOTES, 'UTF-8'); ?>)</p>
    <pre><?php echo htmlspecialchars($cfgBody ?? '', ENT_QUOTES, 'UTF-8'); ?></pre>
  <?php endif; ?>

  <p><a href="session_create.php">세션 생성 페이지로 돌아가기</a></p>
</body>
</html>
