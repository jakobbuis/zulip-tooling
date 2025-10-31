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

const STREAM_NAME = 'SRE # Critical';
const DEADLINE_HOURS = 16;
const BOT_COMMENT_MARKER = '⏰ Incident SLO:';

try {
    // Get the stream ID for "SRE - Critical"
    $response = $guzzle->get('/api/v1/streams');
    $streams = json_decode($response->getBody()->getContents())->streams;

    $targetStream = null;
    foreach ($streams as $stream) {
        if ($stream->name === STREAM_NAME) {
            $targetStream = $stream;
            break;
        }
    }

    if ($targetStream === null) {
        echo "Stream '" . STREAM_NAME . "' not found.\n";
        exit(1);
    }

    echo "Found stream: {$targetStream->name} (ID: {$targetStream->stream_id})\n";

    // Get all topics in the stream
    $response = $guzzle->get("/api/v1/users/me/{$targetStream->stream_id}/topics");
    $topics = json_decode($response->getBody()->getContents())->topics;

    echo "Found " . count($topics) . " topics in the stream.\n";

    $processedCount = 0;
    $skippedCount = 0;

    foreach ($topics as $topic) {
        // Check if the topic is already resolved (marked with ✔)
        if (str_starts_with($topic->name, '✔')) {
            echo "Skipping resolved topic: {$topic->name}\n";
            $skippedCount++;
            continue;
        }

        echo "Processing topic: {$topic->name}\n";

        // Get all messages in this topic
        $response = $guzzle->get('/api/v1/messages', [
            'query' => [
                'anchor' => 'oldest',
                'num_before' => 0,
                'num_after' => 1000, // Get up to 1000 messages
                'narrow' => json_encode([
                    ['operator' => 'stream', 'operand' => $targetStream->name],
                    ['operator' => 'topic', 'operand' => $topic->name],
                ]),
            ],
        ]);

        $messagesData = json_decode($response->getBody()->getContents());
        $messages = $messagesData->messages ?? [];

        if (empty($messages)) {
            echo "  No messages found in topic.\n";
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
            echo "  Already commented on this topic. Skipping.\n";
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

        // Calculate time remaining
        $now = time();
        $timeRemaining = $deadlineTimestamp - $now;
        $hoursRemaining = floor($timeRemaining / 3600);
        $minutesRemaining = floor(($timeRemaining % 3600) / 60);

        $timeRemainingText = '';
        if ($timeRemaining > 0) {
            if ($hoursRemaining > 0) {
                $timeRemainingText = sprintf('%d hours and %d minutes remaining', $hoursRemaining, $minutesRemaining);
            } else {
                $timeRemainingText = sprintf('%d minutes remaining', $minutesRemaining);
            }
        } else {
            $overdueHours = abs(floor($timeRemaining / 3600));
            $overdueMinutes = abs(floor(($timeRemaining % 3600) / 60));
            if ($overdueHours > 0) {
                $timeRemainingText = sprintf('**OVERDUE** by %d hours and %d minutes', $overdueHours, $overdueMinutes);
            } else {
                $timeRemainingText = sprintf('**OVERDUE** by %d minutes', $overdueMinutes);
            }
        }

        // Format the comment message
        $comment = sprintf(
            "%s **%s**\n\n" .
            "This incident was started on %s.\n" .
            "The resolution deadline is **%s** (%d hours after incident start).\n\n" .
            "%s",
            BOT_COMMENT_MARKER,
            $deadlineDate->format('Y-m-d H:i:s T'),
            $firstMessageDate->format('Y-m-d H:i:s T'),
            $deadlineDate->format('Y-m-d H:i:s T'),
            DEADLINE_HOURS,
            $timeRemainingText
        );

        // Post the comment to the thread
        $response = $guzzle->post('/api/v1/messages', [
            'query' => [
                'type' => 'stream',
                'to' => $targetStream->stream_id,
                'topic' => $topic->name,
                'content' => $comment,
            ],
        ]);

        $result = json_decode($response->getBody()->getContents());

        if ($result->result === 'success') {
            echo "  ✓ Posted deadline comment (deadline: {$deadlineDate->format('Y-m-d H:i:s')})\n";
            $processedCount++;
        } else {
            echo "  ✗ Failed to post comment: {$result->msg}\n";
        }
    }

    echo "\nSummary:\n";
    echo "  Processed: $processedCount topics\n";
    echo "  Skipped: $skippedCount topics\n";
    echo "  Total: " . count($topics) . " topics\n";

    exit(0);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
