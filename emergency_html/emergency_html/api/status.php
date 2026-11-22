<?php
declare(strict_types=1);

require_once '/home/naojima/www/emergency/config.php';

requireMethod('GET');
authenticate();

respond([
    'ok' => true,
    'state' => readState()
]);

