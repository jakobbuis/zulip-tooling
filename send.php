<?php

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/*
 * This file sends an email to a particular zulip topic.
 */

$email = (string) $argv[1] ?? null;
$template = (string) $argv[2] ?? null;
$showOpenTopicsList = isset($argv[3]) && $argv[3] === '--show-open-topics';

if ($email === null || $template === null) {
    echo "Usage: php send.php <email> <template> [--show-open-topics]\n";
    echo "Available templates: team-mad, the-team\n";
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Invalid email address\n";
    exit(2);
}

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/skip_holidays.php';

$templateFile = __DIR__ . "/templates/{$template}.md";
if (!file_exists($templateFile)) {
    echo "Template '{$template}' not found. Available templates: team-mad, the-team\n";
    exit(3);
}

$transport = Transport::fromDsn($_ENV['MAIL_DSN']);
$mailer = new Mailer($transport);

$date = date('F j, Y');
$text = file_get_contents($templateFile);
$text = str_replace('$date', $date, $text);

// Add open topics to the message if today is Wednesday or flag is provided
if (date('w') == 3 && $showOpenTopicsList) { // 3 = Wednesday
    $response = $guzzle->get('/api/v1/streams');
    $channels = json_decode($response->getBody()->getContents())->streams;
    $blockedListedChannels = explode(',', $_ENV['IGNORED_CHANNELS'] ?? "");
    $blockedListedChannels = array_map('trim', $blockedListedChannels);
    $channels = array_filter($channels, function ($channel) use ($blockedListedChannels) {
        return !in_array($channel->name, $blockedListedChannels);
    });

    $openTopicsByChannel = [];
    foreach ($channels as $channel) {
        $response = $guzzle->get('/api/v1/users/me/' . $channel->stream_id . '/topics');
        $topics = json_decode($response->getBody()->getContents())->topics;
        $topics = array_filter($topics, fn ($topic) => $topic->name !== 'stream events' && !str_starts_with($topic->name, '✔'));

        if (!empty($topics)) {
            $openTopicsByChannel[$channel->name] = array_map(fn ($topic) => $topic->name, $topics);
        }
    }

    if (!empty($openTopicsByChannel)) {

        $text .= "\nHere are the open topics to review today, grouped by channel. Review this list in the daily and resolve any lingering items.\n\n";

        foreach ($openTopicsByChannel as $channel => $topics) {
            foreach ($topics as $topic) {
                $text .= "* #**{$channel}>{$topic}** \n";
            }
        }
    }
}

$email = (new Email())
    ->from(new Address($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']))
    ->to($email)
    ->subject(date('d-m-Y'))
    ->text($text);

$mailer->send($email);
