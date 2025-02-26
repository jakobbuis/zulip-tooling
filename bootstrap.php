<?php

use Dotenv\Dotenv;
use GuzzleHttp\Client;
use League\Csv\Reader;

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

// Skip operation on national holidays
$path = __DIR__ . '/holidays.csv';
if (is_readable($path)) {
    $csv = Reader::createFromPath($path, 'r');
    $csv->setHeaderOffset(0);
    $holidays = array_map(fn ($line) => $line['date'], iterator_to_array($csv->getRecords()));

    if (in_array(date('Y-m-d'), $holidays)) {
        echo "Today is a national holiday. Skipping operation.\n";
        exit;
    }
}

$guzzle = new Client([
    'base_uri' => $_ENV['ZULIP_URL'],
    'auth' => [$_ENV['ZULIP_USERNAME'], $_ENV['ZULIP_API_KEY']],
    'headers' => [
        'User-Agent' => 'Zulip-Tooling',
    ],
]);
