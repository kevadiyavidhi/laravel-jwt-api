<?php

namespace App\Http\Controllers\Api\Mozio;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class GetSessionClockController extends Controller
{
    public function getSessionClock(string $searchId)
    {
        try {
            $cacheKey = "mozio_poll_{$searchId}";
            $cachedPayload = Cache::get($cacheKey);

            $currentTime = now();

            // Check if cache doesn't exist OR it uses the old structure missing our timeline keys
            if (! $cachedPayload || ! isset($cachedPayload['created_at']) || ! isset($cachedPayload['expire_at'])) {
                $clockDetails = [
                    'expire' => true,
                    'created_time' => '',
                    'current_time' => $currentTime->toDateTimeString(),
                    'expire_at_time' => '',
                    'remaining' => '00:00',
                    'status' => 'expired',
                    'message' => 'Search session expired.',
                ];
            } else {
                // Safe to run calculations now that keys are guaranteed to exist
                $createdAt = Carbon::parse($cachedPayload['created_at']);
                $expireAt = Carbon::parse($cachedPayload['expire_at']);

                $isExpired = $currentTime->greaterThanOrEqualTo($expireAt);
                $remainingSeconds = max(0, $currentTime->diffInSeconds($expireAt, false));

                $minutes = str_pad(floor($remainingSeconds / 60), 2, '0', STR_PAD_LEFT);
                $seconds = str_pad($remainingSeconds % 60, 2, '0', STR_PAD_LEFT);
                $remainingString = "{$minutes}:{$seconds}";

                $clockDetails = [
                    'expire' => $isExpired,
                    'created_time' => $createdAt->toDateTimeString(),
                    'current_time' => $currentTime->toDateTimeString(),
                    'expire_at_time' => $expireAt->toDateTimeString(),
                    'remaining' => $remainingString,
                    'status' => '',
                    'message' => '',
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Session clock detail.',
                'data' => [
                    [
                        $searchId => $clockDetails,
                    ],
                ],
                'errors' => [],
            ]);

        } catch (Throwable $e) {
            Log::error("Failed fetching session clock for ID {$searchId}: ".$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve session clock details.',
                'data' => [],
                'errors' => [config('app.debug') ? $e->getMessage() : 'Server Error'],
            ], 500);
        }
    }

    public function getSelectedAmenitiesSessionClock(string $searchId, string $resultId)
    {
        try {
            $cacheKey = "selected_amenities_{$searchId}_{$resultId}";
            $cachedPayload = Cache::get($cacheKey);

            $currentTime = now();

            if (! $cachedPayload || ! isset($cachedPayload['created_at']) || ! isset($cachedPayload['expire_at'])
            ) {
                $clockDetails = [
                    'expire' => true,
                    'created_time' => '',
                    'current_time' => $currentTime->toDateTimeString(),
                    'expire_at_time' => '',
                    'remaining' => '00:00',
                    'status' => 'expired',
                    'message' => 'Selected amenities session expired.',
                ];
            } else {
                $createdAt = Carbon::parse($cachedPayload['created_at']);
                $expireAt = Carbon::parse($cachedPayload['expire_at']);

                $isExpired = $currentTime->greaterThanOrEqualTo($expireAt);

                $remainingSeconds = max(0, $currentTime->diffInSeconds($expireAt, false));

                $minutes = str_pad(floor($remainingSeconds / 60), 2, '0', STR_PAD_LEFT);
                $seconds = str_pad($remainingSeconds % 60, 2, '0', STR_PAD_LEFT);

                $clockDetails = [
                    'expire' => $isExpired,
                    'created_time' => $createdAt->toDateTimeString(),
                    'current_time' => $currentTime->toDateTimeString(),
                    'expire_at_time' => $expireAt->toDateTimeString(),
                    'remaining' => "{$minutes}:{$seconds}",
                    'status' => $isExpired ? 'expired' : '',
                    'message' => $isExpired ? 'Selected amenities session expired.' : '',
                ];
            }

            return response()->json([
                'success' => true,
                'message' => 'Session clock detail.',
                'data' => [
                    [
                        $cacheKey => $clockDetails,
                    ],
                ],
                'errors' => [],
            ]);

        } catch (Throwable $e) {
            Log::error("Failed fetching selected amenities session clock for {$searchId} / {$resultId}: ".$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve session clock details.',
                'data' => [],
                'errors' => [
                    config('app.debug') ? $e->getMessage() : 'Server Error',
                ],
            ], 500);
        }
    }
}
