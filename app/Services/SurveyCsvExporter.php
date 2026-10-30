<?php

namespace App\Services;

final class SurveyCsvExporter
{
    public function download(array $records, array $columns)
    {
        return response()->streamDownload(function () use ($records, $columns) {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            $this->row($stream, ['Nguồn', 'Thời gian', 'Mã trường', 'Nhóm', 'Cảnh báo', ...array_values($columns)]);
            foreach ($records as $record) {
                $this->row($stream, [$record->sourceId, $record->submittedAt?->format(DATE_ATOM) ?? '', $record->schoolCode ?? '', $record->groupCode ?? '', implode('; ', $record->warnings),
                    ...array_map(fn ($key) => $record->answers[$key] ?? '', array_keys($columns))]);
            }
            fclose($stream);
        }, 'survey_responses_'.now()->format('Ymd_His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    private function row($stream, array $values): void
    {
        fputcsv($stream, array_map($this->safeCell(...), $values), ',', '"', '');
    }

    public function safeCell(string $value): string
    {
        // No numeric schema has been declared, so negative-looking strings are text too.
        return preg_match('/^[\p{Z}\x00-\x20]*[=+@\-\t\r\n]/u', $value) ? "'".$value : $value;
    }
}
