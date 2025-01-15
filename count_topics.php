<?php

use GuzzleHttp\Client;

/*
 * This file closes the topic for today
 */

require_once __DIR__ . '/bootstrap.php';

$guzzle = new Client([
    'base_uri' => $_ENV['ZULIP_URL'],
    'auth' => [$_ENV['ZULIP_USERNAME'], $_ENV['ZULIP_API_KEY']],
    'headers' => [
        'User-Agent' => 'Zulip-Messenger',
    ],
]);

$response = $guzzle->get('/api/v1/streams');
$channels = array_map(function ($channel) {
    return $channel->stream_id;
}, json_decode($response->getBody()->getContents())->streams);


$topics = array_map(function($channelId) use ($guzzle) {
    $response = $guzzle->get('/api/v1/users/me/' . $channelId . '/topics');
    return array_map(function ($topic) {
        return $topic->name;
    }, json_decode($response->getBody()->getContents())->topics);
}, $channels);

$topics = array_merge(...$topics);

$completedTopics = count(array_filter($topics, function ($topic) {
    return str_starts_with($topic, '✔');
}));

$incompleteTopics = count($topics) - $completedTopics;

echo "Completed Topics: $completedTopics" . PHP_EOL;
echo "Incomplete Topics: $incompleteTopics" . PHP_EOL;
echo PHP_EOL;
$link = $_ENV['GOOGLE_SHEET_LINK'] ?? null;
if ($link) {
    echo "\033]8;;{$link}\033\\Add to sheet here\033]8;;\033\\\n" . PHP_EOL;
} else {
    echo "(You can set GOOGLE_SHEET_LINK in .env to have a convenience link here)" . PHP_EOL;
}
