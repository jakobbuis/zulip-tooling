# Zulip messenger
> Sends messages to Zulip on a schedule

## Requirements
- Zulip installation with incoming email gateway
- SMTP server to send email

## Installation
1. Clone repository.
1. Copy `.env.example` to `.env` and fill in the details.
1. Run `php send.php` when wanting to send the message. Note emails can take up to a minute to arrive on Zulip.
1. Run `php close.php` to close today's topic.
