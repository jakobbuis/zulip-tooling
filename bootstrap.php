<?php

use Dotenv\Dotenv;

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv::createMutable(__DIR__);
$dotenv->load();
$dotenv->required([
    'MAIL_DSN',
    'MAIL_FROM_NAME',
    'MAIL_FROM_ADDRESS',
    'ZULIP_CHANNEL_MAIL',
    'ZULIP_API_KEY',
]);
