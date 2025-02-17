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

$email = (new Email())
    ->from(new Address($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']))
    ->to($email)
    ->subject(date('d-m-Y'))
    ->text($text);

$mailer->send($email);
