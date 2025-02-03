<?php

use GuzzleHttp\Client;

/*
 * This file closes the topic for today
 */

$stream = (string) $argv[1] ?? null;
if ($stream === null) {
    echo "Usage: php send.php <stream>\n";
    exit(1);
}

require_once __DIR__ . '/bootstrap.php';

$guzzle = new Client([
    'base_uri' => $_ENV['ZULIP_URL'],
    'auth' => [$_ENV['ZULIP_USERNAME'], $_ENV['ZULIP_API_KEY']],
    'headers' => [
        'User-Agent' => 'Zulip-Tooling',
    ],
]);

// Get the latest message from today's topic
$todaysTopic = date('d-m-Y');

$response = $guzzle->get('/api/v1/messages', [
    'query' => [
        'anchor' => 'oldest',
        'num_before' => 1,
        'num_after' => 1,
        'narrow' => json_encode([
            ['operator' => 'stream', 'operand' => $stream],
            ['operator' => 'topic', 'operand' => $todaysTopic],
        ]),
    ],
]);
$data = $response->getBody()->getContents();
$messages = json_decode($data)->messages;
if (count($messages) === 0) {
    echo 'No messages found for today\'s topic' . PHP_EOL;
    exit(1);
}
$id = $messages[0]->id;

// Patch the topic to be resolved
$guzzle->patch('/api/v1/messages/' . $id, [
    'query' => [
        'topic' => '✔ ' . $todaysTopic,
        'propagate_mode' => 'change_all',
    ],
]);
