<?php

namespace App\Services;

use App\Contracts\SurveyResponseReader;
use App\Data\SourceDefinition;
use App\Data\NormalizedRecord;

final class ResponseQueryService
{
    public function __construct(private SurveyResponseReader $reader, private ResponseNormalizer $normalizer) {}

    public function read(array $sources, array $filters): array
    {
        if ($filters['source'] !== '') {
            $sources = array_intersect_key($sources, [$filters['source'] => true]);
        }
        $records = $columns = $errors = [];
        $success = $bytes = 0;
        $deadline = microtime(true) + 30;
        foreach ($sources as $id => $source) {
            $result = $this->reader->read(new SourceDefinition($source), $deadline);
            if ($result->status !== 'ok') {
                if ($result->errorCode === 'LIMIT_EXCEEDED') $this->limit();
                $errors[$id] = $result->message;
                continue;
            }
            $bytes += $result->payloadBytes;
            if ($bytes > config('research.responses.max_bytes') || count($records) + count($result->records) > config('research.responses.max_rows')
                || count($columns) + count($result->headers) > config('research.responses.max_columns')) {
                $this->limit();
            }
            try {
                $normalized = $this->normalizer->normalize($source, $result);
            } catch (\InvalidArgumentException) {
                $errors[$id] = 'Nguồn chưa cung cấp số dòng/schema hợp lệ. Kiểm tra adapter.';
                continue;
            }
            foreach ($result->headers as $index => $label) {
                $columns[ResponseNormalizer::columnKey($id, $index, $label)] = $label.' ['.$id.':'.($index + 1).']';
            }
            array_push($records, ...$normalized);
            $success++;
            unset($result, $normalized);
        }
        return ['records' => $records, 'columns' => $columns, 'errors' => $errors, 'success' => $success, 'total_sources' => count($sources), 'checked_at' => now(), 'sources' => $sources];
    }

    public function filter(array $records, array $filters): array
    {
        $records = array_values(array_filter($records, function (NormalizedRecord $r) use ($filters) {
            $day = $r->submittedAt?->format('Y-m-d');
            $text = $filters['q'] === '' ? '' : mb_strtolower(implode(' ', [$r->sourceId, $r->schoolCode ?? '', $r->groupCode ?? '', config('research.groups')[$r->groupCode] ?? '', $r->submittedAt?->format('d/m/Y H:i:s T') ?? '', ...array_values($r->answers)]));
            return ($filters['q'] === '' || str_contains($text, mb_strtolower($filters['q'])))
                && ($filters['school'] === '' || $r->schoolCode === $filters['school'])
                && ($filters['group'] === '' || $r->groupCode === $filters['group'])
                && ($filters['source'] === '' || $r->sourceId === $filters['source'])
                && ($filters['date_from'] === '' || ($day && $day >= $filters['date_from']))
                && ($filters['date_to'] === '' || ($day && $day <= $filters['date_to']));
        }));
        usort($records, function (NormalizedRecord $a, NormalizedRecord $b) use ($filters) {
            if ($a->submittedAt === null || $b->submittedAt === null) {
                $date = ($a->submittedAt === null) <=> ($b->submittedAt === null);
            } else {
                $date = $a->submittedAt <=> $b->submittedAt;
                if ($filters['sort'] === 'newest') $date = -$date;
            }
            return $date ?: (strcmp($a->sourceId, $b->sourceId) ?: $a->sourceRowNumber <=> $b->sourceRowNumber);
        });
        return $records;
    }

    private function limit(): never
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->view('admin.response_error', ['message' => 'Dữ liệu vượt giới hạn đọc/xuất. Chọn một nguồn hoặc thu hẹp vùng đọc rồi thử lại.'], 413));
    }
}
