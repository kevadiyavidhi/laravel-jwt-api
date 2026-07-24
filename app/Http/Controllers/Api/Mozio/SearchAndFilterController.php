<?php

namespace App\Http\Controllers\Api\Mozio;

use App\Http\Requests\FilterRidesRequest;
use App\Http\Requests\SearchRideRequest;
use App\Logging\ApiLogger;
use App\Services\MozioService;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SearchAndFilterController extends BaseController
{
    protected MozioService $mozio;

    protected ApiLogger $logger;

    public function __construct(MozioService $mozio)
    {
        $this->mozio = $mozio;
        $this->logger = new ApiLogger;
        $this->middleware('auth:api', [
            'only' => ['search', 'poll', 'filterRides'],
        ]);
    }

    public function search(SearchRideRequest $request)
    {
        try {
            $user = auth('api')->user();

            $this->logger->setContext(['user_id' => $user->id, 'search_id' => null]);
            $this->logger->logRequest('Search', $request->all());

            $payload = [
                'start_address' => $request->pickup,
                'end_address' => $request->dropoff,
                'mode' => $request->mode,
                'pickup_datetime' => $request->pickup_datetime,
                'num_passengers' => $request->num_passengers,
                'currency' => 'USD',
            ];

            if ($request->mode === 'round_trip') {
                $payload['return_pickup_datetime'] = $request->return_pickup_datetime;
            }

            $this->mozio->setContext([
                'user_id' => auth('api')->id(),
                'search_id' => null,
            ]);

            $result = $this->mozio->searchRide($payload);

            $this->logger->setContext([
                'user_id' => $user->id,
                'search_id' => data_get($result, 'data.search_id'),
            ]);
            $this->logger->logResponse('Search', $result);

            return response()->json($result);

        } catch (Throwable $e) {

            $this->logger->logException('Search', $e, [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'payload' => $request->all(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function poll(string $searchId)
    {
        try {
            $user = auth('api')->user();

            $this->logger->setContext(['user_id' => $user->id, 'search_id' => $searchId]);
            $this->logger->logRequest('SearchPoll');

            $this->mozio->setContext([
                'user_id' => auth('api')->id(),
                'search_id' => $searchId,
            ]);

            $response = $this->mozio->searchResults($searchId);

            if (! $response['success']) {
                Log::channel('api')->info('SearchPoll API Response', [
                    'user_id' => $user->id,
                    'search_id' => $searchId,
                    'response' => $response,
                ]);

                return response()->json($response, $response['status'] ?? 400);
            }

            $data = $response['data'];
            $rides = collect($data['results'] ?? []);

            $prices = $rides->pluck('total_price.total_price.value');

            $providers = $rides
                ->groupBy(fn ($ride) => data_get(
                    $ride,
                    'steps.0.details.provider.name',
                    'Unknown'
                ))->map->count();

            $vehicleClass = $rides
                ->groupBy(fn ($ride) => data_get(
                    $ride,
                    'steps.0.details.vehicle.vehicle_type.name',
                    'Unknown'
                ))->map->count();

            $bags = $rides->map(
                fn ($ride) => data_get(
                    $ride,
                    'steps.0.details.vehicle.max_bags',
                    0
                ));

            $waitTimes = $rides->map(
                fn ($ride) => data_get(
                    $ride,
                    'steps.0.details.wait_time.minutes_included',
                    0
                ));

            $amenities = [];

            foreach ($rides as $ride) {
                $rideAmenities = data_get(
                    $ride,
                    'steps.0.details.amenities',
                    []
                );

                foreach ($rideAmenities as $amenity) {
                    if (is_array($amenity)) {
                        $key = $amenity['key'] ?? null;
                    } else {
                        $key = $amenity;
                    }

                    if (! $key) {
                        continue;
                    }

                    $amenities[$key] = ($amenities[$key] ?? 0) + 1;
                }
            }

            $responseData = [
                'success' => true,
                'message' => 'Rental Transfer search completed successfully.',
                'data' => [
                    'search_id' => $searchId,
                    'currency' => $data['currency'] ?? 'USD',
                    'filters' => [
                        'priceRange' => [
                            'minPrice' => $prices->min(),
                            'maxPrice' => $prices->max(),
                        ],
                        'providers' => $providers,
                        'luggageRange' => [
                            'minLuggage' => $bags->min(),
                            'maxLuggage' => $bags->max(),
                        ],
                        'amenities' => $amenities,
                        'waitTimeRange' => [
                            'minWaitTime' => $waitTimes->min(),
                            'maxWaitTime' => $waitTimes->max(),
                        ],
                        'vehicleClass' => $vehicleClass,
                    ],
                    'count' => $rides->count(),
                    'data' => $rides->values(),
                ],
            ];
            $searchId = $responseData['data']['search_id'] ?? $searchId;

            $createdAt = now();
            $expireAt = $createdAt->copy()->addMinute(24000);

            $cachePayload = [
                'created_at' => $createdAt->toDateTimeString(),
                'expire_at' => $expireAt->toDateTimeString(),
                'data' => $responseData['data'] ?? [],
            ];

            Cache::put(
                "mozio_poll_{$searchId}",
                $cachePayload,
                $expireAt
            );

            $this->logger->setContext([
                'user_id' => $user->id,
                'search_id' => $searchId,
            ]);
            $this->logger->logResponse('SearchPoll', $responseData);

            return response()->json($responseData);

        } catch (Throwable $e) {
            $user = auth('api')->user();

            $this->logger->logException('SearchPoll', $e, [
                'user_id' => $user->id,
                'search_id' => $searchId,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to process rental transfer search details. Please try again later.',
                'error' => config('app.debug')
                ? $e->getMessage()
                : 'Internal Server Error',
            ], 500);
        }
    }

    public function filterRides(FilterRidesRequest $request)
    {
        try {
            $user = auth('api')->user();

            $this->logger->setContext(['user_id' => $user->id, 'search_id' => $request->search_id]);
            $this->logger->logRequest('Filter Rides', $request->all());

            $searchId = $request->search_id;
            $filters = $request->filters ?? [];
            $page = $request->page;
            $perPage = $request->perPage;

            $cachedData = Cache::get("mozio_poll_{$searchId}");

            if (! $cachedData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Search session expired.',
                ], 404);
            }

            $rides = collect(
                $cachedData['data']['data']
            );

            $rides = $rides->filter(
                function ($ride) use ($filters) {
                    $details = data_get($ride, 'steps.0.details', []);

                    // Providers
                    if (! empty($filters['providers'])) {
                        $provider = data_get($details, 'provider.name');

                        if (! in_array($provider, $filters['providers'])) {
                            return false;
                        }
                    }

                    // waitTimes
                    if (! empty($filters['waitTimes'])) {
                        $waitTime = data_get($details, 'wait_time.minutes_included');
                        if (! in_array($waitTime, $filters['waitTimes'])) {
                            return false;
                        }
                    }

                    // amenities
                    if (! empty($filters['amenities'])) {
                        $rideAmenities = data_get($details, 'amenities', []);

                        // Extract just the flat keys from string or structural array amenities
                        $rideAmenityKeys = collect($rideAmenities)->map(function ($amenity) {
                            return is_array($amenity) ? ($amenity['key'] ?? null) : $amenity;
                        })->filter()->all();

                        // Ensure every single checked amenity filter exists in the ride
                        foreach ($filters['amenities'] as $requestedAmenity) {
                            if (! in_array($requestedAmenity, $rideAmenityKeys)) {
                                return false;
                            }
                        }
                    }

                    // Vehicle Class
                    if (! empty($filters['vehicleClass'])) {
                        $class = data_get($details, 'vehicle.vehicle_type.name');

                        if (! in_array($class, $filters['vehicleClass'])) {
                            return false;
                        }
                    }
                    // Price Range

                    if (! empty($filters['priceRange'])) {
                        $price = (float) data_get($ride, 'total_price.total_price.value', 0);
                        $min = data_get($filters, 'priceRange.minPrice', 0);
                        $max = data_get($filters, 'priceRange.maxPrice', INF);

                        if ($price < $min || $price > $max) {
                            return false;
                        }
                    }
                    // Luggage

                    if (isset($filters['minLuggage'])) {
                        $bags = (int) data_get($details, 'vehicle.max_bags', 0);

                        if ($bags < $filters['minLuggage']) {
                            return false;
                        }
                    }

                    return true;
                });

            $totalRides = $rides->count();
            $totalPages = (int) ceil($totalRides / $perPage);

            $paginatedRides = $rides->forPage($page, $perPage);

            $response = [
                'success' => true,
                'message' => 'Rental Transfer search completed successfully.',
                'data' => [
                    'currency' => $cachedData['data']['currency'] ?? 'USD',
                    'filters' => $cachedData['data']['filters'] ?? [],
                    'count' => $totalRides,
                    'data' => $paginatedRides->values()->all(),
                    'pagination' => [
                        'current_page' => $page,
                        'per_page' => $perPage,
                        'total_items' => $totalRides,
                        'total_pages' => $totalPages,
                        'has_more' => $page < $totalPages,
                    ],
                ],
            ];

            $this->logger->setContext([
                'user_id' => $user->id,
                'search_id' => $searchId,
            ]);
            $this->logger->logResponse('Filter Rides', $response);

        } catch (Throwable $e) {
            $user = auth('api')->user();

            $this->logger->logException('SearchPoll', $e, [
                'user_id' => $user->id,
                'search_id' => $searchId,
                'request' => $request->all(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while filtering rides.',
                'error' => config('app.debug')
                    ? $e->getMessage()
                    : 'Internal Server Error',
            ], 500);
        }
    }
}
