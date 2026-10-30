<?php

namespace App\Services;

/** Response payload lives only in request memory; metadata() is safe to persist. */
final class ConnectionReadResult
{
    public string $checkedAt;
    public ?string $lastSuccessAt;

    public function __construct(
        public string $sourceId,
        public string $status,
        public ?string $errorCode,
        public string $message,
        public array $headers = [],
        public array $records = [],
        public bool $retryable = false,
        public array $rowNumbers = [],
        public int $payloadBytes = 0,
    ) {
        $this->checkedAt = \Carbon\CarbonImmutable::now('UTC')->toIso8601String();
        $this->lastSuccessAt = $status === 'ok' ? $this->checkedAt : null;
    }

    public function metadata(): array
    {
        return [
            'source_id' => $this->sourceId,
            'status' => $this->status,
            'error_code' => $this->errorCode,
            'user_message' => $this->message,
            'retryable' => $this->retryable,
            'row_count' => $this->status === 'ok' ? count($this->records) : null,
            'schema_version' => 1,
        ];
    }
}
