<?php
declare(strict_types=1);

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

if (empty($_SESSION['authenticated'])) {
    header('Location: login.php');
    exit;
}

