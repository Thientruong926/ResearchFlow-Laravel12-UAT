<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

class GoogleSheetsService
{
    public function responses(array $settings): array
    {
        if (! config('research.google_sync_enabled')) {
            return ['records' => [], 'errors' => ['Đồng bộ Google đang tắt.'], 'synced_at' => now()];
        }

        $records = [];
        $errors = [];
        $seenUrls = [];

        foreach ($settings['assignments'] as $assignment) {
            if (! ($assignment['active'] ?? false) || empty($assignment['sheet_csv_url'])) {
                continue;
            }

            try {
                $url = $this->csvUrl($assignment['sheet_csv_url']);
            } catch (InvalidArgumentException $exception) {
                $errors[] = ($assignment['id'] ?? 'assignment').': '.$exception->getMessage();
                continue;
            }
            if (isset($seenUrls[$url])) {
                continue;
            }
            $seenUrls[$url] = true;

            try {
                $response = Http::timeout(15)->get($url);
                if (! $response->successful()) {
                    $errors[] = $assignment['id'].': HTTP '.$response->status();
                    continue;
                }

                foreach ($this->parseCsv($response->body()) as $row) {
                    $row['_source_school'] = $assignment['school'];
                    $row['_source_group'] = $assignment['group'];
                    $records[] = $row;
                }
            } catch (ConnectionException) {
                $errors[] = $assignment['id'].': không thể kết nối Google Sheets';
            }
        }

        return ['records' => $records, 'errors' => $errors, 'synced_at' => now()];
    }

    public function summarize(array $records, array $settings): array
    {
        $columns = $settings['sheet_columns'];
        $byDate = [];
        $bySchool = [];
        $byGroup = [];

        foreach ($records as $record) {
            $timestamp = $record[$columns['timestamp']] ?? '';
            $date = $this->dateOnly((string) $timestamp);
            $school = trim((string) ($record[$columns['school']] ?? $record['_source_school'] ?? 'Không rõ'));
            $group = trim((string) ($record[$columns['group']] ?? $record['_source_group'] ?? 'Không rõ'));

            $byDate[$date] = ($byDate[$date] ?? 0) + 1;
            $bySchool[$school] = ($bySchool[$school] ?? 0) + 1;
            $byGroup[$group] = ($byGroup[$group] ?? 0) + 1;
        }

        ksort($byDate);
        arsort($bySchool);
        arsort($byGroup);

        $target = collect($settings['schools'])->sum(
            fn (array $school) => array_sum($school['targets'] ?? [])
        );

        return [
            'total' => count($records),
            'target' => $target,
            'response_rate' => $target > 0 ? round(count($records) / $target * 100, 1) : null,
            'by_date' => $byDate,
            'by_school' => $bySchool,
            'by_group' => $byGroup,
        ];
    }

    public function filter(array $records, array $settings, array $filters): array
    {
        $columns = $settings['sheet_columns'];

        return array_values(array_filter($records, function (array $record) use ($columns, $filters) {
            $school = (string) ($record[$columns['school']] ?? $record['_source_school'] ?? '');
            $group = (string) ($record[$columns['group']] ?? $record['_source_group'] ?? '');
            $timestamp = (string) ($record[$columns['timestamp']] ?? '');
            $haystack = Str::lower(implode(' ', array_map('strval', $record)));

            return ($filters['school'] === '' || $filters['school'] === $school)
                && ($filters['group'] === '' || $filters['group'] === $group)
                && ($filters['date'] === '' || $filters['date'] === $this->dateOnly($timestamp))
                && ($filters['q'] === '' || Str::contains($haystack, Str::lower($filters['q'])));
        }));
    }

    public function csvUrl(string $url): string
    {
        if (strtolower((string) parse_url($url, PHP_URL_HOST)) !== 'docs.google.com') {
            throw new InvalidArgumentException('URL Google Sheets không hợp lệ');
        }

        if (Str::contains($url, ['output=csv', 'format=csv'])) {
            return $url;
        }

        if (preg_match('~/spreadsheets/d/([^/]+)~', $url, $matches)) {
            preg_match('/[?#&]gid=(\d+)/', $url, $gidMatch);
            $gid = $gidMatch[1] ?? '0';
            return "https://docs.google.com/spreadsheets/d/{$matches[1]}/export?format=csv&gid={$gid}";
        }

        return $url;
    }

    private function parseCsv(string $csv): array
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, $csv);
        rewind($stream);
        $headers = fgetcsv($stream);
        $rows = [];

        if (! is_array($headers)) {
            fclose($stream);
            return [];
        }

        $headers = array_map(fn ($header) => trim((string) $header), $headers);
        while (($values = fgetcsv($stream)) !== false) {
            if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }
            $values = array_pad($values, count($headers), '');
            $rows[] = array_combine($headers, array_slice($values, 0, count($headers)));
        }

        fclose($stream);
        return $rows;
    }

    private function dateOnly(string $value): string
    {
        if ($value === '') {
            return 'Không rõ ngày';
        }

        $timestamp = strtotime(str_replace('/', '-', $value));
        return $timestamp ? date('Y-m-d', $timestamp) : mb_substr($value, 0, 10);
    }
}
