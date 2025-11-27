<?php
// session_create.php
// - 로그인된 사용자가 "게임 세션 생성"을 눌러 DCV 서버용 EC2 인스턴스를 생성
// - 성공 시, 생성된 gameSessionId를 보여주고 상태/접속 페이지로 이동하는 버튼 제공

session_start();
include 'config.php'; // 여기에서 $API_BASE 가 정의되어 있다고 가정

// 로그인 체크 (sessionId 쿠키 기반)
if (!isset($_COOKIE['sessionId'])) {
    echo "로그인 후 이용 가능합니다. <a href='login.php'>로그인하기</a>";
    exit;
}

$sessionIdCookie = $_COOKIE['sessionId'];

// PHP 세션에 user_id 가 들어있다고 가정 (login.php 등에서 설정)
$userId = $_SESSION['user_id'] ?? null;

$error  = "";
$result = null;

// 기본 region
$defaultRegion = 'ap-northeast-2';

// 폼 전송 처리
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $gameId = trim($_POST['game_id'] ?? '');
    $region = trim($_POST['region'] ?? $defaultRegion);

    if ($gameId === '') {
        $error = "Game ID를 입력하세요.";
    } elseif ($userId === null) {
        // 세션에 userId 자체가 없으면 여기서 막음
        $error = "userId 정보를 찾을 수 없습니다. 다시 로그인해 주세요.";
    } else {
        // API 요청
        $url  = rtrim($API_BASE, '/') . '/game-sessions';
        $body = [
            'gameId' => $gameId,
            'region' => $region,
        ];

        $headers = [
            'Content-Type: application/json',
            // 여기서 userId를 Lambda로 넘김
            'X-User-Id: ' . $userId,
        ];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_POSTFIELDS     => json_encode($body),
        ]);

        $responseBody = curl_exec($ch);
        $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr      = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            $error = "세션 생성 요청 중 오류: " . htmlspecialchars($curlErr, ENT_QUOTES, 'UTF-8');
        } else {
            $decoded = json_decode($responseBody, true);
            if ($httpCode >= 200 && $httpCode < 300 && is_array($decoded)) {
                // 성공
                $result = [
                    'http_code' => $httpCode,
                    'raw_body'  => $responseBody,
                    'json'      => $decoded,
                ];
            } else {
                // Lambda에서 에러 응답
                $error = "세션 생성 실패 (HTTP {$httpCode})<br>\n"
                       . "<pre>" . htmlspecialchars($responseBody, ENT_QUOTES, 'UTF-8') . "</pre>";
            }
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
    <h1>게임 세션 생성</h1>

    <p>현재 로그인 세션 ID (쿠키): <?php echo htmlspecialchars($sessionIdCookie, ENT_QUOTES, 'UTF-8'); ?></p>
    <p>현재 사용자 ID (PHP 세션): <?php echo htmlspecialchars((string)$userId, ENT_QUOTES, 'UTF-8'); ?></p>

    <?php if ($error): ?>
        <div style="color:red;">
            <strong>에러:</strong><br>
            <?php echo $error; ?>
        </div>
    <?php endif; ?>

    <?php if ($result): ?>
        <?php
        $sessionId = $result['json']['sessionId'] ?? null;
        $instanceId = $result['json']['instanceId'] ?? null;
        $status = $result['json']['status'] ?? null;
        ?>
        <hr>
        <h2>세션 생성 결과</h2>
        <p>HTTP STATUS: <?php echo htmlspecialchars((string)$result['http_code'], ENT_QUOTES, 'UTF-8'); ?></p>
        <pre><?php echo htmlspecialchars($result['raw_body'], ENT_QUOTES, 'UTF-8'); ?></pre>

        <?php if ($sessionId): ?>
            <p>
                생성된 Session ID:
                <strong><?php echo htmlspecialchars($sessionId, ENT_QUOTES, 'UTF-8'); ?></strong>
            </p>
            <p>
                <a href="session_status.php?id=<?php echo urlencode($sessionId); ?>">
                    <button type="button">세션 상태 확인 / 접속 페이지로 이동</button>
                </a>
            </p>
        <?php endif; ?>
    <?php endif; ?>

    <hr>
    <h2>새 세션 생성</h2>
    <form method="post">
        <div>
            <label>Game ID:
                <input type="text" name="game_id"
                       value="<?php echo isset($_POST['game_id']) ? htmlspecialchars($_POST['game_id'], ENT_QUOTES, 'UTF-8') : ''; ?>">
            </label>
        </div>
        <br>
        <div>
            <label>Region:
                <input type="text" name="region"
                       value="<?php echo isset($_POST['region'])
                                      ? htmlspecialchars($_POST['region'], ENT_QUOTES, 'UTF-8')
                                      : htmlspecialchars($defaultRegion, ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <br>
        <button type="submit">세션 생성</button>
    </form>
</body>
</html>
