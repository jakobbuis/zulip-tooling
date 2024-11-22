<?php

use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/*
 * This file sends an email to a particular zulip topic.
 */

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

Please post your answers to the questions below in this thread. We'll start the daily standup with 3 minutes of silent reading. See you at 1PM!
EOD;

$email = (new Email())
    ->from(new Address($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']))
    ->to($_ENV['ZULIP_CHANNEL_MAIL'])
    ->subject(date('d-m-Y'))
    ->text($text);

$mailer->send($email);
