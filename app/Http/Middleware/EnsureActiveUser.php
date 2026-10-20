<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user() && $request->user()->active, 403, 'Tài khoản đã ngừng hoạt động.');
        return $next($request);
    }
}
