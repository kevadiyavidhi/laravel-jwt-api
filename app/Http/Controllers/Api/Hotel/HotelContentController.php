<?php

namespace App\Http\Controllers\Api\Hotel;

use App\Helpers\HotelFilterHelper;
use App\Http\Requests\Hotel\GetHotelContentRequest;
use App\Logging\ApiLogger;
use App\Services\HotelNexusService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Throwable;

class HotelContentController extends BaseController
{
    protected HotelNexusService $hotel;

    protected ApiLogger $logger;

    public function __construct(HotelNexusService $hotel)
    {
        $this->hotel = $hotel;
        $this->logger = new ApiLogger;
        // $this->middleware('auth:api');
    }

    /**
     * Get Hotel Content
     * POST /api/hotel/content
     */
    public function getContent(GetHotelContentRequest $request)
    {
        $user = auth('api')->user();

        $this->logger->setContext(['user_id' => $user->id]);
        $this->logger->logRequest('HotelContent', $request->all());

        try {
            $payload = [
                'culture' => 'en-US',
                'contentFields' => $request->input('content_fields', [
                    'basic', 'facilities', 'images', 'descriptions',
                ]),
            ];

            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('perPage', 20);

            if ($request->filled('hotel_ids')) {
                $payload['hotelIds'] = $request->hotel_ids;
            } elseif ($request->filled('circular_region')) {
                $payload['circularRegion'] = $request->circular_region;
            } elseif ($request->filled('polygonal_region')) {
                $payload['polygonalRegion'] = $request->polygonal_region;
            } elseif ($request->filled('multi_polygonal_region')) {
                $payload['multiPolygonalRegion'] = $request->multi_polygonal_region;
            }

            if ($request->filled('filter_by')) {
                $payload['filterBy'] = $request->filter_by;
            }

            if ($request->filled('distance_from')) {
                $payload['distanceFrom'] = $request->distance_from;
            }

            $this->hotel->setContext(['user_id' => $user->id]);
            $result = $this->hotel->getHotelContent($payload, $request->ip());

            // dd($result);

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'data' => [],
                    'errors' => $result['error'] ?? [],
                ], $result['status'] ?? 500);
            }

            $page = (int) $request->input('page', 1);
            $perPage = (int) $request->input('perPage', 20);

            $allHotels = $result['data']['hotels'] ?? [];

            $filters = $request->input('filters', []);

            $filterSummary = HotelFilterHelper::buildFilterSummary($allHotels);
            $filteredHotels = HotelFilterHelper::applyFilters($allHotels, $filters);

            $total = count($filteredHotels);
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;
            $offset = ($page - 1) * $perPage;
            $paginated = array_slice($filteredHotels, $offset, $perPage);

            $response = [
                'success' => true,
                'message' => 'Hotel content fetched successfully.',
                'data' => [
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

            // $this->logger->logResponse('HotelContent', $result);

            return response()->json($response);

        } catch (Throwable $e) {
            $this->logger->logException('HotelContent', $e, [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while fetching hotel content.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error',
            ], 500);
        }
    }

    /**
     * Get Facility Groups
     * GET /api/hotel/content/facilities
     */
    public function getFacilityGroups(Request $request)
    {
        $user = auth('api')->user();

        $this->logger->setContext(['user_id' => $user->id]);
        $this->logger->logRequest('HotelFacilities', []);

        try {
            $this->hotel->setContext(['user_id' => $user->id]);
            $result = $this->hotel->getFacilityGroups([], $request->ip());

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'data' => [],
                    'errors' => $result['error'] ?? [],
                ], $result['status'] ?? 500);
            }

            $this->logger->logResponse('HotelFacilities', $result);

            return response()->json($result);

        } catch (Throwable $e) {
            $this->logger->logException('HotelFacilities', $e, [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while fetching facility groups.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error',
            ], 500);
        }
    }

    /**
     * Get Guest Reviews
     * POST /api/hotel/{hotelId}/reviews
     */
    // public function guestReviews(Request $request, string $hotelId)
    // {
    //     $user = auth('api')->user();

    //     $this->logger->setContext(['user_id' => $user->id, 'hotel_id' => $hotelId]);
    //     $this->logger->logRequest('HotelGuestReviews', ['hotel_id' => $hotelId]);

    //     try {
    //         $this->hotel->setContext(['user_id' => $user->id]);
    //         $result = $this->hotel->guestReviews($hotelId, $request->ip());

    //         if (! $result['success']) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => $result['message'],
    //                 'data'    => [],
    //                 'errors'  => $result['error'] ?? [],
    //             ], $result['status'] ?? 500);
    //         }

    //         $response = [
    //             'success' => true,
    //             'message' => 'Guest reviews fetched successfully.',
    //             'data'    => $result['data'],
    //             'errors'  => [],
    //         ];

    //         $this->logger->logResponse('HotelGuestReviews', $response);

    //         return response()->json($response);

    //     } catch (Throwable $e) {
    //         $this->logger->logException('HotelGuestReviews', $e, [
    //             'url'    => $request->fullUrl(),
    //             'method' => $request->method(),
    //         ]);

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Something went wrong while fetching guest reviews.',
    //             'error'   => config('app.debug') ? $e->getMessage() : 'Internal Server Error',
    //         ], 500);
    //     }
    // }
}

