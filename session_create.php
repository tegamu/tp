<?php
// ==========================================
// 환경 변수에서 설정 가져오기
// ==========================================
$API_BASE_URL      = getenv('API_BASE_URL')      ?: 'https://example-api.execute-api.ap-northeast-2.amazonaws.com/prod';
$DCV_GATEWAY_HOST  = getenv('DCV_GATEWAY_HOST')  ?: '';      // 예: 3.39.x.x (게이트웨이 IP)
$DCV_GATEWAY_PORT  = getenv('DCV_GATEWAY_PORT')  ?: '8443';  // DCV Gateway listen 포트
$DCV_GATEWAY_SCHEME= getenv('DCV_GATEWAY_SCHEME')?: 'https'; // 보통 https

$gatewayConfigError = '';
if ($DCV_GATEWAY_HOST === '') {
    $gatewayConfigError = 'DCV_GATEWAY_HOST 환경변수가 설정되어 있지 않습니다.';
}

// ==========================================
// 공통 API 호출 함수
// ==========================================
function call_api($method, $url, $body = null, $headers = [])
{
    $ch = curl_init();

    $opts = [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_TIMEOUT        => 15,
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

// ==========================================
// 세션 생성 처리 (POST)
// ==========================================
$createErrorMessage = null;
$createResponse     = null;
$gameSessionId      = null;
$gameSessionStatus  = null;
$instanceId         = null;
$dcvEndpoint        = null;
$connectUrl         = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 필요하면 game_id, region 등 폼 파라미터 추가
    $payload = [
        // 'gameId' => $_POST['game_id'] ?? null,
        // 'region' => $_POST['region'] ?? 'ap-northeast-2',
    ];

    $url = rtrim($API_BASE_URL, '/') . '/game-session/create';

    // 로그인 세션 쿠키 같은 거 있으면 여기에 붙이면 됨
    $headers = [];
    if (!empty($_COOKIE['sessionId'])) {
        $headers[] = 'Cookie: sessionId=' . $_COOKIE['sessionId'];
    }

    $createResponse = call_api('POST', $url, $payload, $headers);

    if ($createResponse['error']) {
        $createErrorMessage = 'cURL error: ' . $createResponse['error'];
    } elseif ($createResponse['status_code'] < 200 || $createResponse['status_code'] >= 300) {
        $createErrorMessage = 'HTTP ' . $createResponse['status_code'] . ' 에러: ' . $createResponse['body_raw'];
    } else {
        $data = $createResponse['body_json'] ?? null;

        if (!is_array($data)) {
            $createErrorMessage = '응답을 JSON으로 파싱할 수 없습니다.';
        } else {
            // Lambda 응답 키에 맞춰 읽기
            $gameSessionId     = $data['sessionId']     ?? $data['gameSessionId'] ?? null;
            $gameSessionStatus = $data['status']        ?? null;
            $instanceId        = $data['instanceId']    ?? null;
            $dcvEndpoint       = $data['dcvEndpoint']   ?? null;

            if (!$gameSessionId) {
                $createErrorMessage = '세션 생성은 성공했지만 sessionId를 찾지 못했습니다.';
            } else {
                // 나중에 External Auth 붙이면 authToken을 진짜로 생성해서 넘길 것
                $authToken = 'DUMMY-TOKEN';

                // ✔ DCV Gateway URL
                // 예: https://<GATEWAY_IP>:8443/?authToken=...#<sessionId>
                $connectUrl = sprintf(
                    '%s://%s:%s/?authToken=%s#%s',
                    $DCV_GATEWAY_SCHEME,
                    $DCV_GATEWAY_HOST,
                    $DCV_GATEWAY_PORT,
                    urlencode($authToken),
                    $gameSessionId
                );
            }
        }
    }
}
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
            background: #0b0c10;
            color: #e5e5e5;
        }
        .container { max-width: 800px; margin: 0 auto; }
        h1 { margin-bottom: 0.5rem; }
        .card {
            border: 1px solid #333;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 16px;
            background: #15171c;
        }
        .error { color: #ff5555; white-space: pre-wrap; }
        .success { color: #50fa7b; white-space: pre-wrap; }
        button {
            padding: 8px 14px;
            border-radius: 4px;
            border: 1px solid #666;
            background: #222;
            color: #fff;
            cursor: pointer;
        }
        button:hover { background: #333; }
        .session-id { font-family: monospace; }
        a { text-decoration: none; }
        .small-text { font-size: 0.85rem; color: #bbb; }
    </style>
</head>
<body>
<div class="container">
    <h1>게임 세션 생성</h1>
    <p class="small-text">
        세션을 생성하면 DCV Gateway / Resolver Lambda / RDS / DCV Server를 통해
        사용자의 DCV GUI 세션으로 연결됩니다.
    </p>

    <?php if ($gatewayConfigError): ?>
        <div class="card error">
            <strong>게이트웨이 설정 오류</strong><br>
            <?php echo htmlspecialchars($gatewayConfigError, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <form method="post">
            <!-- 필요하면 게임 선택 / 옵션 필드 추가 -->
            <button type="submit">세션 생성</button>
        </form>
    </div>

    <?php if ($createErrorMessage): ?>
        <div class="card error">
            <strong>세션 생성 실패</strong><br>
            <?php echo htmlspecialchars($createErrorMessage, ENT_QUOTES, 'UTF-8'); ?>
        </div>
    <?php elseif ($gameSessionId): ?>
        <div class="card">
            <div class="success">
                <strong>세션 생성 성공</strong><br>
                세션 ID:
                <span class="session-id">
                    <?php echo htmlspecialchars($gameSessionId, ENT_QUOTES, 'UTF-8'); ?>
                </span><br>
                <?php if ($gameSessionStatus): ?>
                    상태:
                    <?php echo htmlspecialchars($gameSessionStatus, ENT_QUOTES, 'UTF-8'); ?><br>
                <?php endif; ?>
                <?php if ($instanceId): ?>
                    인스턴스 ID:
                    <span class="session-id">
                        <?php echo htmlspecialchars($instanceId, ENT_QUOTES, 'UTF-8'); ?>
                    </span><br>
                <?php endif; ?>
            </div>

            <hr style="margin: 12px 0;">

            <?php if ($connectUrl): ?>
                <p style="margin-top: 12px;">
                    <a href="<?php echo htmlspecialchars($connectUrl, ENT_QUOTES, 'UTF-8'); ?>"
                       target="_blank" rel="noopener noreferrer">
                        <!-- ✅ 가라버튼 삭제, 실제 접속 버튼 -->
                        <button type="button">세션 접속</button>
                    </a>
                </p>
                <p class="small-text">
                    DCV Gateway로 이동한 뒤, Gateway가 Resolver Lambda / RDS를 통해<br>
                    이 세션에 해당하는 DCV Server로 연결합니다.
                </p>
            <?php else: ?>
                <p class="error">
                    세션 생성은 되었지만 게이트웨이 접속 URL을 생성하지 못했습니다.
                </p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
