<?php
// tp-dev/session_create.php
// - 로그인된 사용자가 "게임 세션 생성"을 눌러 DCV 서버용 EC2 인스턴스를 생성
// - 성공 시, 생성된 gameSessionId를 보여주고 상태/접속 페이지로 이동하는 버튼 제공

session_start();
include 'config.php'; // 여기에서 $API_BASE 가 정의되어 있다고 가정

// 로그인 세션(쿠키) + PHP 세션(userId)
$loginSessionId = $_COOKIE['sessionId'] ?? '';
$currentUserId  = $_SESSION['userId'] ?? '';

$error        = "";
$result       = null;
$apiBody      = null;
$apiCode      = null;
$gameSessionId = "";

// 기본 Region
$defaultRegion = "ap-northeast-2";

// 1) 로그인 / userId 체크
if ($loginSessionId === '') {
    $error = "로그인 후 이용 가능합니다. 다시 로그인해 주세요.";
} elseif ($currentUserId === '' || $currentUserId === null) {
    // 네가 보고 있던 에러 문구 그대로 넣음
    $error = "userId 정보를 찾을 수 없습니다. 다시 로그인해 주세요.";
}

// 2) 폼 제출 처리 (에러 없을 때만)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $error === "") {
    $gameId = $_POST['game_id'] ?? '';
    $region = $_POST['region'] ?? $defaultRegion;

    if ($gameId === '') {
        $error = "game_id 를 입력하세요.";
    } else {
        $payload = json_encode([
            "gameId" => $gameId,
            "region" => $region,
            // 굳이 필요하진 않지만, 참고용으로 userId도 같이 보낼 수 있음
            "userId" => $currentUserId,
        ]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $API_BASE . "/game-sessions");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            // HTTP API v2 에서도 쿠키는 일반 헤더로 전달 가능
            "Cookie: sessionId=" . $loginSessionId,
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = "세션 생성 요청 실패: " . curl_error($ch);
            curl_close($ch);
        } else {
            $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $apiBody = $response;
            $apiCode = $statusCode;

            if ($statusCode === 200 || $statusCode === 201) {
                $decoded = json_decode($response, true);
                if (!is_array($decoded)) {
                    $error = "세션 생성 응답 형식이 올바르지 않습니다.";
                } else {
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
                }
            } else {
                $error = "세션 생성 실패 (HTTP {$statusCode}): "
                       . htmlspecialchars($response, ENT_QUOTES, 'UTF-8');
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

    <!-- 디버그용: 현재 로그인/유저 정보 표시 -->
    <p>
        현재 로그인 세션 ID (쿠키): 
        <code>
            <?php
            echo $loginSessionId !== ''
                ? htmlspecialchars($loginSessionId, ENT_QUOTES, 'UTF-8')
                : '(없음)';
            ?>
        </code>
        <br>
        현재 사용자 ID (PHP 세션):
        <code>
            <?php
            echo $currentUserId !== ''
                ? htmlspecialchars($currentUserId, ENT_QUOTES, 'UTF-8')
                : '(없음)';
            ?>
        </code>
    </p>

    <?php if ($error): ?>
        <p style="color:red;"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php if ($loginSessionId === '' || $currentUserId === ''): ?>
            <p><a href="login.php">로그인 페이지로 이동</a></p>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($result && $gameSessionId !== ""): ?>
        <hr>
        <h2>세션 생성 완료</h2>
        <p>생성된 Game Session ID: 
            <code><?php echo htmlspecialchars($gameSessionId, ENT_QUOTES, 'UTF-8'); ?></code>
        </p>

        <p>
            <a href="session_status.php?id=<?php echo urlencode($gameSessionId); ?>">
                <button type="button">세션 상태 / 접속 페이지로 이동</button>
            </a>
        </p>

        <hr>
        <h3>원시 응답(JSON)</h3>
        <pre><?php
            echo htmlspecialchars(
                json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                ENT_QUOTES,
                'UTF-8'
            );
        ?></pre>
    <?php endif; ?>

    <!-- userId/로그인 에러가 없을 때만 새 세션 생성 폼 노출 -->
    <?php if ($error === "" || ($error && $loginSessionId !== '' && $currentUserId !== '' && !$result)): ?>
        <hr>
        <h2>새 세션 생성</h2>
        <form method="post">
            <div>
                <label>Game ID:
                    <input type="text" name="game_id"
                           value="<?php echo isset($_POST['game_id'])
                                          ? htmlspecialchars($_POST['game_id'], ENT_QUOTES, 'UTF-8')
                                          : ''; ?>">
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
    <?php endif; ?>
</body>
</html>
