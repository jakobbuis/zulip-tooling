<?php

/*
 * This file sends an email to a particular zulip topic.
 */

use Dotenv\Dotenv;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

require_once __DIR__ . '/vendor/autoload.php';

Dotenv::createImmutable(__DIR__)->load();

$transport = Transport::fromDsn($_ENV['MAILER_DSN']);
$mailer = new Mailer($transport);

$email = (new Email())
    ->from(new Address($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']))
    ->to($_ENV['ZULIP_CHANNEL_MAIL'])
    ->subject($_ENV['ZULIP_TOPIC']);

ob_start();
echo file_get_contents(__DIR__ . '/message.txt.php');
$text = ob_get_flush();
$email = $email->text($text);

$mailer->send($email);
