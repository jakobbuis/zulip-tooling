<?php

use GuzzleHttp\Client;

/*
 * Count open topics per stream and in total
 */
require_once __DIR__ . '/bootstrap.php';

$guzzle = new Client([
    'base_uri' => $_ENV['ZULIP_URL'],
    'auth' => [$_ENV['ZULIP_USERNAME'], $_ENV['ZULIP_API_KEY']],
    'headers' => [
        'User-Agent' => 'Zulip-Tooling',
    ],
]);

$response = $guzzle->get('/api/v1/streams');
$channels = json_decode($response->getBody()->getContents())->streams;
$blockedListedChannels = explode(',', $_ENV['IGNORED_CHANNELS'] ?? "");
$blockedListedChannels = array_map('trim', $blockedListedChannels);
$channels = array_filter($channels, function ($channel) use ($blockedListedChannels) {
    return !in_array($channel->name, $blockedListedChannels);
});
usort($channels, fn ($a, $b) => strcasecmp($a->name, $b->name));

$statistics = array_map(function($channel) use ($guzzle) {
    $response = $guzzle->get('/api/v1/users/me/' . $channel->stream_id . '/topics');
    $topics = json_decode($response->getBody()->getContents())->topics;
    $topics = array_filter($topics, fn ($topic) => $topic->name !== 'stream events');

    $closedTopics = count(array_filter($topics, fn ($topic) => str_starts_with($topic->name, '✔')));
    $openTopics = count($topics) - $closedTopics;

    return [
        'channel' => $channel->name,
        'closed' => $closedTopics,
        'open' => $openTopics,
    ];
}, $channels);

$closedTopics = array_sum(array_column($statistics, 'closed'));
$openTopics = array_sum(array_column($statistics, 'open'));

$mask = "|%-30.30s |%6.6s |%4.4s |\n";
printf($mask, 'Stream', 'Closed', 'Open');
echo "|-------------------------------|-------|-----|" . PHP_EOL;
foreach ($statistics as $statistic) {
    printf($mask, $statistic['channel'], $statistic['closed'], $statistic['open']);
}
echo PHP_EOL;

echo "Closed Topics: $closedTopics" . PHP_EOL;
echo "Open Topics: $openTopics" . PHP_EOL;
echo PHP_EOL;

$link = $_ENV['GOOGLE_SHEET_LINK'] ?? null;
if ($link) {
    echo "\033]8;;{$link}\033\\Add to sheet here\033]8;;\033\\\n" . PHP_EOL;
} else {
    echo "(You can set GOOGLE_SHEET_LINK in .env to have a convenience link here)" . PHP_EOL;
}
