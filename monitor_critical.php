<?php

/*
 * Monitor "SRE - Critical" stream for unresolved threads
 *
 * This script polls the "SRE - Critical" stream and comments on any unresolved
 * threads with a deadline reminder. The deadline is 16 hours after the first
 * message in the thread. Each thread is only commented on once.
 *
 * Usage: php monitor_critical.php
 * Recommended: Run via cron every minute
 */

require_once __DIR__ . '/bootstrap.php';

const STREAM_ID = 24;
const DEADLINE_HOURS = 16;
const BOT_COMMENT_MARKER = '⏰ Incident SLO:';

try {
    // Get all topics in the stream
    $response = $guzzle->get("/api/v1/users/me/" . STREAM_ID . "/topics");
    $topics = json_decode($response->getBody()->getContents())->topics;

    $processedCount = 0;
    $skippedCount = 0;

    foreach ($topics as $topic) {
        // Check if the topic is already resolved (marked with ✔)
        if (str_starts_with($topic->name, '✔')) {
            $skippedCount++;
            continue;
        }


        // Get all messages in this topic
        $response = $guzzle->get('/api/v1/messages', [
            'query' => [
                'anchor' => 'oldest',
                'num_before' => 0,
                'num_after' => 1000, // Get up to 1000 messages
                'narrow' => json_encode([
                    ['operator' => 'stream', 'operand' => STREAM_ID],
                    ['operator' => 'topic', 'operand' => $topic->name],
                ]),
            ],
        ]);

        $messagesData = json_decode($response->getBody()->getContents());
        $messages = $messagesData->messages ?? [];

        if (empty($messages)) {
            continue;
        }

        // Check if we've already commented on this thread
        $alreadyCommented = false;
        foreach ($messages as $message) {
            if (str_contains($message->content, BOT_COMMENT_MARKER)) {
                $alreadyCommented = true;
                break;
            }
        }

        if ($alreadyCommented) {
            $skippedCount++;
            continue;
        }

        // Get the first message timestamp
        $firstMessage = $messages[0];
        $firstMessageTime = $firstMessage->timestamp;

        // Calculate deadline (16 hours after first message)
        $deadlineTimestamp = $firstMessageTime + (DEADLINE_HOURS * 3600);
        $deadlineDate = new DateTime('@' . $deadlineTimestamp);
        $deadlineDate->setTimezone(new DateTimeZone('Europe/Amsterdam')); // Adjust timezone as needed

        $firstMessageDate = new DateTime('@' . $firstMessageTime);
        $firstMessageDate->setTimezone(new DateTimeZone('Europe/Amsterdam'));

        // Format the comment message
        $comment = sprintf(
            "%s **%s**\n\n" .
            "This incident was started on %s.\n" .
            "The resolution deadline is **%s** (%d hours after incident start).",
            BOT_COMMENT_MARKER,
            $deadlineDate->format('Y-m-d H:i:s T'),
            $firstMessageDate->format('Y-m-d H:i:s T'),
            $deadlineDate->format('Y-m-d H:i:s T'),
            DEADLINE_HOURS
        );

        // Post the comment to the thread
        $response = $guzzle->post('/api/v1/messages', [
            'query' => [
                'type' => 'stream',
                'to' => STREAM_ID,
                'topic' => $topic->name,
                'content' => $comment,
            ],
        ]);

        $result = json_decode($response->getBody()->getContents());

        if ($result->result === 'success') {
            $processedCount++;
        } else {
        }
    }


    exit(0);

} catch (Exception $e) {
    exit(1);
}
