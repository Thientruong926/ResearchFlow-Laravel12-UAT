<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminSessionMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->get('admin_authenticated') !== true) {
            return redirect()->route('login')->withErrors(['email' => 'Vui lòng đăng nhập khu vực quản lý.']);
        }

        return $next($request);
    }
}
