<?php

namespace App\Services;

use App\Data\NormalizedRecord;
use Illuminate\Support\Facades\Crypt;

final class ResponseReference
{
    public function issue(NormalizedRecord $record, string $revision): string
    {
        return Crypt::encryptString(json_encode([
            'source' => $record->sourceId, 'row' => $record->sourceRowNumber,
            'revision' => $revision, 'hash' => $this->fingerprint($record),
            'expires' => now()->timestamp + config('research.responses.reference_ttl'),
        ], JSON_THROW_ON_ERROR));
    }

    public function decode(string $token): array
    {
        try {
            $data = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($data) || ! is_string($data['source'] ?? null) || ! is_int($data['row'] ?? null)
                || ! is_string($data['revision'] ?? null) || ! is_string($data['hash'] ?? null) || ! is_int($data['expires'] ?? null)) {
                throw new \RuntimeException();
            }
        } catch (\Throwable) {
            abort(400, 'Liên kết phản hồi không hợp lệ. Tải lại danh sách.');
        }
        abort_if($data['expires'] <= now()->timestamp, 410, 'Liên kết phản hồi đã hết hạn. Tải lại danh sách.');
        return $data;
    }

    public function fingerprint(NormalizedRecord $record): string
    {
        return hash_hmac('sha256', json_encode([
            $record->sourceId, $record->sourceRowNumber, $record->submittedAt?->format(DATE_ATOM),
            $record->schoolCode, $record->groupCode, $record->answers, $record->warnings,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE), Crypt::getKey());
    }
}
