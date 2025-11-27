<?php
// 세션 상태 조회 및 DCV 접속 URL 생성 페이지

// API Gateway Base URL (예: https://xxxxx.execute-api.ap-northeast-2.amazonaws.com)
$API_BASE = getenv('API_BASE_URL') ?: 'https://example-api.execute-api.ap-northeast-2.amazonaws.com/prod';

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

$url = rtrim($API_BASE, '/') . '/game-sessions/' . urlencode($targetSessionId);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Cookie: sessionId=' . $sessionIdCookie,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr  = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    echo "API 호출 중 오류 발생: " . htmlspecialchars($curlErr, ENT_QUOTES, 'UTF-8');
    exit;
}

if ($httpCode < 200 || $httpCode >= 300) {
    echo "API 응답 코드: " . intval($httpCode) . "<br>";
    echo "<pre>" . htmlspecialchars($response, ENT_QUOTES, 'UTF-8') . "</pre>";
    exit;
}

$data = json_decode($response, true);
if (!is_array($data)) {
    echo "응답 JSON 파싱 실패<br>";
    echo "<pre>" . htmlspecialchars($response, ENT_QUOTES, 'UTF-8') . "</pre>";
    exit;
}

// status / dcvEndpoint / port 읽기
$status       = $data['status']      ?? ($data['Status'] ?? null);
$dcvEndpoint  = $data['dcvEndpoint'] ?? ($data['dcv_endpoint'] ?? null);
$port         = $data['port']        ?? 8443;

$dcvUrl = null;
if ($status === 'READY' && !empty($dcvEndpoint)) {
    // DCV 서버 직접 붙는 URL (디버그용)
    $dcvUrl = "https://{$dcvEndpoint}:{$port}";
}

?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8" />
  <title>게임 세션 상태</title>
  <style>
    body {
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      margin: 20px;
      background: #0b0c10;
      color: #e5e5e5;
    }
    .card {
      border: 1px solid #333;
      border-radius: 8px;
      padding: 16px;
      margin-bottom: 16px;
      background: #15171c;
    }
    .label {
      color: #bbb;
      min-width: 120px;
      display: inline-block;
    }
    a { color: #7aa2f7; }
    button {
      padding: 8px 14px;
      border-radius: 4px;
      border: 1px solid #666;
      background: #222;
      color: #fff;
      cursor: pointer;
    }
    button:hover { background: #333; }
    code { font-family: monospace; }
  </style>
</head>
<body>
  <div class="card">
    <h2>세션 상태</h2>
    <p><span class="label">세션 ID:</span> <code><?php echo htmlspecialchars($targetSessionId, ENT_QUOTES, 'UTF-8'); ?></code></p>
    <p><span class="label">status:</span> <?php echo htmlspecialchars((string)$status, ENT_QUOTES, 'UTF-8'); ?></p>
    <p><span class="label">dcvEndpoint:</span> <code><?php echo htmlspecialchars((string)$dcvEndpoint, ENT_QUOTES, 'UTF-8'); ?></code></p>
    <p><span class="label">port:</span> <code><?php echo htmlspecialchars((string)$port, ENT_QUOTES, 'UTF-8'); ?></code></p>
  </div>

  <?php if ($dcvUrl): ?>
    <div class="card">
      <h2>DCV 접속</h2>
      <p>아래 버튼을 누르면 DCV 서버로 직접 접속합니다.</p>
      <p><code><?php echo htmlspecialchars($dcvUrl, ENT_QUOTES, 'UTF-8'); ?></code></p>
      <p style="margin-top: 16px;">
        <a href="<?php echo htmlspecialchars($dcvUrl, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener noreferrer">
          <button type="button">게임 접속하기 (DCV)</button>
        </a>
      </p>
      <p style="font-size: 0.85rem; color: #bbb;">
        나중에 Gateway/토큰 구조가 완전히 잡히면 이 URL 대신 Gateway 기준 URL로 바꿔 쓰면 됨.
      </p>
    </div>
  <?php else: ?>
    <div class="card">
      <p>status가 <code>READY</code>이고 <code>dcvEndpoint</code>가 설정되어야 접속 버튼이 표시됩니다.</p>
    </div>
  <?php endif; ?>
</body>
</html>
