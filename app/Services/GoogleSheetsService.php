<?php

namespace App\Services;

use Illuminate\Support\Str;
use InvalidArgumentException;

class GoogleSheetsService implements \App\Contracts\SurveyResponseReader
{
    public function read(\App\Data\SourceDefinition $source, ?float $deadline = null): ConnectionReadResult
    {
        if ($source->definition['transport'] !== 'demo_csv') {
            return new ConnectionReadResult($source->definition['source_id'], 'unconfigured', 'NOT_CONFIGURED', 'Adapter Sheets API riêng tư chưa được bàn giao.');
        }
        return $this->readSource($source->definition, $deadline);
    }

    public function responses(array $settings): array
    {
        $records = [];
        $errors = [];
        $results = [];
        foreach (app(ConnectionSources::class)->all($settings) as $source) {
            $result = app(\App\Contracts\SurveyResponseReader::class)->read(new \App\Data\SourceDefinition($source));
            $results[$source['source_id']] = $result->metadata();
            if ($result->status !== 'ok') {
                $errors[] = $source['source_id'].': '.$result->message;
            } else {
                array_push($records, ...$result->records);
            }
        }
        return ['records' => $records, 'errors' => $errors, 'sources' => $results,
            'checked_at' => now(), 'synced_at' => $results !== [] && $errors === [] ? now() : null];
    }

    public function readSource(array $source, ?float $deadline = null): ConnectionReadResult
    {
        $error = function (string $code) use ($source) {
            $messages = [
                'DISABLED' => 'Tắt — bật đồng bộ và ít nhất một phiếu để kiểm tra.',
                'NOT_CONFIGURED' => 'Chưa cấu hình URL Google Sheets HTTPS hợp lệ.',
                'UNAUTHORIZED' => 'Chưa xác thực nguồn. Kiểm tra quyền đọc.',
                'FORBIDDEN' => 'Không có quyền đọc. Kiểm tra quyền được cấp cho nguồn demo.',
                'NOT_FOUND' => 'Không tìm thấy nguồn. Kiểm tra Sheet và tab.',
                'RATE_LIMITED' => 'Google giới hạn lượt đọc. Vui lòng thử lại sau.',
                'TIMEOUT' => 'Không kết nối được trong thời hạn. Kiểm tra mạng và thử lại.',
                'UPSTREAM_ERROR' => 'Google trả lỗi. Vui lòng thử lại sau.',
                'INVALID_PAYLOAD' => 'Nội dung không hợp lệ, trang đăng nhập hoặc vượt giới hạn tải.',
                'SCHEMA_MISMATCH' => 'Cột dữ liệu thiếu/trùng hoặc số cột không khớp. Kiểm tra mapping.',
                'LIMIT_EXCEEDED' => 'Dữ liệu vượt giới hạn đọc. Thu hẹp nguồn hoặc vùng đọc.',
            ];
            return new ConnectionReadResult($source['source_id'], match ($code) {
                'DISABLED' => 'disabled', 'NOT_CONFIGURED' => 'unconfigured', default => 'error',
            }, $code, $messages[$code], retryable: in_array($code, ['RATE_LIMITED', 'TIMEOUT', 'UPSTREAM_ERROR']));
        };
        if (! config('research.google_sync_enabled') || ! $source['active']) {
            return $error('DISABLED');
        }
        if (! $source['url']) {
            return $error('NOT_CONFIGURED');
        }
        if (! ($source['row_number_origin_known'] ?? true)) return $error('SCHEMA_MISMATCH');
        $response = app(ConnectionHttpClient::class)->get($source['url'], $deadline ?? microtime(true) + 30);
        if (isset($response['error'])) {
            return $error($response['error']);
        }
        $body = preg_replace('/^\xEF\xBB\xBF/', '', $response['body']);
        if (str_contains(strtolower($response['content_type']), 'html') || str_starts_with(ltrim($body), '<') || ! mb_check_encoding($body, 'UTF-8')) {
            return $error('INVALID_PAYLOAD');
        }
        try {
            [$headers, $rows, $rowNumbers] = $this->parseCsv($body, $source['header_row'] ?? 1);
            if (! in_array($source['columns']['timestamp'] ?? '', $headers, true)) {
                return $error('SCHEMA_MISMATCH');
            }
            foreach (['school', 'group'] as $field) {
                $values = array_unique(array_column($source['assignments'], $field));
                if (count($values) !== 1 && ! in_array($source['columns'][$field] ?? '', $headers, true)) {
                    return $error('SCHEMA_MISMATCH');
                }
                foreach ($rows as &$row) {
                    if (count($values) !== 1 && trim($row[$source['columns'][$field]] ?? '') === '') {
                        return $error('SCHEMA_MISMATCH');
                    }
                    if (count($values) === 1) {
                        $row['_source_'.$field] = reset($values);
                    }
                }
                unset($row);
            }
            return new ConnectionReadResult($source['source_id'], 'ok', null, 'Đọc Sheet thành công (CSV demo).', $headers, $rows, rowNumbers: $rowNumbers, payloadBytes: strlen($response['body']));
        } catch (InvalidArgumentException $exception) {
            return $error($exception->getMessage() === 'LIMIT_EXCEEDED' ? 'LIMIT_EXCEEDED' : 'SCHEMA_MISMATCH');
        }
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
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https' || strtolower($parts['host'] ?? '') !== 'docs.google.com'
            || isset($parts['user']) || isset($parts['pass']) || (isset($parts['port']) && $parts['port'] !== 443)) {
            throw new InvalidArgumentException('URL Google Sheets không hợp lệ');
        }

