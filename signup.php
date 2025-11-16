<?php
include 'config.php';

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $pw    = $_POST['password'] ?? '';

    if ($email === '' || $pw === '') {
        $error = "이메일과 비밀번호를 입력하세요.";
    } else {
        $data = json_encode([
            "email"    => $email,
            "password" => $pw
        ]);

        $ch = curl_init("$API_BASE/signup");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json"
        ]);

        $response = curl_exec($ch);
        $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($status === 201 || $status === 200) {
            // 회원가입 성공 → 로그인 페이지로 리다이렉트
            header("Location: login.php?signup=success");
            exit;
        } else {
            $error = "회원가입 실패: " . htmlspecialchars($response);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
  <meta charset="UTF-8">
  <title>회원가입</title>
</head>
<body>
  <h2>회원가입</h2>

  <?php if (!empty($error)): ?>
    <p style="color:red;"><?php echo $error; ?></p>
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
    <button type="submit">Sign Up</button>
  </form>

  <p><a href="login.php">이미 계정이 있다면? 로그인</a></p>
</body>
</html>

