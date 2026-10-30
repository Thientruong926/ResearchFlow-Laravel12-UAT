<?php

namespace App\Services;

final class ConnectionSources
{
    public function all(array $settings): array
    {
        $sources = [];
        foreach ($settings['assignments'] ?? [] as $assignment) {
            $raw = trim($assignment['sheet_csv_url'] ?? '');
            try {
                $url = app(GoogleSheetsService::class)->csvUrl($raw);
            } catch (\InvalidArgumentException) {
                $url = null;
            }
            $id = 'src_'.substr(hash('sha256', $url ?? ($raw ?: 'missing:'.($assignment['id'] ?? ''))), 0, 24);
            $sources[$id] ??= ['source_id' => $id, 'url' => $url, 'transport' => 'demo_csv', 'assignments' => [], 'columns' => $settings['sheet_columns'] ?? []];
            $sources[$id]['assignments'][] = $assignment;
        }
        foreach ($sources as &$source) {
            $parts = $source['url'] ? parse_url($source['url']) : [];
            parse_str($parts['query'] ?? '', $query);
            preg_match('~/spreadsheets/d/(?:e/)?([A-Za-z0-9_-]+)~', $parts['path'] ?? '', $matches);
            $published = str_starts_with($parts['path'] ?? '', '/spreadsheets/d/e/');
            $source['spreadsheet_id'] = $published ? null : ($matches[1] ?? null);
            $source['published_id'] = $published ? ($matches[1] ?? null) : null;
            $source['sheet_id'] = $source['url'] ? ($query['gid'] ?? '0') : null;
            $source['range'] = $query['range'] ?? null;
            $source['row_number_origin_known'] = ! $source['range'] || (bool) preg_match('/^(?:[^!]+!)?(?:\$?[A-Z]+\$?[1-9]\d*(?::\$?[A-Z]+\$?[1-9]\d*)?|\$?[A-Z]+:\$?[A-Z]+|[1-9]\d*:[1-9]\d*)$/iD', $source['range']);
            $source['header_row'] = 1;
            if ($source['range'] && preg_match('/(?:^|!)(?:\$?[A-Z]+\$?)?(\d+)(?::|$)/i', $source['range'], $rangeMatch)) {
                $source['header_row'] = (int) $rangeMatch[1];
            }
            $source['timestamp_format'] = $settings['source_defaults']['timestamp_format'] ?? 'd/m/Y H:i:s';
            $source['timezone'] = $settings['source_defaults']['timezone'] ?? 'Asia/Ho_Chi_Minh';
            usort($source['assignments'], fn ($a, $b) => strcmp($a['id'], $b['id']));
            $source['assignment_ids'] = array_column($source['assignments'], 'id');
            $source['active'] = collect($source['assignments'])->contains(fn ($a) => (bool) ($a['active'] ?? false));
            $source['revision'] = hash('sha256', json_encode([$source, (bool) config('research.google_sync_enabled')], JSON_THROW_ON_ERROR));
        }
        return $sources;
    }
}
