<?php
declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once '/home/naojima/www/emergency/config.php';

// CSRFトークン
if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$message = $_SESSION['admin_message'] ?? '';
unset($_SESSION['admin_message']);

// 操作処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';

    if (!is_string($csrf) ||
        !hash_equals($_SESSION['admin_csrf'], $csrf)) {
        http_response_code(403);
        exit('不正なリクエストです。');
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'call') {
        $state = updateState(function (array $state): array {
            // すでに呼び出し中なら重複登録しない
            if (!$state['active']) {
                $state['active'] = true;
                $state['sender'] = 'web-test';
                $state['call_id']++;
                $state['created_at'] = date(DATE_ATOM);
                $state['cleared_at'] = null;
            }

            return $state;
        });

        $_SESSION['admin_message'] =
            'テスト呼び出しを受け付けました。';

    } elseif ($action === 'clear') {
        $callId = filter_var(
            $_POST['call_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if ($callId === false || $callId === null) {
            $_SESSION['admin_message'] = '呼び出しIDが不正です。';
        } else {
            updateState(function (array $state) use ($callId): array {
                // 現在の呼び出しIDが一致するときだけ解除
                if ($state['active'] &&
                    $state['call_id'] === $callId) {
                    $state['active'] = false;
                    $state['cleared_at'] = date(DATE_ATOM);
                }

                return $state;
            });

            $_SESSION['admin_message'] = '解除操作を行いました。';
        }
    }

    header('Location: index.php');
    exit;
}

// 現在の状態を取得
$state = readState();

function h(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="refresh" content="5">
<title>緊急呼び出しシステム</title>
<style>
body {
    font-family: sans-serif;
    background: #f3f4f6;
    margin: 0;
    padding: 20px;
    color: #222;
}
.container {
    max-width: 520px;
    margin: 30px auto;
}
.panel {
    background: white;
    padding: 24px;
    border-radius: 12px;
    box-shadow: 0 2px 12px #0002;
}
h1 {
    font-size: 1.5rem;
    margin-top: 0;
}
.status {
    text-align: center;
    padding: 24px 12px;
    border-radius: 10px;
    font-size: 1.8rem;
    font-weight: bold;
    margin: 20px 0;
}
.waiting {
    background: #dcfce7;
    color: #166534;
}
.active {
    background: #fee2e2;
    color: #b91c1c;
}
.details {
    line-height: 1.9;
    overflow-wrap: anywhere;
}
button {
    width: 100%;
    padding: 15px;
    margin-top: 12px;
    border: 0;
    border-radius: 7px;
    font-size: 1rem;
    font-weight: bold;
    cursor: pointer;
}
.call-button {
    background: #2563eb;
    color: white;
}
.clear-button {
    background: #dc2626;
    color: white;
}
.logout-button {
    background: #6b7280;
    color: white;
}
button:disabled {
    opacity: .45;
    cursor: not-allowed;
}
.message {
    background: #eff6ff;
    padding: 12px;
    border-radius: 7px;
}
.footer {
    text-align: center;
    color: #666;
    font-size: .85rem;
    margin-top: 18px;
}
</style>
</head>
<body>
<div class="container">
<div class="panel">

<h1>緊急呼び出しシステム</h1>

<?php if ($message !== ''): ?>
<p class="message"><?= h($message) ?></p>
<?php endif; ?>

<div class="status <?= $state['active'] ? 'active' : 'waiting' ?>">
    <?= $state['active'] ? '呼び出し中' : '待機中' ?>
</div>

<div class="details">
    <strong>呼び出し元：</strong>
    <?= h($state['sender'] ?? '－') ?><br>

    <strong>呼び出しID：</strong>
    <?= h($state['call_id'] ?? 0) ?><br>

    <strong>呼び出し日時：</strong>
    <?= h($state['created_at'] ?? '－') ?>
</div>

<hr>

<form method="post" action="index.php">
    <input type="hidden" name="csrf"
           value="<?= h($_SESSION['admin_csrf']) ?>">
    <input type="hidden" name="action" value="call">
    <button class="call-button" type="submit"
            <?= $state['active'] ? 'disabled' : '' ?>>
        テスト呼び出し
    </button>
</form>

<form method="post" action="index.php">
    <input type="hidden" name="csrf"
           value="<?= h($_SESSION['admin_csrf']) ?>">
    <input type="hidden" name="action" value="clear">
    <input type="hidden" name="call_id"
           value="<?= h($state['call_id'] ?? 0) ?>">
    <button class="clear-button" type="submit"
            <?= !$state['active'] ? 'disabled' : '' ?>>
        呼び出し解除
    </button>
</form>

<form method="post" action="logout.php">
    <input type="hidden" name="csrf"
           value="<?= h($_SESSION['admin_csrf']) ?>">
    <button class="logout-button" type="submit">
        ログアウト
    </button>
</form>

<div class="footer">
    5秒ごとに自動更新
</div>

</div>
</div>
</body>
</html>

