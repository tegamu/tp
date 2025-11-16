<?php
// session_create.php
include 'config.php';

// 로그인 안 돼 있으면 막기
if (!isset($_COOKIE['sessionId'])) {
    echo "로그인 후 이용 가능합니다. <a href='login.php'>로그인하기</a>";
    exit;
}

$sessionIdCookie = $_COOKIE['sessionId'];

$error = "";
$result = null;

// 기본값: 테스트용 게임/리전
$defaultGameId = "test-game";
$defaultRegion = "ap-northeast-2";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gameId = $_POST['game_id'] ?? $defaultGameId;
    $region = $_POST['region'] ?? $defaultRegion;

    $payload = json_encode([
        "gameId" => $gameId,
        "region" => $region
    ]);

    // 예시 엔드포인트: 실제 API Gateway 경로에 맞춰 수정 (중요)
    $url = "$API_BASE/game-sessions";

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Cookie: sessionId=$sessionIdCookie"
    ]);

    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status === 200 || $status === 201) {
        // 예시 응답:
        // { "sessionId": "sess-123", "status": "CREATING" }
        $result = json_decode($response, true);
        if (!$result) {
            $error = "JSON 파싱 실패: $response";
        }
    } else {
        $error = "세션 생성 실패 (HTTP $status): $response";
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>게임 세션 생성</title>
</head>
<body>
  <h2>게임 세션 생성</h2>
  <p><a href="login.php">← 로그인 페이지</a> | <a href="me.php">내 정보</a></p>

  <?php if (!empty($error)): ?>
    <p style="color:red;"><?php echo htmlspecialchars($error); ?></p>
  <?php endif; ?>

  <?php if ($result): ?>
    <p style="color:green;">세션이 생성되었습니다.</p>
    <pre><?php echo htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>

    <?php if (!empty($result['sessionId'])): ?>
      <p>
        세션 상태 확인:
        <a href="session_status.php?id=<?php echo urlencode($result['sessionId']); ?>">
          session_status.php?id=<?php echo htmlspecialchars($result['sessionId']); ?>
        </a>
      </p>
    <?php endif; ?>
  <?php endif; ?>

  <h3>새 세션 만들기</h3>
  <form method="POST">
    <div>
      <label>Game ID:
        <input type="text" name="game_id" value="<?php echo htmlspecialchars($defaultGameId); ?>">
      </label>
    </div>
    <br>
    <div>
      <label>Region:
        <input type="text" name="region" value="<?php echo htmlspecialchars($defaultRegion); ?>">
      </label>
    </div>
    <br>
    <button type="submit">세션 생성</button>
  </form>
</body>
</html>

