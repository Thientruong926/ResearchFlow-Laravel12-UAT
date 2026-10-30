<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ResearchAccess
{
    public function handle(Request $request, Closure $next, string $action)
    {
        $roles = match ($action) {
            'configure', 'create_link' => ['IT_ADMIN'],
            'registry', 'toggle', 'revoke' => ['IT_ADMIN', 'NCS'],
            'review' => ['NCS', 'qc_reviewer'],
            'export' => ['NCS', 'ANALYST', 'data_manager'],
            'audit' => ['NCS', 'IT_ADMIN', 'AUDITOR'],
            'site_status' => ['SITE_COORDINATOR'],
            default => [],
        };
        // Existing owner/researcher accounts retain their original A–E permissions.
        abort_unless($request->user()?->hasRole('owner', 'researcher', ...$roles), 403);
        return $next($request);
    }
}
