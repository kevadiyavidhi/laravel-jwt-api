<?php

namespace App\Http\Controllers\Api\Mozio;

use App\Http\Requests\AmenitiesRequest;
use App\Logging\ApiLogger;
use App\Services\MozioService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Throwable;

class AmenitiesController extends BaseController
{
    protected MozioService $mozio;

    protected ApiLogger $logger;

    public function __construct(MozioService $mozio)
    {
        $this->mozio = $mozio;
        $this->logger = new ApiLogger;
        $this->middleware('auth:api', [
            'only' => [
                'getRideAmenities', 'selectedAmenities',
                'addAmenity', 'removeAmenity',
                'selectedAmenitiesSummary', 'pricing',
            ],
        ]);
    }

    public function getRideAmenities(AmenitiesRequest $request)
    {
        try {
            $user = auth('api')->user();

            $this->logger->setContext(['user_id' => $user->id, 'search_id' => $request->search_id]);
            $this->logger->logRequest('Get Ride Amenities', $request->all());

            $searchId = $request->search_id;
            $resultId = $request->result_id;

            $cachedData = Cache::get("mozio_poll_{$searchId}");

            if (! $cachedData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Search session expired.',
                ], 404);
            }

            $ride = collect($cachedData['data']['data'])
                ->firstWhere('result_id', $resultId);

