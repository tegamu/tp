<?php
// session_create.php
include 'config.php';

//    - DCV 게이트웨이 인스턴스 퍼블릭 IP 또는 도메인
$DCV_GATEWAY_HOST = "nas.tegamu.shop"; 

// 로그인 여부 체크 (sessionId 쿠키 없으면 막기)
if (!isset($_COOKIE['sessionId'])) {
    echo "로그인 후 이용 가능합니다. <a href='login.php'>로그인하기</a>";
    exit;
}

$sessionIdCookie = $_COOKIE['sessionId'];

$error  = "";
$result = null;

// 기본값: 테스트용
$defaultGameId = "test-game";
$defaultRegion = "ap-northeast-2";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gameId = $_POST['gameId'] ?? '';
    $region = $_POST['region'] ?? '';

    if ($gameId === '' || $region === '') {
        $error = "gameId / region 을 모두 입력하세요.";
    } else {
        $payload = json_encode([
            "gameId" => $gameId,
            "region" => $region,
        ], JSON_UNESCAPED_UNICODE);

        // Lambda(API Gateway) 호출
        $ch = curl_init($API_BASE . "/game-sessions");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true); // 헤더 + 바디 같이 받기
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            // 로그인 때 받은 sessionId 쿠키를 그대로 전달
            "Cookie: sessionId=" . $sessionIdCookie,
        ]);

        $response    = curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status      = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $header = substr($response, 0, $header_size);
        $body   = substr($response, $header_size);

        if ($status === 200) {
            $result = json_decode($body, true);
            if ($result === null) {
                $error = "세션 생성은 되었지만 JSON 파싱에 실패했습니다: " . htmlspecialchars($body);
            }
        } else {
            $error = "세션 생성 실패 (HTTP {$status}): " . htmlspecialchars($body);
        }
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

  <p>
    &larr; <a href="login.php">로그인 페이지</a> |
    <a href="me.php">내 정보</a>
  </p>

  <?php if (!empty($error)): ?>
    <p style="color:red;"><?php echo $error; ?></p>
  <?php endif; ?>

  <?php if ($result): ?>
    <h3>세션 생성 결과</h3>
    <pre><?php echo htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>

    <?php
      $gameSessionId = $result['gameSessionId'] ?? null;

      $connectUrl = "https://" . $DCV_GATEWAY_HOST . ":80";
    ?>

    <p style="margin-top: 12px;">
      <a href="<?php echo htmlspecialchars($connectUrl); ?>" target="_blank">
        <button type="button">DCV 게이트웨이 접속 (가라버튼)</button>
      </a>
    </p>

    <hr>
  <?php endif; ?>

  <form method="POST">
    <div>
      <label>Game ID:
        <input type="text" name="gameId"
               value="<?php echo isset($_POST['gameId'])
                              ? htmlspecialchars($_POST['gameId'])
                              : htmlspecialchars($defaultGameId); ?>">
      </label>
    </div>
    <br>
    <div>
      <label>Region:
        <input type="text" name="region"
               value="<?php echo isset($_POST['region'])
                              ? htmlspecialchars($_POST['region'])
                              : htmlspecialchars($defaultRegion); ?>">
      </label>
    </div>
    <br>
    <button type="submit">세션 생성</button>
  </form>
</body>
</html>
