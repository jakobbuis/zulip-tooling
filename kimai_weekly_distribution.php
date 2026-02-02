<?php

use Dotenv\Dotenv;
use GuzzleHttp\Client;

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv::createMutable(__DIR__);
$dotenv->load();
$dotenv->required([
    'KIMAI_URL',
    'KIMAI_API_TOKEN',
    'KIMAI_USER_IDS',
]);

$userIds = array_map('intval', array_filter(explode(',', $_ENV['KIMAI_USER_IDS'])));

// Parse command line arguments
if ($argc < 2) {
    echo "Usage: php kimai_weekly_distribution.php <start> [end]\n";
    echo "  start: Start date (YYYY-MM-DD), inclusive\n";
    echo "  end:   End date (YYYY-MM-DD), inclusive. Defaults to today.\n";
    echo "\nExample: php kimai_weekly_distribution.php 2024-01-01 2024-12-31\n";
    exit(1);
}

$startDate = $argv[1];
$endDate = $argv[2] ?? (new DateTimeImmutable('today'))->format('Y-m-d');

// Validate date formats
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $startDate) || !DateTimeImmutable::createFromFormat('Y-m-d', $startDate)) {
    echo "Error: Invalid start date format. Use YYYY-MM-DD.\n";
    exit(1);
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $endDate) || !DateTimeImmutable::createFromFormat('Y-m-d', $endDate)) {
    echo "Error: Invalid end date format. Use YYYY-MM-DD.\n";
    exit(1);
}

$client = new Client([
    'base_uri' => rtrim($_ENV['KIMAI_URL'], '/') . '/',
    'headers' => [
        'Authorization' => 'Bearer ' . $_ENV['KIMAI_API_TOKEN'],
        'Content-Type' => 'application/json',
    ],
]);

$begin = $startDate . 'T00:00:00';
$end = $endDate . 'T23:59:59';

fwrite(STDERR, "Fetching timesheets from {$begin} to {$end}...\n");

$timesheets = [];
$page = 1;
$pageSize = 500;

do {
    $response = $client->get('api/timesheets', [
        'query' => [
            'begin' => $begin,
            'end' => $end,
            'size' => $pageSize,
            'page' => $page,
            'user' => 'all',
        ],
    ]);

    $batch = json_decode($response->getBody()->getContents(), true);
    $timesheets = array_merge($timesheets, $batch);
    $page++;
} while (count($batch) === $pageSize);

fwrite(STDERR, "Fetched " . count($timesheets) . " timesheet entries.\n");

// Group hours by user -> week -> day of week
$weeklyData = [];

foreach ($timesheets as $entry) {
    $userId = $entry['user'];

    // Skip users not in our configured list
    if (!in_array($userId, $userIds)) {
        continue;
    }

    $startTime = new DateTimeImmutable($entry['begin']);
    $weekNumber = (int) $startTime->format('W');
    $year = (int) $startTime->format('o'); // ISO year for week number
    $dayOfWeek = (int) $startTime->format('N'); // 1 = Monday, 7 = Sunday
    $hours = ($entry['duration'] ?? 0) / 3600;

    $weekKey = sprintf('%d-W%02d', $year, $weekNumber);

    if (!isset($weeklyData[$userId][$weekKey])) {
        $weeklyData[$userId][$weekKey] = [
            'year' => $year,
            'week' => $weekNumber,
            'days' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 7 => 0],
        ];
    }

    $weeklyData[$userId][$weekKey]['days'][$dayOfWeek] += $hours;
}

// Expected weekly hours: 32 hours over 5 days = 6.4 hours/day
$expectedWeeklyHours = 32;

// Write CSV to STDOUT
fputcsv(STDOUT, ['user', 'week', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun', 'total'], ';', "\"", "\\", "\n");

foreach ($weeklyData as $userId => $weeks) {
    ksort($weeks); // Sort by week

    foreach ($weeks as $weekKey => $data) {
        $totalHours = array_sum($data['days']);

        // Skip weeks with no hours
        if ($totalHours <= 0) {
            continue;
        }

        $row = [$userId, $data['week']];
        for ($day = 1; $day <= 7; $day++) {
            if ($data['days'][$day] > 0) {
                $pct = ($data['days'][$day] / $expectedWeeklyHours) * 100;
                $row[] = number_format($pct, 1, '.', '') . '%';
            } else {
                $row[] = '';
            }
        }

        // Add total percentage
        $totalPct = ($totalHours / $expectedWeeklyHours) * 100;
        $row[] = number_format($totalPct, 1, '.', '') . '%';

        fputcsv(STDOUT, $row, ';', "\"", "\\", "\n");
    }
}
