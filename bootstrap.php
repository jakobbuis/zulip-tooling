<?php

use Dotenv\Dotenv;
use GuzzleHttp\Client;

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv::createMutable(__DIR__);
$dotenv->load();
$dotenv->required([
    'MAIL_DSN',
    'MAIL_FROM_NAME',
    'MAIL_FROM_ADDRESS',
    'ZULIP_USERNAME',
    'ZULIP_API_KEY',
    'ZULIP_URL',
]);

$guzzle = new Client([
    'base_uri' => $_ENV['ZULIP_URL'],
    'auth' => [$_ENV['ZULIP_USERNAME'], $_ENV['ZULIP_API_KEY']],
    'headers' => [
        'User-Agent' => 'Zulip-Tooling',
    ],
]);
