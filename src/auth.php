<?php
require_once __DIR__ . '/config/bootstrap.php';
require_once __DIR__ . '/config/configdb.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'logout') {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        header('Location: index.php', true, 303);
        exit;
    }
    if (!in_array($action, ['register', 'login'], true)) fail(422, '잘못된 요청입니다.');
    $username = textField($_POST, 'username', 50);
    $password = $_POST['password'] ?? null;
    if (!is_string($password) || strlen($password) < 8 || strlen($password) > 72 || str_contains($password, "\0")) {
        fail(422, '비밀번호는 8~72바이트로 입력하세요.');
    }
    if ($action === 'register') {
        try {
            $stmt = $pdo->prepare('INSERT INTO board_users (username, password_hash) VALUES (?, ?)');
            $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT)]);
        } catch (PDOException $error) {
            if (($error->errorInfo[1] ?? null) === 1062) fail(409, '이미 사용 중인 사용자 이름입니다.');
            throw $error;
        }
        $userId = (int)$pdo->lastInsertId();
    } else {
        $stmt = $pdo->prepare('SELECT id, password_hash FROM board_users WHERE username = ?');
        $stmt->execute([$username]);
        $account = $stmt->fetch();
        $hash = $account['password_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi';
        if (!password_verify($password, $hash) || !$account) fail(401, '사용자 이름 또는 비밀번호가 올바르지 않습니다.');
        $userId = (int)$account['id'];
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = $userId;
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    header('Location: index.php', true, 303);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'GET') fail(405, '지원하지 않는 요청입니다.');
$register = ($_GET['mode'] ?? '') === 'register';
?>
<!doctype html>
<html lang="ko">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $register ? '회원가입' : '로그인' ?></title>
<style>body{font-family:sans-serif;background:#f8fafc;color:#334155;padding:50px 20px}.card{max-width:380px;margin:auto;background:white;padding:30px;border:1px solid #e2e8f0;border-radius:16px}label{display:block;margin-top:20px}input,button{box-sizing:border-box;width:100%;padding:12px;margin-top:8px;border:1px solid #cbd5e1;border-radius:8px}button{background:#4f46e5;color:white;cursor:pointer}a{color:#4f46e5}p{line-height:1.7}</style></head>
<body><main class="card"><h1><?= $register ? '회원가입' : '로그인' ?></h1>
<form method="POST" action="auth.php">
<input type="hidden" name="csrf_token" value="<?= e(csrfToken()) ?>">
<input type="hidden" name="action" value="<?= $register ? 'register' : 'login' ?>">
<label for="username">사용자 이름</label><input id="username" name="username" maxlength="50" required autocomplete="username">
<label for="password">비밀번호</label><input id="password" type="password" name="password" minlength="8" maxlength="72" required autocomplete="<?= $register ? 'new-password' : 'current-password' ?>">
<button type="submit"><?= $register ? '가입하기' : '로그인' ?></button></form>
<p><a href="auth.php<?= $register ? '' : '?mode=register' ?>"><?= $register ? '로그인' : '회원가입' ?></a> · <a href="index.php">게시판</a></p></main></body></html>
