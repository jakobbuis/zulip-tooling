# Zulip tooling
> Various tooling for use with Zulip instances

## Requirements
- Zulip installation with incoming email gateway
- SMTP server to send email

## Installation
1. Clone repository.
1. Copy `.env.example` to `.env` and fill in the details.
1. Optionally create a file `holidays.csv` in the root folder to hardcode dates to be skipped.

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
