<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Bounded GETs only. Never persist upstream bodies or exception messages. */
class ConnectionHttpClient
{
    public function get(string $url, float $deadline, bool $sheet = true): array
    {
        for ($attempt = 0; $attempt < 3; $attempt++) {
            if (microtime(true) >= $deadline) {
                return ['error' => 'TIMEOUT'];
            }
            $next = $url;
            $tooLarge = false;
            try {
                for ($redirect = 0; $redirect < 4; $redirect++) {
                    if (! $this->allowed($next, $sheet)) {
                        return ['error' => 'INVALID_PAYLOAD'];
                    }
                    $remaining = $deadline - microtime(true);
                    if ($remaining <= 0) {
                        return ['error' => 'TIMEOUT'];
                    }
                    $limit = (int) config('research.connections.max_bytes', 5242880);
                    $sink = fopen('php://memory', 'w+');
                    try {
                        $response = Http::connectTimeout(min(5, $remaining))->timeout(min(10, $remaining))
                            ->withOptions([
                                'allow_redirects' => false,
                                'sink' => $sink,
                                'on_headers' => static function ($response) use ($limit, &$tooLarge) {
                                    if ((int) $response->getHeaderLine('Content-Length') > $limit) {
                                        $tooLarge = true;
                                        throw new RuntimeException('Payload limit');
                                    }
                                },
                                'progress' => static function ($total, $downloaded) use ($limit, &$tooLarge) {
                                    if ($total > $limit || $downloaded > $limit) {
                                        $tooLarge = true;
                                        throw new RuntimeException('Payload limit');
                                    }
                                },
                            ])->get($next);
                        $body = $response->body();
                    } finally {
                        if (is_resource($sink)) {
                            fclose($sink);
                        }
                    }
                    if (strlen($body) > $limit) {
                        return ['error' => 'LIMIT_EXCEEDED'];
                    }
                    if ($response->redirect()) {
                        $next = $response->header('Location');
                        continue;
                    }
                    if ($response->successful()) {
                        return ['body' => $body, 'content_type' => $response->header('Content-Type')];
                    }
                    $code = match ($response->status()) {
                        401 => 'UNAUTHORIZED', 403 => 'FORBIDDEN', 404 => 'NOT_FOUND',
                        429 => 'RATE_LIMITED', default => 'UPSTREAM_ERROR',
                    };
                    if ($response->status() !== 429 && $response->status() < 500) {
                        return ['error' => $code];
                    }
                    $retryAfter = $response->header('Retry-After');
                    $delay = is_numeric($retryAfter) ? max(0, (float) $retryAfter) : max(0, (strtotime($retryAfter) ?: 0) - time());
                    break;
                }
                if ($response->redirect()) {
                    return ['error' => 'INVALID_PAYLOAD'];
                }
            } catch (ConnectionException) {
                if ($tooLarge) return ['error' => 'LIMIT_EXCEEDED'];
                $code = 'TIMEOUT';
                $delay = 0;
            } catch (\Throwable) {
                return ['error' => $tooLarge ? 'LIMIT_EXCEEDED' : 'INVALID_PAYLOAD'];
            }
            $delay = max($delay, (0.2 * (2 ** $attempt)) + random_int(0, 100) / 1000);
            if ($attempt === 2 || microtime(true) + $delay >= $deadline) {
                return ['error' => $code];
            }
            usleep((int) ($delay * 1000000));
        }
        return ['error' => 'UPSTREAM_ERROR'];
    }

    private function allowed(string $url, bool $sheet): bool
    {
        $p = parse_url($url);
        if (! is_array($p) || ($p['scheme'] ?? '') !== 'https' || isset($p['user']) || isset($p['pass']) || (isset($p['port']) && $p['port'] !== 443)) {
            return false;
        }
        $host = strtolower($p['host'] ?? '');
        $path = $p['path'] ?? '';
        if (! $sheet) {
            return $host === 'docs.google.com' && preg_match('~^/forms/d/e/[A-Za-z0-9_-]+/viewform$~', $path);
        }
        return ($host === 'docs.google.com' && str_starts_with($path, '/spreadsheets/'))
            || (preg_match('/^doc-[a-z0-9-]+\.googleusercontent\.com$/', $host) && str_starts_with($path, '/export/'));
    }
}
