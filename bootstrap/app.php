<?php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\PrivacyHeaders;
use App\Http\Middleware\AdminSessionMiddleware;
return Application::configure(basePath: dirname(__DIR__))
 ->withProviders([\App\Providers\ResearchServiceProvider::class])
 ->withRouting(web: __DIR__.'/../routes/google.php', commands: __DIR__.'/../routes/console.php', health: '/up')
 ->withMiddleware(function (Middleware $middleware): void { $middleware->alias(['admin.session'=>AdminSessionMiddleware::class]); $middleware->append(PrivacyHeaders::class); })
 ->withExceptions(function (Exceptions $exceptions): void {
     $exceptions->dontReport([\App\Exceptions\SettingsStorageException::class]);
     $exceptions->render(function (\App\Exceptions\SettingsStorageException $error, \Illuminate\Http\Request $request) {
         return response()->view('admin.settings_error', ['message' => $request->is('admin/*') ? $error->getMessage() : 'Nội dung nghiên cứu tạm thời không khả dụng. Vui lòng thử lại sau.'], 503);
     });
     $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\ConflictHttpException $error, \Illuminate\Http\Request $request) {
         if ($request->is('admin/settings/*')) return response()->view('admin.settings_error', ['message'=>$error->getMessage()], 409);
     });
 })
 ->create();
