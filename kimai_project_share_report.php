<?php

/**
 * Generate a CSV report of hours spent on KIMAI_HIGHLIGHT_PROJECT by KIMAI_USER_IDS
 * for the period 2025-09-01 until last Friday.
 *
 * Output format: user_id, date (YYYY-MM-DD), hours [project] (1 decimal), total hours
 *
 * Usage: php kimai_project_share_report.php [start]
 */

require_once __DIR__ . '/bootstrap.php';

use GuzzleHttp\Client;

$kimaiUrl = $_ENV['KIMAI_URL'];
$apiToken = $_ENV['KIMAI_API_TOKEN'];
$highlightProject = $_ENV['KIMAI_HIGHLIGHT_PROJECT'] ?? null;
$userIds = !empty($_ENV['KIMAI_USER_IDS'])
    ? array_map('intval', explode(',', $_ENV['KIMAI_USER_IDS']))
    : [];

if (empty($kimaiUrl) || empty($apiToken) || empty($highlightProject) || empty($userIds)) {
    echo "Missing required env vars: KIMAI_URL, KIMAI_API_TOKEN, KIMAI_HIGHLIGHT_PROJECT, KIMAI_USER_IDS\n";
    exit(1);
}

$client = new Client([
    'base_uri' => rtrim($kimaiUrl, '/') . '/',
    'headers' => [
        'Authorization' => 'Bearer ' . $apiToken,
        'Content-Type' => 'application/json',
    ],
]);

// Determine date range: 2025-09-01 to last Friday
$start = new DateTimeImmutable('2025-09-01');
$end = new DateTimeImmutable('today');

fprintf(STDERR, "Report period: %s to %s\n", $start->format('Y-m-d'), $end->format('Y-m-d'));

// Fetch users and projects
$users = json_decode($client->get('api/users', ['query' => ['visible' => 1]])->getBody()->getContents(), true);
$projects = json_decode($client->get('api/projects', ['query' => ['visible' => 1]])->getBody()->getContents(), true);

$userMap = [];
foreach ($users as $user) {
    if (in_array($user['id'], $userIds)) {
        $userMap[$user['id']] = $user['alias'] ?: $user['username'];
    }
}

$projectMap = [];
$highlightProjectId = null;
foreach ($projects as $project) {
    $projectMap[$project['id']] = $project['name'];
    if ($project['name'] === $highlightProject) {
        $highlightProjectId = $project['id'];
    }
}

// Fetch timesheets in pages (API has size limit of 1000)
$allTimesheets = [];
$page = 1;
$begin = $start->format('Y-m-d') . 'T00:00:00';
$endStr = $end->format('Y-m-d') . 'T23:59:59';

do {
    $response = $client->get('api/timesheets', [
        'query' => [
            'begin' => $begin,
            'end' => $endStr,
            'size' => 1000,
            'page' => $page,
            'user' => 'all',
        ],
    ]);
    $batch = json_decode($response->getBody()->getContents(), true);
    $allTimesheets = array_merge($allTimesheets, $batch);
    $page++;
} while (count($batch) === 1000);

fprintf(STDERR, "Fetched %d timesheet entries across %d pages\n", count($allTimesheets), $page - 1);

// Aggregate: $data[userId][date] = ['project' => hours, 'total' => hours]
$data = [];

foreach ($allTimesheets as $entry) {
    $userId = $entry['user'];
    if (!isset($userMap[$userId])) {
        continue;
    }

    $hours = ($entry['duration'] ?? 0) / 3600;
    $entryDate = (new DateTimeImmutable($entry['begin']))->format('Y-m-d');

    if (!isset($data[$userId][$entryDate])) {
        $data[$userId][$entryDate] = ['project' => 0, 'total' => 0];
    }

    $data[$userId][$entryDate]['total'] += $hours;

    if ($entry['project'] === $highlightProjectId) {
        $data[$userId][$entryDate]['project'] += $hours;
    }
}

// Output CSV
$out = fopen('php://stdout', 'w');
fputcsv($out, ['user_id', 'date', 'hours_project', 'total_hours']);

foreach ($userIds as $userId) {
    if (!isset($data[$userId])) {
        continue;
    }

    $dates = array_keys($data[$userId]);
    sort($dates);

    foreach ($dates as $date) {
        $row = $data[$userId][$date];
        fputcsv($out, [
            $userId,
            $date,
            number_format($row['project'], 1),
            number_format($row['total'], 1),
        ]);
    }
}

fclose($out);
