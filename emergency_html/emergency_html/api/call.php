<?php
declare(strict_types=1);

require_once '/home/naojima/www/emergency/config.php';

requireMethod('POST');
authenticate();

$body = readJsonBody();
$sender = $body['sender'] ?? 'esp8266';

if (!is_string($sender) || !preg_match('/^[a-zA-Z0-9_-]{1,32}$/', $sender)) {
    respond(['ok' => false, 'error' => 'Invalid Sender'], 400);
}

$state = updateState(function (array $state) use ($sender): array {
    // 既に呼び出し中ならIDを変えない
    if ($state['active']) {
        return $state;
    }

    $state['active'] = true;
    $state['sender'] = $sender;
    $state['call_id']++;
    $state['created_at'] = date(DATE_ATOM);
    $state['cleared_at'] = null;

    return $state;
});

respond(['ok' => true, 'state' => $state]);

