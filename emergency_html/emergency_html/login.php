<?php
declare(strict_types=1);

require_once '/home/naojima/www/emergency/config.php';

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/emergency/',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Strict',
]);

session_start();

if (!empty($_SESSION['authenticated'])) {
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['login_csrf'])) {
    $_SESSION['login_csrf'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    $password = $_POST['password'] ?? '';

    if (!is_string($csrf) ||
        !hash_equals($_SESSION['login_csrf'], $csrf)) {
        $error = '不正なリクエストです。';
    } elseif (!is_string($password)) {
        $error = 'パスワードを確認してください。';
    } elseif (ADMIN_PASSWORD_HASH === '') {
        $error = '管理パスワードがまだ登録されていません。';
    } elseif (password_verify($password, ADMIN_PASSWORD_HASH)) {
        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        $_SESSION['login_time'] = time();
        unset($_SESSION['login_csrf']);

        header('Location: index.php');
        exit;
    } else {
        $error = 'パスワードが違います。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>緊急呼び出しシステム - ログイン</title>
<style>
body {
    font-family: sans-serif;
    background: #f3f4f6;
    display: grid;
    place-items: center;
    min-height: 100vh;
    margin: 0;
}
.login {
    background: white;
    width: min(340px, calc(100% - 48px));
    padding: 28px;
    border-radius: 12px;
    box-shadow: 0 2px 12px #0002;
}
h1 { font-size: 1.4rem; margin-top: 0; }
input, button {
    box-sizing: border-box;
    width: 100%;
    padding: 12px;
    margin-top: 12px;
    font-size: 1rem;
}
button {
    background: #2563eb;
    color: white;
    border: 0;
    border-radius: 6px;
    cursor: pointer;
}
.error { color: #c00; }
</style>
</head>
<body>
<div class="login">
    <h1>管理画面ログイン</h1>

    <?php if ($error !== ''): ?>
        <p class="error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>

    <form method="post" action="login.php">
        <input type="hidden" name="csrf"
               value="<?= htmlspecialchars($_SESSION['login_csrf'], ENT_QUOTES, 'UTF-8') ?>">

        <input type="password"
               name="password"
               placeholder="管理パスワード"
               autocomplete="current-password"
               required
               autofocus>

        <button type="submit">ログイン</button>
    </form>
</div>
</body>
</html>

