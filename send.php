<?php

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/*
 * This file sends an email to a particular zulip topic.
 */

$email = (string) $argv[1] ?? null;
if ($email === null) {
    echo "Usage: php send.php <email>\n";
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Invalid email address\n";
    exit(2);
}

require_once __DIR__ . '/bootstrap.php';

$transport = Transport::fromDsn($_ENV['MAIL_DSN']);
$mailer = new Mailer($transport);

$date = date('F j, Y');
$text = <<<EOD
:wave: Good morning! Today is $date. It's time for daily standup:

1. What did you accomplish yesterday?
1. What are you going to finish today?
1. Are there any blockers in your way?
1. Clearly describe any out-of-office planned for the next 24 hours.

Please post your answers to the questions below in this thread.
EOD;

// Add open topics to the message if today is Wednesday
if (date('w') == 3) { // 3 = Wednesday
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
            $openTopicsByChannel[$channel->name] = array_map(function ($topic) use ($channel) {
                return sprintf(
                    '<a href="%s/#narrow/stream/%s/topic/%s">%s</a>',
                    $_ENV['ZULIP_URL'],
                    $channel->stream_id,
                    rawurlencode($topic->name),
                    htmlspecialchars($topic->name)
                );
            }, $topics);
        }
    }

    if (!empty($openTopicsByChannel)) {
        $openTopicsHtml = "<ul>";
        foreach ($openTopicsByChannel as $channelName => $topics) {
            $openTopicsHtml .= sprintf(
                '<li><strong>%s</strong><ul><li>%s</li></ul></li>',
                htmlspecialchars($channelName),
                implode('</li><li>', $topics)
            );
        }
        $openTopicsHtml .= "</ul>";
        $text .= "\n\n---\n\nHere are the open topics to review today, grouped by channel. Review this list in the daily and resolve any lingering items\n\n" . $openTopicsHtml;
    }
}

$email = (new Email())
    ->from(new Address($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']))
    ->to($email)
    ->subject(date('d-m-Y'))
    ->html(nl2br($text)); // Convert newlines to <br> for HTML rendering

$mailer->send($email);
