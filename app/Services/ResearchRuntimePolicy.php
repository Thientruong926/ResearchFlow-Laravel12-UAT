<?php

namespace App\Services;

use LogicException;

final class ResearchRuntimePolicy
{
    public function assertValid(): void
    {
        $allowed = app()->environment('testing') ? ['file', 'array'] : ['file'];
        $cache = config('cache.default');
        $limiter = config('cache.limiter') ?: $cache;
        foreach ([config('session.driver'), config("cache.stores.{$cache}.driver"), config("cache.stores.{$limiter}.driver")] as $driver) {
            if (! in_array($driver, $allowed, true)) {
                throw new LogicException('ResearchFlow requires file session/cache (array allowed only in testing). Check SESSION_DRIVER and CACHE_STORE.');
            }
        }
        $queue = config('queue.default');
        if (config("queue.connections.{$queue}.driver") !== 'sync' || config('queue.failed.driver') !== 'null') {
            throw new LogicException('ResearchFlow requires sync queue and null failed-job storage.');
        }
    }
}
