<?php

namespace App\Http\Middleware;

use App\Services\ApiLogger;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

// class ApiLogMiddleware
// {
//     public function handle($request, Closure $next)
//     {
//         // Request Log
//         Log::channel('api')->info('API Request', [
//             'url' => $request->fullUrl(),
//             'method' => $request->method(),
//             'ip' => $request->ip(),
//             'headers' => $request->headers->all(),
//             'request' => $request->all(),
//         ]);

//         try {
//             $response = $next($request);

//             // Response Log
//             Log::channel('api')->info('API Response', [
//                 'status' => $response->status(),
//                 'response' => json_decode($response->getContent(), true),
//             ]);

//             return $response;

//         } catch (\Throwable $e) {
//             // Exception Log
//             Log::channel('api')->error('API Exception Occurred', [
//                 'message' => $e->getMessage(),
//                 'line' => $e->getLine(),
//                 'file' => $e->getFile(),
//                 'trace' => $e->getTraceAsString(),
//             ]);

//             throw $e;
//         }
//     }
// }

class ApiLogMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle($request, Closure $next)
    {
        // Start timer
        $startTime = microtime(true);

        // Log incoming request
        $requestId = ApiLogger::request($request);

        try {

            // Continue request
            $response = $next($request);

            // Log response
            ApiLogger::response(
                $requestId,
                $response,
                $startTime
            );

            return $response;

        } catch (Throwable $e) {

            // Log exception
            ApiLogger::exception(
                $requestId,
                $request,
                $e
            );

            throw $e;
        }
    }
}
