<?php

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;

/*
 * This file sends messages from a thread to a specified email address.
 */
$email = $argv[1] ?? null;
$stream = $argv[2] ?? null;
$topic = $argv[3] ?? null;
$since = $argv[4] ?? null;

if ($email === null || $stream === null || $topic === null || $since === null) {
    echo "Usage: php send.php <email> <stream> <topic> <since>\n";
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo "Invalid email address\n";
    exit(2);
}

$since = strtotime($since);
if ($since === false) {
    echo "Invalid since date\n";
    exit(3);
}

require_once __DIR__ . '/bootstrap.php';

// Get thread
$response = $guzzle->get('/api/v1/messages', [
    'query' => [
        'anchor' => 'newest',
        'num_before' => 1000,
        'num_after' => 1,
        'narrow' => json_encode([
            ['operator' => 'stream','operand' => $stream],
            ['operator' => 'topic','operand' => $topic],
        ]),
    ],
]);
$data = $response->getBody()->getContents();
$messages = json_decode($data)->messages;
$messages = array_filter($messages, function ($message) use ($since) {
    return $message->timestamp >= $since;
});
if (empty($messages)) {
    echo "No messages found since $since\n";
    exit(4);
}

// Fetch images
$images = array_reduce($messages, function ($carry, $message) {
    if (isset($message->content) && preg_match_all('/src="(.*)"/', $message->content, $matches)) {
        return array_merge($carry, $matches[1]);
    }
    return $carry;
}, []);
$images = array_map(fn ($path) => 'https://chat.dsinternal.net' . $path, $images);
$paths = array_map(function ($image) use ($guzzle) {
    $file = tempnam(sys_get_temp_dir(), 'zulip_tooling_email_thread');
    $data = $guzzle->get($image)->getBody()->getContents();
    file_put_contents($file, $data);
    return $file;
}, $images);

// Send email
$transport = Transport::fromDsn($_ENV['MAIL_DSN']);
$mailer = new Mailer($transport);

$sinceDate = date('F j, Y', $since);
$text = "<html><body><p>Hi, this is your $topic update since $sinceDate:</p>";
array_walk($paths, function ($path, $index) use ($text) {
    $text .= "<img src=\"cid:image-$index\" />";
});
$text .= "</body></html>";

$email = (new Email())
    ->from(new Address($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']))
    ->to($email)
    ->subject($topic . ' update')
    ->html($text);

array_walk($paths, function ($path, $index) use ($email) {
    $email->addPart((new DataPart(new File($path, 'r'), "image-{$index}", 'image/jpg'))->asInline());
});

$mailer->send($email);

array_walk($paths, fn ($path) => unlink($path));
