<?php
// =============================================
// 설정: 환경변수에서 값 읽기
//  - API_BASE_URL        : API Gateway Base URL (예: https://xxxxx.execute-api.ap-northeast-2.amazonaws.com/prod)
//  - DCV_GATEWAY_HOST    : DCV 게이트웨이 IP (예: 3.39.112.145)
//  - DCV_GATEWAY_PORT    : DCV 게이트웨이 포트 (없으면 기본 8443)
//  - DCV_GATEWAY_SCHEME  : https/http (없으면 https)
// =============================================

$API_BASE = getenv('API_BASE_URL') ?: 'https://example-api.execute-api.ap-northeast-2.amazonaws.com/prod';

$DCV_GATEWAY_HOST   = getenv('DCV_GATEWAY_HOST') ?: '';
$DCV_GATEWAY_PORT   = getenv('DCV_GATEWAY_PORT') ?: '8443';
$DCV_GATEWAY_SCHEME = getenv('DCV_GATEWAY_SCHEME') ?: 'https';

// 게이트웨이 HOST 환경변수 안 들어가 있으면 에러로 알려주기
$gatewayConfigError = '';
if ($DCV_GATEWAY_HOST === '') {
    $gatewayConfigError = 'DCV 게이트웨이 호스트(DCV_GATEWAY_HOST)가 설정되어 있지 않습니다. '
        . '웹서버 환경변수 또는 config에서 DCV_GATEWAY_HOST를 설정해주세요.';
}


// =============================================
// 공통: API 호출 함수 (cURL)
// =============================================

function call_api($method, $url, $body = null, $headers = [])
{
    $ch = curl_init();

    $opts = [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_TIMEOUT        => 10,
    ];

    if ($body !== null) {
        $json = json_encode($body);
        $opts[CURLOPT_POSTFIELDS] = $json;
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Content-Length: ' . strlen($json);
    }

    if (!empty($headers)) {
        $opts[CURLOPT_HTTPHEADER] = $headers;
    }

    curl_setopt_array($ch, $opts);

    $responseBody = curl_exec($ch);
    $httpCode     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err          = curl_error($ch);

    curl_close($ch);

    return [
        'status_code' => $httpCode,
        'error'       => $err ?: null,
        'body_raw'    => $responseBody,
        'body_json'   => json_decode($responseBody, true),
    ];
}


// =============================================
// 세션 생성 처리 (POST)
// =============================================

$createRequestBody   = null;
$createResponse      = null;
$createErrorMessage  = null;
$gameSessionId       = null;
$gameSessionStatus   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 나중에 필요하면 여기서 user_id, game_id 등 추가할 수 있음
    $createRequestBody = [
        // 예시:
        // 'userId' => $_SESSION['user_email'] ?? null,
        // 'gameId' => $_POST['game_id'] ?? null,
    ];

    // API Gateway 엔드포인트 (네가 만든 /game-session/create 경로에 맞춰 수정)
    $url = rtrim($API_BASE, '/') . '/game-session/create';

    $createResponse = call_api('POST', $url, $createRequestBody);

    if ($createResponse['error']) {
        $createErrorMessage = 'cURL error: ' . $createResponse['error'];
    } elseif ($createResponse['status_code'] < 200 || $createResponse['status_code'] >= 300) {
        $createErrorMessage = 'HTTP ' . $createResponse['status_code'] . ' 에러: ' . $createResponse['body_raw'];
    } else {
        $data = $createResponse['body_json'] ?? null;

        if (is_array($data)) {
            // Lambda 응답 형식에 맞게 sessionId / status 읽기
            // 예: { "sessionId": "sess-1234", "status": "READY" }
            $gameSessionId     = $data['sessionId'] ?? $data['gameSessionId'] ?? null;
            $gameSessionStatus = $data['status']    ?? null;
        }

        if (!$gameSessionId) {
            $createErrorMessage = '세션 생성은 성공했지만 sessionId를 응답에서 찾을 수 없습니다.';
        }
    }
}


