<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GoogleFormsService
{
    public function submit(array $assignment, array $answers): void
    {
        abort_unless(config('research.google_sync_enabled'), 503, 'Đồng bộ Google đang tắt.');

        $formId = $this->formId((string) ($assignment['form_id'] ?? ''));
        $entries = $assignment['entries'] ?? [];
        $payload = [];

        foreach ($answers as $key => $value) {
            $entryId = preg_replace('/\D/', '', (string) ($entries[$key] ?? ''));
            if ($entryId !== '' && $value !== null && $value !== '') {
                $payload['entry.'.$entryId] = $value;
            }
        }

        if ($formId === '' || $payload === []) {
            throw new RuntimeException('Phiếu Google Forms chưa được cấu hình đầy đủ.');
        }

        $this->post($formId, $payload);
    }

    public function submitImported(array $assignment, string $school, string $group, array $answers): void
    {
        abort_unless(config('research.google_sync_enabled'), 503, 'Đồng bộ Google đang tắt.');

        $formId = $this->formId((string) ($assignment['form_id'] ?? ''));
        $payload = [];
        foreach (['school' => $school, 'group' => $group] as $key => $value) {
            $entryId = preg_replace('/\D/', '', (string) ($assignment['entries'][$key] ?? ''));
            if ($entryId !== '') {
                $payload['entry.'.$entryId] = $value;
            }
        }

        foreach (($assignment['questions'] ?? []) as $question) {
            $id = (string) ($question['id'] ?? '');
            $entryId = (string) ($question['entry_id'] ?? '');
            if (! preg_match('/^[A-Za-z0-9_-]{1,100}$/', $entryId) || ! array_key_exists($id, $answers)) {
                continue;
            }
            $value = $answers[$id];
            if ($value !== null && $value !== '' && $value !== []) {
                $payload['entry.'.$entryId] = $value;
            }
        }

        if ($formId === '' || $payload === []) {
            throw new RuntimeException('Phiếu Google Forms chưa được đồng bộ đầy đủ.');
        }

        $this->post($formId, $payload);
    }

    public function connectionStatus(array $assignment, ?float $deadline = null): array
    {
        if (! config('research.google_sync_enabled')) {
            return ['ok' => false, 'message' => 'Đồng bộ đang tắt'];
        }

        $formId = $this->formId((string) ($assignment['form_id'] ?? ''));
        if ($formId === '') {
            return ['ok' => false, 'message' => 'Chưa cấu hình Form ID'];
        }

        $response = app(ConnectionHttpClient::class)->get("https://docs.google.com/forms/d/e/{$formId}/viewform", $deadline ?? microtime(true) + 30, false);
        return isset($response['error'])
            ? ['ok' => false, 'message' => 'Không truy cập được trang Form: '.$response['error']]
            : ['ok' => true, 'message' => 'Trang Form truy cập được; chưa chứng minh nhận phản hồi.'];
    }

    private function formId(string $value): string
    {
        if (preg_match('~/forms/d/e/([^/]+)~', $value, $matches)
            || preg_match('~/forms/d/([^/]+)~', $value, $matches)) {
            return $matches[1];
        }

        return preg_replace('/[^A-Za-z0-9_-]/', '', $value) ?? '';
    }

    private function post(string $formId, array $payload): void
    {
        try {
            $request = Http::timeout(15)->withHeaders(['User-Agent' => 'ResearchFlow/1.0']);
            $hasMultipleValues = collect($payload)->contains(fn ($value) => is_array($value));
            if ($hasMultipleValues) {
                $pairs = [];
                foreach ($payload as $key => $value) {
                    foreach ((array) $value as $item) {
                        $pairs[] = rawurlencode((string) $key).'='.rawurlencode((string) $item);
                    }
                }
                $response = $request
                    ->withBody(implode('&', $pairs), 'application/x-www-form-urlencoded')
                    ->post("https://docs.google.com/forms/d/e/{$formId}/formResponse");
            } else {
                $response = $request->asForm()
                    ->post("https://docs.google.com/forms/d/e/{$formId}/formResponse", $payload);
            }
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Không thể kết nối Google Forms. Vui lòng thử lại khi có mạng.', 0, $exception);
        }

        if ($response->status() < 200 || $response->status() >= 400) {
            throw new RuntimeException('Google Forms từ chối câu trả lời. Kiểm tra Form ID, entry ID và trạng thái nhận phản hồi.');
        }
    }
}
