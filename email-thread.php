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

// Fetch images
$images = array_reduce($messages, function ($carry, $message) {
    if (isset($message->content) && preg_match_all('/src="(.*)"/', $message->content, $matches)) {
        return array_merge($carry, $matches[1]);
    }
    return $carry;
}, []);

$images = array_map(fn ($path) => $_ENV['ZULIP_URL'] . $path, $images);
$images = array_map(function ($image) use ($guzzle) {
    $file = tempnam(sys_get_temp_dir(), 'zulip_tooling_email_thread');
    $response = $guzzle->get($image);
    $image = $response->getBody()->getContents();
    file_put_contents($file, $image);
    $type = $response->getHeader('Content-Type')[0];
    return ['path' => $file, 'type' => $type];
}, $images);

// Resize images
$images = array_map(function ($image) {
    $source = $image['path'];
    $destination = tempnam(sys_get_temp_dir(), 'zulip_tooling_email_thread');
    exec("convert $source -resize 500x500 $destination");
    unlink($source);
    return ['path' => $destination, 'type' => $image['type']];
}, $images);

// Send email
$transport = Transport::fromDsn($_ENV['MAIL_DSN']);
$mailer = new Mailer($transport);

$sinceDate = date('F j, Y', $since);
$text = "<html><body><p>Hi, this is your $topic update since $sinceDate:</p>";
if (empty($images)) {
    $text .= "<p>There were no new updates.</p>";
} else {
    array_walk($images, function ($image, $index) use (&$text) {
        $text .= "<img src=\"cid:image-$index\" />";
    });
}
$text .= "</body></html>";

$email = (new Email())
    ->from(new Address($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']))
    ->to($email)
    ->subject($topic . ' update')
    ->html($text);

array_walk($images, function ($image, $index) use ($email) {
    $email->addPart((new DataPart(new File($image['path'], 'r'), "image-{$index}", $image['type']))->asInline());
});

$mailer->send($email);

array_walk($images, fn ($image) => unlink($image['path']));
