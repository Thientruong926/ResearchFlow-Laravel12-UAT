<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\PrivacyHeaders;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\AuditSensitiveRead;
return Application::configure(basePath: dirname(__DIR__))
 ->withRouting(web: __DIR__.'/../routes/web.php', commands: __DIR__.'/../routes/console.php', health: '/up')
 ->withMiddleware(function (Middleware $middleware): void { $middleware->alias(['role'=>RoleMiddleware::class,'active'=>EnsureActiveUser::class,'audit_read'=>AuditSensitiveRead::class]); $middleware->append(PrivacyHeaders::class); })
 ->withExceptions(function (Exceptions $exceptions): void { })
 ->create();
