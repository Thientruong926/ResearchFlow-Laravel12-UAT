<?php

namespace App\Services;

use App\Data\NormalizedRecord;
use DateTimeImmutable;
use DateTimeZone;

final class ResponseNormalizer
{
    public function normalize(array $source, ConnectionReadResult $read): array
    {
        if ($read->sourceId !== $source['source_id'] || $read->headers === [] || count(array_unique($read->headers)) !== count($read->headers)
            || in_array('', $read->headers, true) || ! in_array($source['columns']['timestamp'], $read->headers, true)) {
            throw new \InvalidArgumentException('Schema mismatch');
        }
        if (count($read->records) !== count($read->rowNumbers) || count(array_unique($read->rowNumbers)) !== count($read->rowNumbers)) {
            throw new \InvalidArgumentException('Adapter chưa cung cấp số dòng chính xác.');
        }
        $records = [];
        $zone = new DateTimeZone($source['timezone']);
        $keys = [];
        foreach ($read->headers as $column => $label) $keys[$label] = self::columnKey($source['source_id'], $column, $label);
        foreach ($read->records as $index => $row) {
            if (! is_int($read->rowNumbers[$index]) || $read->rowNumbers[$index] <= $source['header_row']) throw new \InvalidArgumentException('Invalid row number');
            $answers = [];
            foreach ($read->headers as $column => $label) {
                if (! array_key_exists($label, $row) || (! is_scalar($row[$label]) && $row[$label] !== null)) throw new \InvalidArgumentException('Invalid cell');
                $answers[$keys[$label]] = (string) ($row[$label] ?? '');
            }
            $rawDate = trim((string) ($row[$source['columns']['timestamp']] ?? ''));
            try {
                $date = DateTimeImmutable::createFromFormat('!'.$source['timestamp_format'], $rawDate, $zone);
                $errors = DateTimeImmutable::getLastErrors();
                if (! $date || ($errors && ($errors['warning_count'] || $errors['error_count'])) || $date->format($source['timestamp_format']) !== $rawDate) {
                    $date = null;
                }
            } catch (\ValueError) {
                $date = null;
            }
            $records[] = new NormalizedRecord(
                $source['source_id'], $read->rowNumbers[$index], $date,
                $this->category($row, $source, 'school'), $this->category($row, $source, 'group'),
                $answers, $date ? [] : ['Ngày gửi thiếu hoặc không đúng định dạng nguồn.'],
            );
        }
        return $records;
    }

    private function category(array $row, array $source, string $field): ?string
    {
        $value = trim((string) ($row[$source['columns'][$field]] ?? ''));
        if ($value === '') {
            $candidates = array_unique(array_column($source['assignments'], $field));
            if (count($candidates) !== 1) throw new \InvalidArgumentException('Ambiguous mapping');
            $value = (string) reset($candidates);
        }
        return $value !== '' ? $value : null;
    }

    public static function columnKey(string $sourceId, int $index, string $label): string
    {
        return $sourceId.':'.hash('sha256', $index.':'.$label);
    }
}
