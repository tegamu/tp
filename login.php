<?php
// tp-dev/login.php

session_start();
include 'config.php';

$loginSuccess = false;
$error = "";
$fromSignup = (isset($_GET['signup']) && $_GET['signup'] === 'success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pw    = $_POST['password'] ?? '';

    if ($email === '' || $pw === '') {
        $error = "이메일과 비밀번호를 입력하세요.";
    } else {
        $data = json_encode([
            "email"    => $email,
            "password" => $pw,
        ]);

        // 로그인 API 호출
        $ch = curl_init($API_BASE . "/login");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true); // 헤더 + 바디 같이 받기
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
        ]);

        $response = curl_exec($ch);

        if ($response === false) {
            $error = "로그인 요청 실패: " . curl_error($ch);
            curl_close($ch);
        } else {
            $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $status      = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $header = substr($response, 0, $header_size);
            $body   = substr($response, $header_size);

            if ($status === 200) {
                // ★ Set-Cookie 에서 sessionId 추출 (대소문자 무시)
                if (preg_match('/Set-Cookie:\s*sessionId=([^;]+)/i', $header, $m)) {
                    $sessionIdValue = $m[1];

                    // 브라우저에 sessionId 쿠키 저장
                    // 필요하면 config.php에서 도메인/secure 옵션 조정
                    setcookie("sessionId", $sessionIdValue, time() + 7200, "/", "", false, true);

                    // ★ 정석: /me API 한 번 더 호출해서 userId를 PHP 세션에 저장
                    $userId = null;

                    $meCh = curl_init($API_BASE . "/me");
                    curl_setopt($meCh, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($meCh, CURLOPT_HTTPHEADER, [
                        "Content-Type: application/json",
                        "Cookie: sessionId=" . $sessionIdValue,
                    ]);

                    $meResponse = curl_exec($meCh);
                    $meStatus   = curl_getinfo($meCh, CURLINFO_HTTP_CODE);
                    curl_close($meCh);

                    if ($meResponse !== false && $meStatus === 200) {
                        $meData = json_decode($meResponse, true);
                        if (is_array($meData)) {
                            if (isset($meData["userId"])) {
                                $userId = $meData["userId"];
                            } elseif (isset($meData["email"])) {
                                // me_lambda에서 user_id = email 이라서 fallback
                                $userId = $meData["email"];
                            }
                        }
                    }

                    if ($userId !== null && $userId !== '') {
                        $_SESSION["userId"] = $userId;
                    }

                    $loginSuccess = true;
                } else {
                    // 디버깅용: 쿠키 헤더가 안 보이면 여기서 막힘
                    $error = "로그인은 되었지만 sessionId 쿠키를 받지 못했습니다.";
                    // 필요하면 아래 주석을 풀어서 헤더 내용 확인
                    // $error .= " HEADER: " . htmlspecialchars($header, ENT_QUOTES, 'UTF-8');
                }
            } else {
                $error = "로그인 실패: " . htmlspecialchars($body, ENT_QUOTES, "UTF-8");
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>로그인</title>
</head>
<body>
    <h1>로그인</h1>

    <?php if ($fromSignup): ?>
        <p style="color: green;">회원가입 성공! 이제 로그인하세요.</p>
    <?php endif; ?>

    <?php if ($loginSuccess): ?>
        <p style="color: green;">
            로그인 성공!
            <br>
            <!-- 디버그용: 현재 세션 정보 표시 -->
            현재 sessionId (쿠키로 내려간 값): 
            <code>
                <?php
                echo htmlspecialchars(
                    $_COOKIE['sessionId'] ?? ($sessionIdValue ?? ''),
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
            </code>
            <br>
            현재 userId (PHP 세션): 
            <code>
                <?php
                echo htmlspecialchars(
                    $_SESSION['userId'] ?? '',
                    ENT_QUOTES,
                    'UTF-8'
                );
                ?>
            </code>
            <br><br>
            <a href="me.php">내 정보(me)</a>로 이동하거나
            <a href="session_create.php">게임 세션 페이지</a>로 이동하세요.
        </p>
    <?php else: ?>
        <?php if ($error): ?>
            <p style="color: red;"><?php echo $error; ?></p>
        <?php endif; ?>

        <form method="POST">
            <div>
                <label>Email:
                    <input type="email" name="email" required>
                </label>
            </div>
            <br>
            <div>
                <label>Password:
                    <input type="password" name="password" required>
                </label>
            </div>
            <br>
            <button type="submit">Login</button>
        </form>

        <p><a href="signup.php">계정이 없다면? 회원가입</a></p>
    <?php endif; ?>
</body>
</html>
