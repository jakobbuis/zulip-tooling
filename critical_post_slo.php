<?php

/*
 * Comments on open critical incident topics with SLO deadline information.
 */

require_once __DIR__ . '/bootstrap.php';

const STREAM_ID = 24;
const DEADLINE_HOURS = 16;
const BOT_COMMENT_MARKER = 'Incident SLO:';
const TIMEZONE = new DateTimeZone('Europe/Amsterdam');

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

    // Get the first message timestamp
    $firstMessage = $topic->messages[0];
    $firstMessageTime = $firstMessage->timestamp;

    // Calculate deadline (16 hours after first message)
    $deadlineTimestamp = $firstMessageTime + (DEADLINE_HOURS * 3600);
    $deadlineDate = new DateTime('@' . $deadlineTimestamp);
    $deadlineDate->setTimezone(TIMEZONE);

    $firstMessageDate = new DateTime('@' . $firstMessageTime);
    $firstMessageDate->setTimezone(TIMEZONE);

    // Format the comment message
    $comment = sprintf(
        "%s \n\n" .
        "This incident was started on %s.\n" .
        "The resolution deadline is **%s** (%d hours after incident start).",
        '⏰ '. BOT_COMMENT_MARKER,
        $firstMessageDate->format('d-m-Y H:i:s T'),
        $deadlineDate->format('d-m-Y H:i:s T'),
        DEADLINE_HOURS
    );

    // Post the comment to the thread
    $guzzle->post('/api/v1/messages', [
        'query' => [
            'type' => 'stream',
            'to' => STREAM_ID,
            'topic' => $topic->name,
            'content' => $comment,
        ],
    ]);
}