// =============================================
// HTML 출력
// =============================================
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>게임 세션 생성 / DCV 접속</title>
    <style>
        body {
            font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            margin: 20px;
            background-color: #111;
            color: #eee;
        }
        .container {
            max-width: 760px;
            margin: 0 auto;
        }
        h1 {
            margin-bottom: 0.5rem;
        }
        .card {
            border: 1px solid #444;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            background-color: #181818;
        }
        .error {
            color: #ff5555;
            white-space: pre-wrap;
        }
        .success {
            color: #50fa7b;
            white-space: pre-wrap;
        }
        button {
            padding: 8px 14px;
            border-radius: 4px;
            border: 1px solid #888;
            background: #222;
            color: #fff;
            cursor: pointer;
        }
        button:hover {
            background: #333;
        }
        .field {
            margin-bottom: 8px;
        }
        .field label {
            display: inline-block;
            width: 120px;
            font-weight: 600;
        }
        .session-id {
            font-family: monospace;
        }
        a {
            text-decoration: none;
        }
        .small-text {
            font-size: 0.85rem;
            color: #bbb;
        }
    </style>
</head>
<body>
<div class="container">

    <h1>게임 세션 생성</h1>
    <p class="small-text">
        세션을 생성하면 DCV 게이트웨이/세션 브로커가 RDS 정보를 사용해<br>
        사용자가 생성한 DCV 서버로 연결을 라우팅합니다.
    </p>

    <!-- 게이트웨이 환경설정 오류 표시 -->
    <?php if ($gatewayConfigError): ?>
        <div class="card error">
            <strong>게이트웨이 설정 오류</strong><br>
            <?php echo htmlspecialchars($gatewayConfigError, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <!-- 세션 생성 폼 -->
    <div class="card">
        <form method="post">
            <!-- 나중에 필요하면 여기 게임 선택/옵션 추가 -->
            <!--
            <div class="field">
                <label for="game_id">게임 ID</label>
                <input type="text" id="game_id" name="game_id">
            </div>
            -->
            <button type="submit">세션 생성</button>
        </form>
    </div>

    <!-- 세션 생성 결과 -->
    <?php if ($createErrorMessage): ?>
        <div class="card error">
            <strong>세션 생성 실패</strong><br>
            <?php echo htmlspecialchars($createErrorMessage, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php elseif ($gameSessionId): ?>
        <div class="card">
            <div class="success">
                <strong>세션 생성 성공</strong><br>
                세션 ID: <span class="session-id">
                    <?php echo htmlspecialchars($gameSessionId, ENT_QUOTES, 'UTF-8'); ?>
                </span><br>
                <?php if ($gameSessionStatus): ?>
                    상태: <?php echo htmlspecialchars($gameSessionStatus, ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
            </div>

            <hr style="margin: 12px 0;">

            <?php
                // --- 여기서 DCV 게이트웨이 접속 URL 생성 ---
                // 게이트웨이는 도메인 없이 IP만 있다고 했으므로 HOST = IP
                // 나중에 Terraform에서 DCV_GATEWAY_HOST 환경변수에 "게이트웨이 인스턴스 IP"를 넣어주면
                // PHP 수정 없이 그대로 사용 가능.

                if ($DCV_GATEWAY_HOST !== '') {
                    // 예: https://3.39.112.145:8443
                    $connectUrl = sprintf(
                        '%s://%s:%s',
                        $DCV_GATEWAY_SCHEME,
                        $DCV_GATEWAY_HOST,
                        $DCV_GATEWAY_PORT
                    );
                    // 나중에 세션 ID를 쿼리로 넘기고 싶으면:
                    // $connectUrl .= '?sessionId=' . urlencode($gameSessionId);
                } else {
                    $connectUrl = '';
                }
            ?>

            <?php if ($connectUrl): ?>
                <p style="margin-top: 12px;">
                    <a href="<?php echo htmlspecialchars($connectUrl, ENT_QUOTES, 'UTF-8'); ?>"
                       target="_blank" rel="noopener noreferrer">
                        <!-- ✅ 여기서 "가라버튼" 문구 제거 -->
                        <button type="button">세션 접속</button>
                    </a>
                </p>
                <p class="small-text">
                    DCV 게이트웨이로 이동 후, 게이트웨이/세션 브로커가 RDS 정보를 기반으로<br>
                    이 세션에 해당하는 DCV 서버 GUI로 연결합니다.
                </p>
            <?php else: ?>
                <p class="error">
                    DCV 게이트웨이 호스트가 설정되지 않아 세션 접속 버튼을 표시할 수 없습니다.
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>
</body>
</html>
