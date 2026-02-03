<?php

namespace ZulipTooling;

use GuzzleHttp\Client;

class Kimai
{
    private Client $client;
    private array $userIds;

    public function __construct(string $baseUri, string $apiToken, array $userIds = [])
    {
        $this->client = new Client([
            'base_uri' => rtrim($baseUri, '/') . '/',
            'headers' => [
                'Authorization' => 'Bearer ' . $apiToken,
                'Content-Type' => 'application/json',
            ],
        ]);
        $this->userIds = $userIds;
    }

    /**
     * Get time tracking summary for the last working day, grouped by user.
     * Returns data in format: ['Aloïs' => ['project_hours' => ['project' => 6.5], 'total' => 7.0], ...]
     */
    public function getLastWorkingDaySummary(): array
    {
        $lastWorkingDay = $this->getLastWorkingDay();
        $begin = $lastWorkingDay->format('Y-m-d') . 'T00:00:00';
        $end = $lastWorkingDay->format('Y-m-d') . 'T23:59:59';

        $timesheets = $this->getTimesheets($begin, $end);
        $users = $this->getUsers();
        $projects = $this->getProjects();

        // Build lookup maps, filtering to configured user IDs
        $userMap = [];
        foreach ($users as $user) {
            if (empty($this->userIds) || in_array($user['id'], $this->userIds)) {
                $userMap[$user['id']] = $user['alias'] ?: $user['username'];
            }
        }

        $projectMap = [];
        foreach ($projects as $project) {
            $projectMap[$project['id']] = $project['name'];
        }

        // Initialize summary for all configured users (even if they have 0 hours)
        $summary = [];
        foreach ($userMap as $userId => $userName) {
            $summary[$userName] = ['project_hours' => [], 'total' => 0];
        }

        // Aggregate hours by user and project
        foreach ($timesheets as $entry) {
            $userId = $entry['user'];
            $projectId = $entry['project'];
            $hours = ($entry['duration'] ?? 0) / 3600;

            // Skip users not in our configured list
            if (!isset($userMap[$userId])) {
                continue;
            }

            $userName = $userMap[$userId];
            $projectName = $projectMap[$projectId] ?? "Project {$projectId}";

            if (!isset($summary[$userName]['project_hours'][$projectName])) {
                $summary[$userName]['project_hours'][$projectName] = 0;
            }

            $summary[$userName]['project_hours'][$projectName] += $hours;
            $summary[$userName]['total'] += $hours;
        }

        // Sort by username
        ksort($summary);

        return $summary;
    }

    private function getLastWorkingDay(): \DateTimeImmutable
    {
        $today = new \DateTimeImmutable('today');
        $dayOfWeek = (int) $today->format('N'); // 1 = Monday, 7 = Sunday

        // On Monday, go back to Friday (3 days)
        // On Sunday, go back to Friday (2 days)
        // On Saturday, go back to Friday (1 day)
        // Otherwise, go back 1 day
        $daysBack = match ($dayOfWeek) {
            1 => 3, // Monday -> Friday
            7 => 2, // Sunday -> Friday
            6 => 1, // Saturday -> Friday
            default => 1,
        };

        return $today->modify("-{$daysBack} days");
    }

    /**
     * Format the summary as a string for the template.
     */
    public function formatSummaryForTemplate(array $summary, ?string $highlightProject = null): string
    {
        if (empty($summary)) {
            return "No time entries recorded.\n";
        }

        $lines = [];
        foreach ($summary as $userName => $data) {
            $total = $data['total'];
            $projectHours = $data['project_hours'];

            // Always use the highlight project if specified (even if 0 hours)
            $mainProject = $highlightProject ?? array_key_first($projectHours);
            $mainHours = $projectHours[$mainProject] ?? 0;

            $percentage = $total > 0 ? round(($mainHours / $total) * 100) : 0;

            $lines[] = sprintf(
                "%s: %s hours %s, %s hours total (%d%%)",
                $userName,
                $this->formatHours($mainHours),
                $mainProject,
                $this->formatHours($total),
                $percentage
            );
        }

        return implode("\n", $lines) . "\n";
    }

    private function formatHours(float $hours): string
    {
        if ($hours == floor($hours)) {
            return (string) (int) $hours;
        }
        return number_format($hours, 1);
    }

    private function getTimesheets(string $begin, string $end): array
    {
        $response = $this->client->get('api/timesheets', [
            'query' => [
                'begin' => $begin,
                'end' => $end,
                'size' => 1000,
                'user' => 'all',
            ],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    private function getUsers(): array
    {
        $response = $this->client->get('api/users', [
            'query' => ['visible' => 1],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }

    private function getProjects(): array
    {
        $response = $this->client->get('api/projects', [
            'query' => ['visible' => 1],
        ]);

        return json_decode($response->getBody()->getContents(), true);
    }
}
