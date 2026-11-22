<?php
declare(strict_types=1);

require_once '/home/naojima/www/emergency/config.php';

requireMethod('POST');
authenticate();

$body = readJsonBody();
$callId = $body['call_id'] ?? null;

if (!is_int($callId) || $callId < 1) {
    respond(['ok' => false, 'error' => 'Invalid Call ID'], 400);
}

$state = updateState(function (array $state) use ($callId): array {
    if (!$state['active'] || $state['call_id'] !== $callId) {
        return $state;
    }

    $state['active'] = false;
    $state['cleared_at'] = date(DATE_ATOM);

    return $state;
});

respond(['ok' => true, 'state' => $state]);