// namespace App\Http\Controllers\Api\Hotel;

// use App\Logging\ApiLogger;
// use App\Services\HotelNexusService;
// use Illuminate\Http\Request;
// use Illuminate\Routing\Controller as BaseController;
// use Throwable;

// class HotelContentController extends BaseController
// {
//     protected HotelNexusService $hotel;

//     protected ApiLogger $logger;

//     public function __construct(HotelNexusService $hotel)
//     {
//         $this->hotel = $hotel;
//         $this->logger = new ApiLogger;
//         $this->middleware('auth:api');
//     }

//     /**
//      * Get Hotel Content
//      * POST /api/hotel/content
//      * Returns hotel details, images, facilities, description etc.
//      */

//     public function getContent(Request $request)
//     {
//         $request->validate([
//             'hotel_ids' => ['required_without:circular_region', 'array'],
//             'hotel_ids.*' => ['string'],
//             'circular_region' => ['required_without:hotel_ids', 'array'],
//             'circular_region.centerLat' => ['required_with:circular_region', 'numeric'],
//             'circular_region.centerLong' => ['required_with:circular_region', 'numeric'],
//             'circular_region.radiusInKM' => ['required_with:circular_region', 'numeric'],
//         ]);

//         $user = auth('api')->user();

//         $this->logger->setContext(['user_id' => $user->id]);
//         $this->logger->logRequest('HotelContent', $request->all());

//         try {
//             $payload = [
//                 'currency' => $request->currency ?? 'USD',
//             ];

//             if ($request->filled('hotel_ids')) {
//                 $payload['hotelIds'] = $request->hotel_ids;
//             } elseif ($request->filled('circular_region')) {
//                 $payload['circularRegion'] = $request->circular_region;
//             } elseif ($request->filled('polygonal_region')) {
//                 $payload['polygonalRegion'] = $request->polygonal_region;
//             }

//             $this->hotel->setContext(['user_id' => $user->id]);
//             $result = $this->hotel->getHotelContent($payload, $request->ip());

//             if (! $result['success']) {
//                 return response()->json([
//                     'success' => false,
//                     'message' => $result['message'],
//                     'data' => [],
//                     'errors' => $result['error'] ?? [],
//                 ], $result['status'] ?? 500);
//             }

//             $data = $result['data'];

//             $response = [
//                 'success' => true,
//                 'message' => 'Hotel content fetched successfully.',
//                 'data' => $data,
//                 'errors' => [],
//             ];

//             $this->logger->logResponse('HotelContent', $response);

//             return response()->json($response);

//         } catch (Throwable $e) {
//             $this->logger->logException('HotelContent', $e, [
//                 'url' => $request->fullUrl(),
//                 'method' => $request->method(),
//             ]);

//             return response()->json([
//                 'success' => false,
//                 'message' => 'Something went wrong while fetching hotel content.',
//                 'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error',
//             ], 500);
//         }
//     }

//     public function getFacilityGroups(Request $request)
//     {
//         $user = auth('api')->user();

//         $this->logger->setContext(['user_id' => $user->id]);
//         $this->logger->logRequest('HotelFacilities', $request->all());

//         try {
//             $this->hotel->setContext(['user_id' => $user->id]);
//             $result = $this->hotel->getFacilityGroups($request->all(), $request->ip());

//             if (! $result['success']) {
//                 return response()->json([
//                     'success' => false,
//                     'message' => $result['message'],
//                     'data' => [],
//                     'errors' => $result['error'] ?? [],
//                 ], $result['status'] ?? 500);
//             }

//             $data = $result['data'];

//             $response = [
//                 'success' => true,
//                 'message' => 'Facility groups fetched successfully.',
//                 'data' => $data,
//                 'errors' => [],
//             ];

//             $this->logger->logResponse('HotelFacilities', $response);

//             return response()->json($response);

//         } catch (Throwable $e) {
//             $this->logger->logException('HotelFacilities', $e, [
//                 'url' => $request->fullUrl(),
//                 'method' => $request->method(),
//             ]);

//             return response()->json([
//                 'success' => false,
//                 'message' => 'Something went wrong while fetching facility groups.',
//                 'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error',
//             ], 500);
//         }
//     }
// }
