<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrivacyHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        if ($request->is('join/*') || $request->is('s/*') || $request->is('admin/*') || $request->is('admin')) {
            $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        }
        return $response;
    }
}
