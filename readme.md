# Zulip tooling
> Various tooling for use with Zulip instances

## Requirements
- Zulip installation with incoming email gateway
- SMTP server to send email
- Usage of `email-thread.php` requires imagemagick to be installed

## Installation
1. Clone repository.
1. Copy `.env.example` to `.env` and fill in the details.
1. Optionally create a file `holidays.csv` in the root folder to hardcode dates to be skipped.
1. Optionally: run the Dockerfile if you don't have PHP locally available

## Creating a topic
```bash
php send.php example@example.com
```

Note emails can take up to a minute to arrive on Zulip.

## Closing a topic
```bash
php close.php "Todo"
```

## Counting topics
```bash
php count_topics.php
```

Outputs counts of open and completed topics. You can set `GOOGLE_SHEET_LINK` in `.env` to have a clickable link to an Excel-sheet in the output; useful for linking to where you want to copy the data.

## Email thread attachments
```bash
php email-thread.php [email] [stream name] [thread name] [time string]
php email-thread.php info@example.com "Off-Topic" "Cats" "3 days ago"
```

Emails all images (not messages) in a thread since a particular time to an e-mail address. Useful for sharing cat pictures.
