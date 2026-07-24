<?php

use App\Http\Middleware\AdminMiddleware;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Lottery;
use Nette\Schema\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

// use Illuminate\Http\Request;
// use Throwable;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function ($middleware) {
        $middleware->alias([
            'admin' => AdminMiddleware::class,
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions): void {
        // $exceptions->report(function (Throwable $e) {
        //     Log::channel('exception_logs')->error('Application Exception Encounted', [
        //         'message' => $e->getMessage(),
        //         'file' => $e->getFile(),
        //         'line' => $e->getLine(),
        //         'trace' => $e->getTraceAsString(),
        //     ]);
        // });
        // $exceptions->level(
        //     PDOException::class,
        //     'critical'
        // );

        // $exceptions->context(function () {
        //     return [
        //         'server' => gethostname(),
        //         'app_name' => config('app.name'),
        //         'environment' => app()->environment(),
        //         'php_version' => PHP_VERSION,
        //     ];
        // });

        // $exceptions->render(function (Throwable $e,) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'Validation failed, please try again.',
        //         'errors' => $e->getMessage(),
        //     ], 422);
        // });

        // $exceptions->shouldRenderJsonWhen(function (Request $request, Throwable $e) {
        //     return $request->is('api/*') || $request->expectsJson();
        // });

        // $exceptions->report(function (\Throwable $e) {
        //     Log::error($e->getMessage());
        // });

        // $exceptions->dontReport(ValidationException::class);

        // $exceptions->dontReportWhen(function ($e) {
        //     return $e->getCode() == 404;
        // });

        // $exceptions->dontReportDuplicates();

        // $exceptions->dontFlash([
        //     'password'
        // ]);

        // $exceptions->shouldRenderJsonWhen(
        //     fn($request) => $request->is('api/*')
        // );

        // $exceptions->stopIgnoring(
        //     HttpException::class
        // );

        // $exceptions->truncateRequestExceptionsAt(100);

        // $exceptions->dontTruncateRequestExceptions();

        // $exceptions->respond(function ($response) {
        //     logger('RESPOND CALLED');

        //     return $response->header(
        //         // 'X-App',
        //         // 'Laravel11',
        //         'API-Version',
        //         'v1',
        //     );
        // });

        // $exceptions->throttle(function (Throwable $e) {
        //     return Limit::perMinute(5)
        //         ->by(get_class($e));
        // });

        // $exceptions->throttle(function (Throwable $e) {
        //     return Lottery::odds(1, 5)
        //         ->winner(function () {
        //             Log::info('Winner');
        //         })
        //         ->loser(function () {
        //             Log::info('Loser');
        //         });
        //     // ->choose($winner, $loser);
        //     // ->runCallback();
        // });

    })->create();
