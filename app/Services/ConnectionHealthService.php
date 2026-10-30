<?php

namespace App\Services;

use Carbon\CarbonImmutable;

class ConnectionHealthService
{
    public function __construct(private ConnectionMetadataStore $store, private \App\Contracts\SurveyResponseReader $sheets) {}

    public function snapshot(array $source): array
    {
        $data = $this->store->read($source['source_id']);
        $changed = isset($data['revision']) && $data['revision'] !== $source['revision'];
        $data['configuration_changed'] = $changed;
        $data['stale'] = isset($data['last_checked_at']) && CarbonImmutable::parse($data['last_checked_at'])->addSeconds(config('research.connections.stale_seconds', 300))->isPast();
        if ($changed) {
            $data['status'] = 'unchecked';
            $data['user_message'] = 'Cấu hình đã thay đổi — cần kiểm tra lại.';
            $data['forms'] = [];
            $data['verification'] = ['status' => 'changed'];
        }
        if (! config('research.google_sync_enabled') || ! $source['active']) {
            $data['status'] = 'disabled';
        } elseif (! $source['url']) {
            $data['status'] = 'unconfigured';
        }
        return $data + ['status' => 'unchecked', 'forms' => [], 'verification' => ['status' => 'unverified']];
    }

    public function check(array $source): array
    {
        return $this->store->update($source['source_id'], function (array $previous) use ($source) {
            if (isset($previous['last_checked_at']) && CarbonImmutable::parse($previous['last_checked_at'])->addSeconds(30)->isFuture()) {
                abort(429, 'Mỗi nguồn chỉ được kiểm tra một lần trong 30 giây.');
            }
            $start = microtime(true);
            $checkedAt = CarbonImmutable::now('UTC')->toIso8601String();
            $result = $this->sheets->read(new \App\Data\SourceDefinition($source), $start + 30);
            $readSucceededAt = $result->status === 'ok' ? CarbonImmutable::now('UTC')->toIso8601String() : null;
            $forms = [];
            $byForm = [];
            foreach ($source['assignments'] as $assignment) {
                $form = $assignment['form_id'] ?? '';
                if (! isset($byForm[$form])) {
                    if ($result->status === 'disabled') {
                        $byForm[$form] = ['ok' => false, 'message' => 'Tắt'];
                    } else {
                        $byForm[$form] = app(GoogleFormsService::class)->connectionStatus($assignment, $start + 30);
                    }
                }
                $forms[$assignment['id']] = $byForm[$form];
            }
            return $result->metadata() + [
                'revision' => $source['revision'],
                'last_checked_at' => $checkedAt,
                'last_success_at' => $readSucceededAt ?? ($previous['last_success_at'] ?? null),
                'last_success_revision' => $result->status === 'ok' ? $source['revision'] : ($previous['last_success_revision'] ?? null),
                'duration_ms' => (int) round((microtime(true) - $start) * 1000),
                'forms' => $forms,
                'verification' => ($previous['revision'] ?? null) === $source['revision'] ? ($previous['verification'] ?? ['status' => 'unverified']) : ['status' => isset($previous['revision']) ? 'changed' : 'unverified'],
            ];
        });
    }

    public function verify(array $source, string $revision, string $status): void
    {
        abort_unless(hash_equals($source['revision'], $revision), 409, 'Cấu hình đã thay đổi. Tải lại trang.');
        $this->store->update($source['source_id'], function (array $data) use ($source, $status) {
            abort_unless(($data['revision'] ?? '') === $source['revision'], 409, 'Cần kiểm tra nguồn với cấu hình hiện tại trước.');
            if ($status === 'verified') {
                abort_unless(($data['status'] ?? '') === 'ok', 409, 'Cần đọc Sheet thành công trước khi xác minh.');
            }
            $data['verification'] = ['status' => $status, 'revision' => $source['revision'], 'at' => CarbonImmutable::now('UTC')->toIso8601String()];
            return $data;
        });
    }
}
