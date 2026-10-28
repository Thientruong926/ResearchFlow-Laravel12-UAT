<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\PrivacyHeaders;
use App\Http\Middleware\AdminSessionMiddleware;
return Application::configure(basePath: dirname(__DIR__))
 ->withRouting(web: __DIR__.'/../routes/google.php', commands: __DIR__.'/../routes/console.php', health: '/up')
 ->withMiddleware(function (Middleware $middleware): void { $middleware->alias(['admin.session'=>AdminSessionMiddleware::class]); $middleware->append(PrivacyHeaders::class); })
 ->withExceptions(function (Exceptions $exceptions): void { })
 ->create();