            if (! $ride) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ride not found.',
                ], 404);
            }

            $amenities = data_get(
                $ride,
                'steps.0.details.amenities',
                []
            );

            $response = [
                'success' => true,
                'message' => 'Amenities fetched successfully.',
                'data' => [
                    'result_id' => $resultId,
                    'amenities' => $amenities,
                ],
            ];

            $this->logger->setContext([
                'user_id' => $user->id,
                'search_id' => $searchId,
            ]);

            $this->logger->logResponse('Get Ride Amenities', $response);

            return response()->json($response);

        } catch (Throwable $e) {
            $user = auth('api')->user();

            $this->logger->logException('Get Ride Amenities', $e, [
                'user_id' => $user->id,
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'search_id' => $searchId,
                'payload' => $request->all(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong.',
                'error' => config('app.debug')
                    ? $e->getMessage()
                    : 'Internal Server Error',
            ], 500);
        }
    }

    public function selectedAmenities(AmenitiesRequest $request)
    {
        try {
            $user = auth('api')->user();

            $this->logger->setContext(['user_id' => $user->id, 'search_id' => $request->search_id]);
            $this->logger->logRequest('Selected Amenities', $request->all());

            $searchId = $request->search_id;
            $resultId = $request->result_id;

            $cachedData = Cache::get(
                "mozio_poll_{$searchId}"
            );

            if (! $cachedData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Search session expired.',
                ], 404);
            }

            $ride = collect(
                $cachedData['data']['data']
            )->firstWhere(
                'result_id',
                $resultId
            );

            if (! $ride) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ride not found.',
                ], 404);
            }

            $cacheKey = "selected_amenities_{$searchId}_{$resultId}";

            if (! Cache::has($cacheKey)) {
                $createdAt = now();
                $expireAt = $createdAt->copy()->addMinute(20000);

                Cache::put($cacheKey, $cachePayload = [
                    'created_at' => $createdAt->toDateTimeString(),
                    'expire_at' => $expireAt->toDateTimeString(),
                    'selected_amenities' => [],
                ], $expireAt);
            }

            $response = [
                'success' => true,
                'message' => 'Selected amenities retrieved successfully.',
                'data' => [
                    'selectedAmenities' => Cache::get($cacheKey, []),
                    'total_price' => $ride['total_price'],
                ],
                'errors' => [],
            ];

            $this->logger->setContext([
                'user_id' => $user->id,
                'search_id' => $searchId,
            ]);

            $this->logger->logResponse('Selected Amenities', $response);

            return response()->json($response);

        } catch (Throwable $e) {
            $user = auth('api')->user();

            $this->logger->logException('Selected Amenities', $e, [
                'user_id' => $user->id,
                'search_id' => $searchId,
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'payload' => $request->all(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function addAmenity(AmenitiesRequest $request)
    {
        try {
            $user = auth('api')->user();

            $this->logger->setContext(['user_id' => $user->id, 'search_id' => $request->search_id]);
            $this->logger->logRequest('Add Amenities', $request->all());

            $searchId = $request->search_id;
            $resultId = $request->result_id;
            $amenityKey = $request->amenity_key;

            $pollCacheKey = "mozio_poll_{$searchId}";
            $pollCache = Cache::get($pollCacheKey);

            if (! $pollCache || ! isset($pollCache['data']['data'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Search session expired.',
                ], 404);
            }

            $ride = collect($pollCache['data']['data'])->firstWhere('result_id', $resultId);

            if (! $ride) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ride not found.',
                ], 404);
            }

            $rideAmenities = data_get($ride, 'steps.0.details.amenities', []);

            $amenity = collect($rideAmenities)->firstWhere('key', $amenityKey);

            if (! $amenity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Amenity not found for this ride.',
                ], 404);
            }

            $cacheKey = "selected_amenities_{$searchId}_{$resultId}";
            $selectedAmenitiesCache = Cache::get($cacheKey);

            if (
                ! is_array($selectedAmenitiesCache) ||
                ! isset($selectedAmenitiesCache['selected_amenities'])
            ) {
                $createdAt = now();
                $expireAt = $createdAt->copy()->addHours(120);

                $selectedAmenitiesCache = [
                    'created_at' => $createdAt->toDateTimeString(),
                    'expire_at' => $expireAt->toDateTimeString(),
                    'selected_amenities' => [],
                ];
            }

            $selectedAmenities = $selectedAmenitiesCache['selected_amenities'];

            // duplicate check
            $alreadyExists = collect($selectedAmenities)->contains(function ($item) use ($amenityKey) {
                return isset($item['key']) && $item['key'] === $amenityKey;
            });

            if (! $alreadyExists) {
                $amenity['selected'] = true;
                $selectedAmenities[] = $amenity;
            }

            // cache update
            $selectedAmenitiesCache['selected_amenities'] = $selectedAmenities;

            Cache::put(
                $cacheKey,
                $selectedAmenitiesCache,
                now()->addHours(120)
            );

            $response = [
                'success' => true,
                'message' => 'Amenity added to selection successfully.',
                'data' => [$amenity],
                'errors' => [],
            ];

            $this->logger->setContext([
                'user_id' => $user->id,
                'search_id' => $searchId,
            ]);

            $this->logger->logResponse('Add Amenities', $response);

            return response()->json($response);

        } catch (Throwable $e) {
            $user = auth('api')->user();

            $this->logger->logException('Add Amenities', $e, [
                'user_id' => $user->id,
                'search_id' => $searchId,
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'payload' => $request->all(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function removeAmenity(AmenitiesRequest $request)
    {
        try {
            $user = auth('api')->user();

            $this->logger->setContext([
                'user_id' => $user->id,
                'search_id' => $request->search_id,
                'result_id' => $request->result_id,
                'amenity_key' => $request->amenity_key,
            ]);
            $this->logger->logRequest('Remove Amenities', $request->all());

            $searchId = $request->search_id;
            $resultId = $request->result_id;
            $amenityKey = $request->amenity_key;

            $cachedData = Cache::get("mozio_poll_{$searchId}");

            if (! $cachedData) {

                $response = [
                    'success' => false,
                    'message' => 'Search session expired.',
                ];

                Log::channel('api')->info('Remove Amenities API Response', [
                    'search_id' => $searchId,
                    'response' => $response,
                ]);

                return response()->json($response, 404);
            }

            $ride = collect($cachedData['data']['data'])
                ->firstWhere('result_id', $resultId);

            if (! $ride) {

                $response = [
                    'success' => false,
                    'message' => 'Ride not found.',
                ];

                Log::channel('api')->info('Remove Amenities API Response', [
                    'search_id' => $searchId,
                    'result_id' => $resultId,
                    'response' => $response,
                ]);

                return response()->json($response, 404);
            }

            $cacheKey = "selected_amenities_{$searchId}_{$resultId}";

            $selectedAmenitiesCache = Cache::get($cacheKey);

            if (
                ! is_array($selectedAmenitiesCache) ||
                ! isset($selectedAmenitiesCache['selected_amenities'])
            ) {

                $response = [
                    'success' => false,
                    'message' => 'No selected amenities found.',
                ];

                Log::channel('api')->info('Remove Amenities API Response', [
                    'search_id' => $searchId,
                    'result_id' => $resultId,
                    'response' => $response,
                ]);

                return response()->json($response, 404);
            }

            $selectedAmenities = $selectedAmenitiesCache['selected_amenities'];

            if (empty($selectedAmenities)) {

                $response = [
                    'success' => false,
                    'message' => 'No selected amenities found.',
                ];

                Log::channel('api')->info('Remove Amenities API Response', [
                    'user_id' => $user->id,
                    'search_id' => $searchId,
                    'result_id' => $resultId,
                    'response' => $response,
                ]);

                return response()->json($response, 404);
            }

            $amenityExists = collect($selectedAmenities)
                ->contains(function ($item) use ($amenityKey) {
                    return $item['key'] === $amenityKey;
                });

            if (! $amenityExists) {

                $response = [
                    'success' => false,
                    'message' => 'Amenity not found in selected list.',
                ];

                return response()->json($response, 404);
            }

            $updatedAmenities = collect($selectedAmenities)
                ->reject(function ($item) use ($amenityKey) {
                    return $item['key'] === $amenityKey;
                })
                ->values()
                ->toArray();

            Cache::put(
                $cacheKey,
                [
                    'selected_amenities' => $updatedAmenities,
                ],
                now()->addHours(120)
            );

            $response = [
                'success' => true,
                'message' => 'Amenity removed successfully.',
                'data' => [
                    'search_id' => $searchId,
                    'result_id' => $resultId,
                    'removed_amenity_key' => $amenityKey,
                    'selected_amenities' => $updatedAmenities,
                ],
                'errors' => [],
            ];

            $this->logger->setContext([
                'user_id' => $user->id,
                'search_id' => $searchId,
                'result_id' => $resultId,
            ]);

            $this->logger->logResponse('Remove Amenities', $response);

            return response()->json($response);

        } catch (Throwable $e) {
            $user = auth('api')->user();

            $this->logger->logException('Remove Amenities', $e, [
                'user_id' => $user->id,
                'search_id' => $searchId,
                'result_id' => $resultId,
                'amenity_key' => $request->amenity_key,
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'payload' => $request->all(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while removing amenity.',
                'data' => [],
                'errors' => config('app.debug')
                    ? [$e->getMessage()]
                    : ['Internal Server Error'],
            ], 500);
        }
    }

    public function selectedAmenitiesSummary(AmenitiesRequest $request)
    {
        try {
            $user = auth('api')->user();

            $this->logger->setContext(['user_id' => $user->id, 'search_id' => $request->search_id]);
            $this->logger->logRequest('Amenities Summary', $request->all());

            $searchId = $request->search_id;
            $resultId = $request->result_id;

            $cachedData = Cache::get("mozio_poll_{$searchId}");

            if (! $cachedData || ! isset($cachedData['data']['data'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Search session expired.',
                ], 404);
            }

            $ride = collect($cachedData['data']['data'])
                ->firstWhere('result_id', $resultId);

            if (! $ride) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ride not found.',
                ], 404);
            }

            $cacheKey = "selected_amenities_{$searchId}_{$resultId}";
            $selectedAmenitiesCache = Cache::get($cacheKey);

            if (
                ! is_array($selectedAmenitiesCache) ||
                ! isset($selectedAmenitiesCache['selected_amenities'])
            ) {
                $selectedAmenities = [];
            } else {
                $selectedAmenities = $selectedAmenitiesCache['selected_amenities'];
            }

            $optionalAmenities = collect($selectedAmenities)
                ->pluck('key')
                ->filter()
                ->values()
                ->toArray();

            $this->mozio->setContext([
                'user_id' => auth('api')->id(),
                'search_id' => $searchId,
            ]);

            $pricingResponse = $this->mozio->pricing([
                'search_id' => $searchId,
                'result_id' => $resultId,
                'optional_amenities' => $optionalAmenities,
            ]);

            if (! $pricingResponse['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $pricingResponse['message'] ?? 'Failed to retrieve pricing.',
                    'data' => [],
                    'errors' => $pricingResponse['error'] ?? [],
                ], 500);
            }

            $pricingData = $pricingResponse['data'];

            $response = [
                'success' => true,
                'message' => 'Selected amenities retrieved successfully.',
                'data' => [
                    'selectedAmenities' => $selectedAmenities,
                    'total_price' => $pricingData['final_price'] ?? null,
                    'pricing_response' => $pricingData,
                ],
                'errors' => [],
            ];

            $this->logger->setContext([
                'user_id' => $user->id,
                'search_id' => $searchId,
            ]);

            $this->logger->logResponse('Amenities Summary', $response);

            return response()->json($response);

        } catch (Throwable $e) {
            $user = auth('api')->user();

            $this->logger->logException('Amenities Summary', $e, [
                'user_id' => $user->id,
                'search_id' => $searchId,
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'payload' => $request->all(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function pricing(Request $request)
    {
        $user = auth('api')->user();

        $this->logger->setContext(['user_id' => $user->id, 'search_id' => $request->search_id]);
        $this->logger->logRequest('Pricing', $request->all());

        $validator = Validator::make($request->all(), [
            'search_id' => 'required|string',
            'result_id' => 'required|string',
            'optional_amenities' => 'nullable|array',
            'optional_amenities.*' => 'string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'data' => [],
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $searchId = $request->search_id;
            $resultId = $request->result_id;
            $optionalAmenities = $request->optional_amenities ?? [];

            $this->mozio->setContext([
                'user_id' => auth('api')->id(),
                'search_id' => $searchId,
            ]);

            $pricingResponse = $this->mozio->pricing([
                'search_id' => $searchId,
                'result_id' => $resultId,
                'optional_amenities' => $optionalAmenities,
            ]);

            if (! $pricingResponse['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $pricingResponse['message'] ?? 'Failed to retrieve pricing.',
                    'data' => [],
                    'errors' => $pricingResponse['error'] ?? [],
                ], 500);
            }

            $pricingData = $pricingResponse['data'];

            $response = [
                'success' => true,
                'message' => 'Pricing retrieved successfully.',
                'data' => [
                    'search_id' => $searchId,
                    'result_id' => $resultId,
                    'optional_amenities' => $optionalAmenities,
                    'final_price' => $pricingData['final_price'] ?? null,
                    'pricing_response' => $pricingData,
                ],
                'errors' => [],
            ];

            $this->logger->setContext([
                'user_id' => $user->id,
                'search_id' => $searchId,
            ]);

            $this->logger->logResponse('Pricing', $response);

            return response()->json($response);

        } catch (Throwable $e) {
            $user = auth('api')->user();
            $this->logger->logException('Pricing', $e, [
                'user_id' => $user->id,
                'search_id' => $searchId,
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'payload' => $request->all(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while retrieving pricing.',
                'data' => [],
                'errors' => [
                    config('app.debug') ? $e->getMessage() : 'Internal Server Error',
                ],
            ], 500);
        }
    }
}
