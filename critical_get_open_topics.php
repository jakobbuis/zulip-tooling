<?php

// Get all topics in the stream
$response = $guzzle->get("/api/v1/users/me/" . STREAM_ID . "/topics");
$topics = json_decode($response->getBody()->getContents())->topics;

// Ignore resolved topics
$topics = array_filter($topics, function ($topic) {
    return !str_starts_with($topic->name, '✔');
});

// Add messages
$topics = array_map(function ($topic) use ($guzzle) {
    // Get all messages in this topic
    $response = $guzzle->get('/api/v1/messages', [
        'query' => [
            'anchor' => 'oldest',
            'num_before' => 0,
            'num_after' => 1000,
            'narrow' => json_encode([
                ['operator' => 'stream', 'operand' => STREAM_ID],
                ['operator' => 'topic', 'operand' => $topic->name],
            ]),
        ],
    ]);

    $messagesData = json_decode($response->getBody()->getContents());
    $messages = $messagesData->messages ?? [];

    return (object) ['name' => $topic->name, 'messages' => $messages];
}, $topics);

