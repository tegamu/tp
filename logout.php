<?php
// 쿠키 삭제
setcookie("sessionId", "", time() - 3600, "/");
header("Location: login.php");
exit;

