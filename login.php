<?php
include 'config.php';

$loginSuccess = false;
$error = "";
$fromSignup = (isset($_GET['signup']) && $_GET['signup'] === 'success');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $pw    = $_POST['password'] ?? '';

    if ($email === '' || $pw === '') {
        $error = "이메일과 비밀번호를 입력하세요.";
    } else {
        $data = json_encode([
            "email"    => $email,
            "password" => $pw,
        ]);

        $ch = curl_init("$API_BASE/login");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true); // 헤더+바디 모두 받기
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json"
        ]);

        $response    = curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $status      = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $header = substr($response, 0, $header_size);
        $body   = substr($response, $header_size);

        if ($status === 200) {
            // ★ Set-Cookie 에서 sessionId 추출 (대소문자 무시)
            //   기존: 'set-cookie:' → 안 맞아서 쿠키를 못 받았음
            if (preg_match('/Set-Cookie:\s*sessionId=([^;]+)/i', $header, $m)) {
                $session = $m[1];

                // 브라우저에 sessionId 쿠키 저장
                // (도메인/https 환경이면 config.php 에서 조정)
                setcookie("sessionId", $session, time() + 7200, "/", "", false, true);

                $loginSuccess = true;
            } else {
                // 디버깅용으로 헤더 내용도 같이 찍어둠 (필요 없으면 나중에 제거 가능)
                $error = "로그인은 되었지만 sessionId 쿠키를 받지 못했습니다.";
                // $error .= " HEADER: " . htmlspecialchars($header);
            }
        } else {
            $error = "로그인 실패: " . htmlspecialchars($body);
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
        <p style="color: green;">로그인 성공! <a href="me.php">내 정보 페이지</a>로 이동하거나 <a href="session_create.php">게임 세션 페이지</a>로 이동하세요.</p>
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
