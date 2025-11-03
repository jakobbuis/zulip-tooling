<?php

/*
 * Comments on open critical incident topics with SLO deadline information.
 */

require_once __DIR__ . '/bootstrap.php';

const STREAM_ID = 24;
const REMINDER_AFTER_MINUTES = 15;
const BOT_COMMENT_MARKER = 'At this point, your focus should be on applying workarounds to restore service';
const TIMEZONE = new DateTimeZone('Europe/Amsterdam');
$firstResponders = explode(',', $_ENV['FIRST_RESPONDERS']);

require_once __DIR__ . '/critical_get_open_topics.php';

foreach ($topics as $topic) {

    // Check if we've already commented on this thread
    $alreadyCommented = false;
    foreach ($topic->messages as $message) {
        if (str_contains($message->content, BOT_COMMENT_MARKER)) {
            $alreadyCommented = true;
            break;
        }
    }
    if ($alreadyCommented) {
        continue;
    }

    // Check whether it's been two hours since the first message by a first responder
    $FRMessages = array_filter($topic->messages, fn ($msg) => in_array($msg->sender_id, $firstResponders));
    if (empty($FRMessages)) {
        continue;
    }
    $firstMessageTime = reset($FRMessages)->timestamp;
    if (time() - $firstMessageTime < REMINDER_AFTER_MINUTES * 60) {
        continue;
    }

    // Post the comment to the thread
    $comment = '🔧 This incident has been worked on for 2 hours. ' . BOT_COMMENT_MARKER . ', rather than finding the root cause. If you don\'t intend to do so, please write that decision plus your reasoning in this topic.';
    $guzzle->post('/api/v1/messages', [
        'query' => [
            'type' => 'stream',
            'to' => STREAM_ID,
            'topic' => $topic->name,
            'content' => $comment,
        ],
    ]);
}

