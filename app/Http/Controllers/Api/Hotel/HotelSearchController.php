<?php

namespace App\Http\Controllers\Api\Hotel;

use App\Helpers\HotelFilterHelper;
use App\Http\Requests\Hotel\HotelFilterSearchRequest;
use App\Http\Requests\Hotel\SearchHotelRequest;
use App\Logging\ApiLogger;
use App\Models\HotelSearch;
use App\Services\HotelNexusService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class HotelSearchController extends BaseController
{
    protected HotelNexusService $hotel;

    protected ApiLogger $logger;

    public function __construct(HotelNexusService $hotel)
    {
        $this->hotel = $hotel;
        $this->logger = new ApiLogger;
        $this->middleware('auth:api');
    }

    /**
     * POST /api/hotel/availability
     *
     * ASSUMPTION: built using the same request shape as init(), since the
     * exact payload wasn't confirmed against ZentrumHub's actual docs for
     * this specific endpoint. Adjust validation/payload if the real docs differ.
     */
    public function availability(SearchHotelRequest $request)
    {
        $user = auth('api')->user();

        $this->logger->setContext(['user_id' => $user->id]);
        $this->logger->logRequest('HotelAvailability', $request->all());

        try {
            $payload = [
                'currency' => $request->currency ?? 'USD',
                'culture' => 'en-US',
                'checkIn' => $request->check_in,
                'checkOut' => $request->check_out,
                'occupancies' => $request->occupancies,
                'nationality' => $request->nationality ?? 'US',
                'countryOfResidence' => $request->country_of_residence ?? 'US',
            ];

            if ($request->filled('hotel_ids')) {
                $payload['hotelIds'] = $request->hotel_ids;
            } elseif ($request->filled('circular_region')) {
                $payload['circularRegion'] = $request->circular_region;
            } elseif ($request->filled('multiPolygonal_region')) {
                $payload['multiPolygonalRegion'] = $request->multiPolygonal_region;
            }

            $customerIp = $request->ip();

            $this->hotel->setContext(['user_id' => $user->id]);
            $result = $this->hotel->availability($payload, $customerIp);

            $data = $result['data']['hotels'] ?? [];
            $total = count($data);

            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('perPage', 20);

            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;
            $offset = ($page - 1) * $perPage;
            $paginated = array_slice($data, $offset, $perPage);

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'data' => [],
                    'errors' => $result['error'] ?? [],
                ], $result['status'] ?? 500);
            }

            $response = [
                'success' => true,
                'message' => 'Availability fetched successfully.',
                'data' => $result['data'],
                'hotels' => $paginated,
                'hotel_count' => count($paginated),
                'pagination' => [
                    'total_hotels' => $total,
                    'per_page' => $perPage,
                    'current_page' => $page,
                    'total_pages' => $totalPages,
                    'has_more_pages' => $page < $totalPages,
                ],
                'errors' => [],
            ];

            $this->logger->logResponse('HotelAvailability', $response);

            return response()->json($response);

        } catch (Throwable $e) {
            $this->logger->logException('HotelAvailability', $e, [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while fetching availability.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error',
            ], 500);
        }
    }

    public function init(SearchHotelRequest $request)
    {
        $user = auth('api')->user();

        $this->logger->setContext(['user_id' => $user->id]);
        $this->logger->logRequest('HotelAvailabilityInit', $request->all());

        try {
            $payload = [
                'currency' => $request->currency ?? 'USD',
                'culture' => 'en-US',
                'checkIn' => $request->check_in,
                'checkOut' => $request->check_out,
                'occupancies' => $request->occupancies,
                'nationality' => $request->nationality ?? 'US',
                'countryOfResidence' => $request->country_of_residence ?? 'US',
            ];

            // Search region — one of: hotelIds, circularRegion, polygonalRegion
            if ($request->filled('hotel_ids')) {
                $payload['hotelIds'] = $request->hotel_ids;
            } elseif ($request->filled('circular_region')) {
                $payload['circularRegion'] = $request->circular_region;
            } elseif ($request->filled('multiPolygonal_region')) {
                $payload['multiPolygonalRegion'] = $request->multiPolygonal_region;
            }

            $customerIp = $request->ip();

            $this->hotel->setContext(['user_id' => $user->id]);
            $result = $this->hotel->availabilityInit($payload, $customerIp);

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'data' => [],
                    'errors' => $result['error'] ?? [],
                ], $result['status'] ?? 500);
            }

            $token = $result['data']['token'] ?? null;

            // Save search record
            $hotelSearch = HotelSearch::create([
                'user_id' => $user->id,
                'token' => $token,
                'channel_id' => config('hotelnexus.channel_id'),
                'currency' => $payload['currency'],
                'culture' => $payload['culture'],
                'check_in' => $request->check_in,
                'check_out' => $request->check_out,
                'occupancies' => $request->occupancies,
                'search_region' => array_intersect_key($payload, array_flip(['hotelIds', 'circularRegion', 'polygonalRegion'])),
                'nationality' => $payload['nationality'],
                'country_of_residence' => $payload['countryOfResidence'],
                'status' => 'pending',
            ]);

            // Initialize empty cache for this token
            Cache::put("hotel_search_{$token}", [
                'token' => $token,
                'status' => 'pending',
                'currency' => $payload['currency'],
                'expected_hotel_count' => null,
                'completed_hotel_count' => null,
                'nextResultsKey' => null,
                'hotels' => [],
                'total_collected' => 0,
            ], now()->addHours(2));

            $response = [
                'success' => true,
                'message' => 'Hotel availability search initiated.',
                'data' => [
                    'token' => $token,
                    'hotel_search_id' => $hotelSearch->id,
                ],
                'errors' => [],
            ];

            $this->logger->logResponse('HotelAvailabilityInit', $response);

            return response()->json($response);

        } catch (Throwable $e) {
            $this->logger->logException('HotelAvailabilityInit', $e, [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while initiating hotel search.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error',
            ], 500);
        }
    }

    /**
     * STEP 2 & 3 — Poll Results
     * GET /api/hotel/search/{token}/results
     * GET /api/hotel/search/{token}/results?nextResultsKey=xxx&page=1&perPage=20
     *
     * Each poll merges hotels into cache
     * Paginate from ALL collected hotels so far
     */
    public function results(Request $request, string $token)
    {
        $user = auth('api')->user();

        $this->logger->setContext(['user_id' => $user->id, 'token' => $token]);
        $this->logger->logRequest('HotelAvailabilityResults', ['token' => $token]);

        try {
            $nextResultsKey = $request->query('nextResultsKey');
            $page = (int) $request->query('page', 1);
            $perPage = (int) $request->query('perPage', 20);
            $customerIp = $request->ip();

            $this->hotel->setContext(['user_id' => $user->id, 'token' => $token]);
            $result = $this->hotel->availabilityResults($token, $nextResultsKey, $customerIp);

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'data' => [],
                    'errors' => $result['error'] ?? [],
                ], $result['status'] ?? 500);
            }

            $data = $result['data'];
            $status = $data['status'] ?? 'InProgress';

            // Merge new hotels into cache
            $cacheKey = "hotel_search_{$token}";
            $existingData = Cache::get($cacheKey, []);

            $allHotels = array_merge(
                $existingData['hotels'] ?? [],
                $data['hotels'] ?? []
            );

            // Update cache with merged hotels
            Cache::put($cacheKey, [
                'token' => $token,
                'status' => $status,
                'currency' => $data['currency'] ?? 'USD',
                'expected_hotel_count' => $data['expectedHotelCount'] ?? null,
                'completed_hotel_count' => $data['completedHotelCount'] ?? null,
                'nextResultsKey' => $data['nextResultsKey'] ?? null,
                'hotels' => $allHotels,
                'total_collected' => count($allHotels),
            ], now()->addHours(2));

            HotelSearch::where('token', $token)->update([
                'status' => $status === 'Completed' ? 'completed' : 'in_progress',
            ]);

            $total = count($allHotels);
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;
            $offset = ($page - 1) * $perPage;
            $paginated = array_slice($allHotels, $offset, $perPage);

            $response = [
                'success' => true,
                'message' => $status === 'Completed'
                    ? 'All results received.'
                    : 'Partial results — keep polling with nextResultsKey.',
                'data' => [
                    'token' => $token,
                    'status' => $status,
                    'more_coming' => $status !== 'Completed',
                    'nextResultsKey' => $data['nextResultsKey'] ?? null,
                    'expected_hotel_count' => $data['expectedHotelCount'] ?? null,
                    'completed_hotel_count' => $data['completedHotelCount'] ?? null,
                    'currency' => $data['currency'] ?? 'USD',
                    'hotels' => $paginated,
                    'total_collected' => $total,
                    'hotel_count' => count($paginated),
                    'pagination' => [
                        'total_hotels' => $total,
                        'per_page' => $perPage,
                        'current_page' => $page,
                        'total_pages' => $totalPages,
                        'has_more_pages' => $page < $totalPages,
                    ],
                ],
                'errors' => [],
            ];

            $this->logger->logResponse('HotelAvailabilityResults', [
                'token' => $token,
                'status' => $status,
                'new_in_batch' => count($data['hotels'] ?? []),
                'total_collected' => $total,
                'expected_hotel_count' => $data['expectedHotelCount'] ?? null,
                'nextResultsKey' => $data['nextResultsKey'] ?? null,
            ]);

            return response()->json($response);

        } catch (Throwable $e) {
            $this->logger->logException('HotelAvailabilityResults', $e, [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while fetching hotel results.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error',
            ], 500);
        }
    }

    /**
     * Auto Poll — collects ALL results automatically
     * GET /api/hotel/search/{token}/poll
     */
    public function poll(Request $request, string $token)
    {
        $user = auth('api')->user();

        $this->logger->setContext(['user_id' => $user->id, 'token' => $token]);
        $this->logger->logRequest('HotelAutoPoll', ['token' => $token]);

        try {
            $customerIp = $request->ip();
            $allHotels = [];
            $nextResultsKey = null;
            $status = 'InProgress';
            $maxAttempts = 60;
            $attempts = 0;
            $currency = 'USD';
            $expectedCount = null;
            $cacheKey = "hotel_search_{$token}";

            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('perPage', 20);

            while ($status !== 'Completed' && $attempts < $maxAttempts) {
                $this->hotel->setContext(['user_id' => $user->id, 'token' => $token]);
                $result = $this->hotel->availabilityResults($token, $nextResultsKey, $customerIp);

                if (! $result['success']) {
                    return response()->json([
                        'success' => false,
                        'message' => $result['message'],
                        'data' => [],
                        'errors' => $result['error'] ?? [],
                    ], $result['status'] ?? 500);
                }

                $data = $result['data'];
                $status = $data['status'] ?? 'InProgress';
                $nextResultsKey = $data['nextResultsKey'] ?? null;
                $currency = $data['currency'] ?? 'USD';
                $expectedCount = $data['expectedHotelCount'] ?? null;

                $allHotels = array_merge($allHotels, $data['hotels'] ?? []);

                // Update cache after each batch
                Cache::put($cacheKey, [
                    'token' => $token,
                    'status' => $status,
                    'currency' => $currency,
                    'expected_hotel_count' => $expectedCount,
                    'completed_hotel_count' => $data['completedHotelCount'] ?? null,
                    'nextResultsKey' => $nextResultsKey,
                    'hotels' => $allHotels,
                    'total_collected' => count($allHotels),
                ], now()->addHours(2));

                $attempts++;

                if ($status !== 'Completed') {
                    usleep(500000); // 500ms
                }
            }

            HotelSearch::where('token', $token)->update([
                'status' => $status === 'Completed' ? 'completed' : 'in_progress',
            ]);

            $filtered = collect($allHotels)->values();
            $total = $filtered->count();
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;
            $offset = ($page - 1) * $perPage;
            $paginated = $filtered->slice($offset, $perPage)->values();

            $response = [
                'success' => true,
                'message' => $status === 'Completed'
                    ? 'All hotel results collected successfully.'
                    : 'Polling stopped after max attempts — partial results returned.',
                'data' => [
                    'token' => $token,
                    'status' => $status,
                    'currency' => $currency,
                    'expected_hotel_count' => $expectedCount,
                    'total_collected' => count($allHotels),
                    'hotel_count' => count($paginated),
                    'polls_made' => $attempts,
                    'hotels' => $paginated,
                    'pagination' => [
                        'total_hotels' => $total,
                        'per_page' => $perPage,
                        'current_page' => $page,
                        'total_pages' => $totalPages,
                        'has_more_pages' => $page < $totalPages,
                    ],
                ],
                'errors' => [],
            ];

            $this->logger->logResponse('HotelAutoPoll', [
                'token' => $token,
                'status' => $status,
                'hotel_count' => count($allHotels),
                'polls_made' => $attempts,
            ]);

            return response()->json($response);

        } catch (Throwable $e) {
            $this->logger->logException('HotelAutoPoll', $e, [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while polling hotel results.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error',
            ], 500);
        }
    }

    // public function poll(HotelFilterSearchRequest $request, string $token)
    // {
    //     $user = auth('api')->user();

    //     $this->logger->setContext(['user_id' => $user->id, 'token' => $token]);
    //     $this->logger->logRequest('HotelAutoPoll', ['token' => $token, 'filters' => $request->input('filters', [])]);

    //     try {
    //         $customerIp = $request->ip();
    //         $allHotels = [];
    //         $nextResultsKey = null;
    //         $status = 'InProgress';
    //         $maxAttempts = 60;
    //         $attempts = 0;
    //         $currency = 'USD';
    //         $expectedCount = null;
    //         $cacheKey = "hotel_search_{$token}";
    //         $cachedData = Cache::get($cacheKey);

    //         $page = (int) $request->input('page', 1);
    //         $perPage = (int) $request->input('perPage', 20);
    //         $filters = $request->input('filters', []);
    //         $allHotels = $cachedData['hotels'] ?? [];

    //         while ($status !== 'Completed' && $attempts < $maxAttempts) {
    //             $this->hotel->setContext(['user_id' => $user->id, 'token' => $token]);
    //             $result = $this->hotel->availabilityResults($token, $nextResultsKey, $customerIp);

    //             if (! $result['success']) {
    //                 return response()->json([
    //                     'success' => false,
    //                     'message' => $result['message'],
    //                     'data' => [],
    //                     'errors' => $result['error'] ?? [],
    //                 ], $result['status'] ?? 500);
    //             }

    //             $data = $result['data'];
    //             $status = $data['status'] ?? 'InProgress';
    //             $nextResultsKey = $data['nextResultsKey'] ?? null;
    //             $currency = $data['currency'] ?? 'USD';
    //             $expectedCount = $data['expectedHotelCount'] ?? null;

    //             $allHotels = array_merge($allHotels, $data['hotels'] ?? []);

    //             // Update cache after each batch — preserve static_content/enriched
    //             // (set during init()'s parallel Static Content call) instead of
    //             // wiping the whole cache entry every iteration.
    //             $existingCache = Cache::get($cacheKey, []);
    //             Cache::put($cacheKey, array_merge($existingCache, [
    //                 'token' => $token,
    //                 'status' => $status,
    //                 'currency' => $currency,
    //                 'expected_hotel_count' => $expectedCount,
    //                 'completed_hotel_count' => $data['completedHotelCount'] ?? null,
    //                 'nextResultsKey' => $nextResultsKey,
    //                 'hotels' => $allHotels,
    //                 'total_collected' => count($allHotels),
    //             ]), now()->addHours(2));

    //             $attempts++;

    //             if ($status !== 'Completed') {
    //                 usleep(500000); // 500ms
    //             }
    //         }

    //         HotelSearch::where('token', $token)->update([
    //             'status' => $status === 'Completed' ? 'completed' : 'in_progress',
    //         ]);

    //         // ── Apply filters on the fully-collected hotel list ──────────────
    //         $filterSummary = HotelFilterHelper::buildFilterSummary($allHotels);
    //         $filteredHotels = HotelFilterHelper::applyFilters($allHotels, $filters);

    //         $total = count($filteredHotels);
    //         $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;
    //         $offset = ($page - 1) * $perPage;
    //         $paginated = array_slice($filteredHotels, $offset, $perPage);

    //         $response = [
    //             'success' => true,
    //             'message' => $status === 'Completed'
    //                 ? 'All hotel results collected successfully.'
    //                 : 'Polling stopped after max attempts — partial results returned.',
    //             'data' => [
    //                 'token' => $token,
    //                 'status' => $status,
    //                 'currency' => $currency,
    //                 'expected_hotel_count' => $expectedCount,
    //                 'total_collected' => count($allHotels),
    //                 'polls_made' => $attempts,
    //                 'filters' => array_merge($filterSummary, [
    //                     'filterHotelsCount' => $total,
    //                     'totalHotelsCount' => count($allHotels),
    //                 ]),
    //                 'filters_applied' => $filters,
    //                 // 'hotels' => array_values($paginated),
    //                 'hotels' => $paginated,
    //                 'hotel_count' => count($paginated),
    //                 'pagination' => [
    //                     'total_hotels' => $total,
    //                     'per_page' => $perPage,
    //                     'current_page' => $page,
    //                     'total_pages' => $totalPages,
    //                     'has_more_pages' => $page < $totalPages,
    //                 ],
    //             ],
    //             'errors' => [],
    //         ];

    //         $this->logger->logResponse('HotelAutoPoll', [
    //             'token' => $token,
    //             'status' => $status,
    //             'total_collected' => count($allHotels),
    //             'total_filtered' => $total,
    //             'polls_made' => $attempts,
    //             'filters' => $filters,
    //         ]);

    //         return response()->json($response);

    //     } catch (Throwable $e) {
    //         $this->logger->logException('HotelAutoPoll', $e, [
    //             'url' => $request->fullUrl(),
    //             'method' => $request->method(),
    //         ]);

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Something went wrong while polling hotel results.',
    //             'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error',
    //         ], 500);
    //     }
    // }

    public function enrich(Request $request, string $token)
    {
        $user = auth('api')->user();

        $this->logger->setContext(['user_id' => $user->id, 'token' => $token]);
        $this->logger->logRequest('HotelEnrich', ['token' => $token]);

        try {
            $cacheKey = "hotel_search_{$token}";
            $cachedData = Cache::get($cacheKey);

            if (! $cachedData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Search session expired or not found. Please search again.',
                    'data' => [],
                    'errors' => [],
                ], 404);
            }

            $hotels = $cachedData['hotels'] ?? [];
            $limit = min((int) $request->input('limit', 50), 100);
            $offset = (int) $request->input('offset', 0);
            $slice = array_slice($hotels, $offset, $limit);
            $hotelIds = array_column($slice, 'id');

            if (empty($hotelIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No hotels found to enrich at this offset.',
                    'data' => [],
                    'errors' => [],
                ], 404);
            }

            // ── Step 1: Get facility groups (cached 24 hours) ─────────────
            $facilityGroupsCacheKey = 'hotel_facility_groups';
            $facilityGroupsMap = Cache::get($facilityGroupsCacheKey);

            if (! $facilityGroupsMap) {
                $this->hotel->setContext(['user_id' => $user->id]);
                $facilityResult = $this->hotel->getFacilityGroups([], $request->ip());

                if ($facilityResult['success']) {
                    $facilityGroupsMap = [];
                    foreach ($facilityResult['data'] ?? [] as $facility) {
                        $id = (string) ($facility['id'] ?? '');
                        if ($id) {
                            $facilityGroupsMap[$id] = [
                                'id' => $id,
                                'name' => $facility['name'] ?? '',
                                'type' => $facility['type'] ?? '',
                            ];
                        }
                    }
                    Cache::put($facilityGroupsCacheKey, $facilityGroupsMap, now()->addHours(2400));

                }
            }

            // ── Step 2: Get content for this slice only (1 batch) ─────────
            $this->hotel->setContext(['user_id' => $user->id]);

            $result = $this->hotel->getHotelContent([
                'hotelIds' => $hotelIds,
                'currency' => $cachedData['currency'] ?? 'USD',
                'culture' => 'en-US',
                // 'contentFields' => 'basic,facilities,images,descriptions',
                'contentFields' => ['basic', 'facilities', 'images', 'descriptions'],

            ], $request->ip());

            $contentMap = [];
            if ($result['success']) {
                foreach ($result['data']['hotels'] ?? [] as $content) {
                    $contentId = $content['id'] ?? null;
                    if ($contentId) {
                        $contentMap[$contentId] = $content;
                    }
                }
            }

            // ── Step 3: Merge content into this slice ──────────────────────
            $enrichedSlice = array_map(function ($hotel) use ($contentMap, $facilityGroupsMap) {
                $id = $hotel['id'] ?? null;
                $content = $contentMap[$id] ?? [];

                $hotelFacilities = [];
                foreach ($content['facilities'] ?? [] as $facilityId) {

                    if (is_array($facilityId)) {
                        $fid = (string) ($facilityId['id'] ?? '');
                        if (isset($facilityGroupsMap[$fid])) {
                            $hotelFacilities[] = $facilityGroupsMap[$fid];
                        } elseif (isset($facilityId['name'])) {
                            $hotelFacilities[] = [
                                'id' => $fid,
                                'name' => $facilityId['name'],
                                'type' => $facilityId['type'] ?? '',
                            ];
                        }
                    } else {
                        $fid = (string) $facilityId;
                        if (isset($facilityGroupsMap[$fid])) {
                            $hotelFacilities[] = $facilityGroupsMap[$fid];
                        }
                    }
                }

                return array_merge($hotel, [
                    'name' => $content['name'] ?? $hotel['name'] ?? null,
                    'type' => $content['type'] ?? $hotel['type'] ?? null,
                    'category' => $content['category'] ?? null,
                    'starRating' => $content['starRating'] ?? $hotel['starRating'] ?? null,
                    'chainName' => $content['chainName'] ?? null,
                    'website' => $content['website'] ?? null,
                    'distance' => $content['distance'] ?? $hotel['distance'] ?? null,
                    'coordinates' => $content['geoCode'] ?? $hotel['coordinates'] ?? null,
                    'address' => [
                        'line1' => $content['contact']['address']['line1'] ?? null,
                        'city' => $content['contact']['address']['city']['name'] ?? null,
                        'country' => $content['contact']['address']['country']['name'] ?? null,
                    ],
                    'phone' => $content['contact']['phones'][0] ?? null,
                    'email' => $content['contact']['emails'][0] ?? null,
                    'images' => collect($hotel['images'] ?? [])
                        ->take(10)
                        ->map(function ($img) {
                            $image = collect($img['links'] ?? [])->firstWhere('size', 'Xxl')
                                ?? collect($img['links'] ?? [])->firstWhere('size', 'Standard');

                            return [
                                'url' => $image['url'] ?? null,
                                'caption' => $img['caption'] ?? null,
                                'category' => $img['category'] ?? null,
                            ];
                        })
                        ->filter(fn ($img) => ! empty($img['url']))
                        ->values()
                        ->toArray(),
                    'heroImage' => $content['heroImage'] ?? null,
                    'facilities' => $hotelFacilities,
                    'description' => $content['descriptions'][0]['text'] ?? null,
                    'imageCount' => $content['imageCount'] ?? null,
                ]);
            }, $slice);

            array_splice($hotels, $offset, $limit, $enrichedSlice);

            $allEnriched = ($offset + $limit) >= count($cachedData['hotels'] ?? []);

            Cache::put($cacheKey, array_merge($cachedData, [
                'hotels' => $hotels,
                'enriched' => $allEnriched,
            ]), now()->addHours(2400));

            $response = [
                'success' => true,
                'message' => 'Hotels enriched successfully.',
                'data' => [
                    'token' => $token,
                    'enriched_count' => count($enrichedSlice),
                    'offset' => $offset,
                    'limit' => $limit,
                    'total_hotels' => count($hotels),
                    'next_offset' => $offset + $limit,
                    'all_enriched' => $allEnriched,
                    'facility_groups_used' => count($facilityGroupsMap ?? []),
                ],
                'errors' => [],
            ];

            $this->logger->logResponse('HotelEnrich', $response);

            return response()->json($response);

        } catch (Throwable $e) {
            Log::error('Enrich Exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->logger->logException('HotelEnrich', $e, [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while enriching hotels.',
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ], 500);
        }
    }

    /**
     * Filter Hotels from Cache
     * POST /api/hotel/search/{token}/filter
     */
    public function filter(HotelFilterSearchRequest $request, string $token)
    {
        $user = auth('api')->user();

        $this->logger->setContext(['user_id' => $user->id, 'token' => $token]);
        $this->logger->logRequest('HotelFilter', $request->all());

        try {
            $cacheKey = "hotel_search_{$token}";
            $cachedData = Cache::get($cacheKey);

            if (! $cachedData) {
                return response()->json([
                    'success' => false,
                    'message' => 'Search session expired or not found. Please search again.',
                    'data' => [],
                    'errors' => [],
                ], 404);
            }

            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('perPage', 20);
            $filters = $request->input('filters', []);
            $allHotels = $cachedData['hotels'] ?? [];
            // dd($allHotels);

            $filterSummary = HotelFilterHelper::buildFilterSummary($allHotels);

            $filteredHotels = HotelFilterHelper::applyFilters($allHotels, $filters);

            $total = count($filteredHotels);
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;
            $offset = ($page - 1) * $perPage;
            $paginated = array_slice($filteredHotels, $offset, $perPage);

            // dd($paginated);

            $response = [
                'success' => true,
                'message' => 'Hotels filtered successfully.',
                'data' => [
                    'token' => $token,
                    'status' => $cachedData['status'],
                    'currency' => $cachedData['currency'] ?? 'USD',
                    'total_collected' => count($allHotels),
                    'filters' => array_merge($filterSummary, [
                        'filterHotelsCount' => $total,
                        'totalHotelsCount' => count($allHotels),
                    ]),
                    'filters_applied' => $filters,
                    'hotels' => $paginated,
                    'hotel_count' => count($paginated),
                    'pagination' => [
                        'total_hotels' => $total,
                        'per_page' => $perPage,
                        'current_page' => $page,
                        'total_pages' => $totalPages,
                        'has_more_pages' => $page < $totalPages,
                    ],
                ],
                'errors' => [],
            ];

            $this->logger->logResponse('HotelFilter', [
                'token' => $token,
                'total_filtered' => $total,
                'total_collected' => count($allHotels),
                'filters' => $filters,
            ]);

            return response()->json($response);

        } catch (Throwable $e) {
            $this->logger->logException('HotelFilter', $e, [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while filtering hotels.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error',
            ], 500);
        }
    }
}
