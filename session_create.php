<?php
// session_create.php
// - 로그인된 사용자가 "게임 세션 생성"을 눌러 DCV 서버용 EC2 인스턴스를 생성
// - 성공 시, 생성된 gameSessionId를 보여주고 상태/접속 페이지로 이동하는 버튼 제공

include 'config.php'; // 여기에서 $API_BASE 가 정의되어 있다고 가정

if (!isset($_COOKIE['sessionId'])) {
    echo "로그인 후 이용 가능합니다. <a href='login.php'>로그인하기</a>";
    exit;
}

$sessionIdCookie = $_COOKIE['sessionId'];

$error    = "";
$result   = null;
$apiBody  = null;
$apiCode  = null;
$gameSessionId = "";

// 기본 Region (필요에 따라 수정)
$defaultRegion = "ap-northeast-2";

// 폼 제출 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gameId = $_POST['game_id'] ?? '';
    $region = $_POST['region'] ?? $defaultRegion;

    if ($gameId === '') {
        $error = "game_id 를 입력하세요.";
    } else {
        $payload = json_encode([
            "gameId" => $gameId,
            "region" => $region,
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $API_BASE . "/game-sessions");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            // HTTP API v2 에서도 쿠키는 일반 헤더로 전달 가능
            "Cookie: sessionId=" . $sessionIdCookie,
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);

        $resp = curl_exec($ch);
        if ($resp === false) {
            $error = "세션 생성 API 호출 실패: " . curl_error($ch);
        } else {
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $header     = substr($resp, 0, $headerSize);
            $body       = substr($resp, $headerSize);
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            $apiBody = $body;
            $apiCode = $statusCode;

            $decoded = json_decode($body, true);
            if ($statusCode >= 200 && $statusCode < 300 && is_array($decoded)) {
                // 람다 응답에 따라 키 이름이 sessionId 또는 gameSessionId 일 수 있으므로 모두 시도
                if (isset($decoded['gameSessionId'])) {
                    $gameSessionId = $decoded['gameSessionId'];
                } elseif (isset($decoded['sessionId'])) {
                    $gameSessionId = $decoded['sessionId'];
                } elseif (isset($decoded['session_id'])) {
                    $gameSessionId = $decoded['session_id'];
                }

                $result = $decoded;
                if ($gameSessionId === "") {
                    $error = "세션은 생성되었지만 sessionId를 응답에서 찾지 못했습니다.";
                }
            } else {
                $error = "세션 생성 실패 (HTTP {$statusCode}): " . htmlspecialchars($body);
            }
        }

        curl_close($ch);
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
    <h1>게임 세션 생성</h1>

    <p><a href="me.php">내 정보</a> | <a href="logout.php">로그아웃</a></p>

    <?php if ($error): ?>
        <p style="color: red;"><?php echo $error; ?></p>
    <?php endif; ?>

    <?php if ($result && $gameSessionId): ?>
        <h2>세션 생성 완료</h2>
        <p>생성된 세션 ID: <code><?php echo htmlspecialchars($gameSessionId); ?></code></p>

        <p>
            <a href="session_status.php?id=<?php echo urlencode($gameSessionId); ?>">
                <button type="button">세션 상태 / 접속 페이지로 이동</button>
            </a>
        </p>

        <hr>
        <h3>원시 응답(JSON)</h3>
        <pre><?php echo htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
    <?php endif; ?>

    <hr>
    <h2>새 세션 생성</h2>
    <form method="post">
        <div>
            <label>Game ID:
                <input type="text" name="game_id"
                       value="<?php echo isset($_POST['game_id']) ? htmlspecialchars($_POST['game_id']) : ''; ?>">
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