        parse_str($parts['query'] ?? '', $query);
        parse_str($parts['fragment'] ?? '', $fragment);
        $gid = $query['gid'] ?? $fragment['gid'] ?? '0';
        if (! is_string($gid) || ! preg_match('/^\d+$/', $gid)
            || (isset($query['range']) && ! is_string($query['range']))) {
            throw new InvalidArgumentException('Tab hoặc vùng Google Sheets không hợp lệ');
        }
        $gid = ltrim($gid, '0') ?: '0';
        $range = isset($query['range']) ? '&range='.rawurlencode($query['range']) : '';

        if (preg_match('~^/spreadsheets/d/e/([A-Za-z0-9_-]+)/pub$~', $parts['path'] ?? '', $matches)) {
            return "https://docs.google.com/spreadsheets/d/e/{$matches[1]}/pub?output=csv&gid={$gid}{$range}";
        }
        if (preg_match('~^/spreadsheets/d/([A-Za-z0-9_-]+)(?:/(?:edit|export))?/?$~', $parts['path'] ?? '', $matches)) {
            return "https://docs.google.com/spreadsheets/d/{$matches[1]}/export?format=csv&gid={$gid}{$range}";
        }

        throw new InvalidArgumentException('URL Google Sheets không hợp lệ');
    }

    private function parseCsv(string $csv, int $headerRow = 1): array
    {
        $stream = fopen('php://memory', 'w+');
        fwrite($stream, $csv);
        rewind($stream);
        $headers = fgetcsv($stream, 0, ',', '"', '');
        $rows = [];
        $rowNumbers = [];
        $rowNumber = $headerRow;

        if (! is_array($headers)) {
            fclose($stream);
            throw new InvalidArgumentException('Missing header');
        }

        $headers = array_map(fn ($header) => trim((string) $header), $headers);
        if (in_array('', $headers, true) || count(array_unique($headers)) !== count($headers) || array_filter($headers, fn ($h) => str_starts_with($h, '_source_'))) {
            fclose($stream);
            throw new InvalidArgumentException('Invalid header');
        }
        if (count($headers) > config('research.responses.max_columns', 100)) {
            fclose($stream);
            throw new InvalidArgumentException('LIMIT_EXCEEDED');
        }
        while (($values = fgetcsv($stream, 0, ',', '"', '')) !== false) {
            $rowNumber++;
            if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }
            if (count($values) !== count($headers)) {
                fclose($stream);
                throw new InvalidArgumentException('Invalid row width');
            }
            $rows[] = array_combine($headers, $values);
            $rowNumbers[] = $rowNumber;
            if (count($rows) > config('research.responses.max_rows', 10000)) {
                fclose($stream);
                throw new InvalidArgumentException('LIMIT_EXCEEDED');
            }
        }

        fclose($stream);
        return [$headers, $rows, $rowNumbers];
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
