<?php

namespace App\Http\Controllers\Api\Hotel;

use App\Helpers\HotelFilterHelper;
use App\Http\Requests\SearchHotelsRequest;
use App\Logging\ApiLogger;
use App\Models\HotelSearch;
use App\Services\HotelNexusService;
use Carbon\Carbon;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class HotelSearchHotelsController extends BaseController
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
     * Unified Hotel Search API
     * POST /api/hotel/searchHotels
     *
     * Combines:
     * 1. availabilityInit + poll  → rates, prices, refundable, suppliers
     * 2. getHotelContent          → images, distance, facilities, name, address
     *
     * Returns single merged response like reference API
     */
    public function search(SearchHotelsRequest $request)
    {
        $user = auth('api')->user();
        $customerIp = $request->ip();
        $searchId = (string) Str::uuid();
        $searchDate = now()->format('Y-m-d');
        $page = (int) $request->input('page', 1);
        $perPage = (int) $request->input('perPage', 20);
        $currency = $request->input('desiredResultCurrency', 'USD');
        $radius = (float) $request->input('radius', 5);

        $this->logger->setContext(['user_id' => $user->id]);
        $this->logger->logRequest('SearchHotels', $request->all());

        try {
            // ── STEP 1: Build occupancies from rooms ───────────────────────
            $occupancies = collect($request->rooms)->map(fn ($room) => [
                'numOfAdults' => (int) ($room['adults'] ?? 1),
                'childAges' => $room['childs'] ?? [],
            ])->toArray();

            // ── STEP 2: Availability Init ──────────────────────────────────
            $initPayload = [
                'currency' => $currency,
                'culture' => 'en-US',
                'checkIn' => Carbon::parse($request->checkinDate)->format('Y-m-d'),
                'checkOut' => Carbon::parse($request->checkoutDate)->format('Y-m-d'),
                'occupancies' => $occupancies,
                'nationality' => strtoupper($request->input('residency', 'US')),
                'countryOfResidence' => strtoupper($request->input('code', 'US')),
                'circularRegion' => [
                    'centerLat' => $request->geoLat,
                    'centerLong' => $request->geoLong,
                    'radiusInKM' => $radius,
                ],
            ];

            $this->hotel->setContext(['user_id' => $user->id]);
            $initResult = $this->hotel->availabilityInit($initPayload, $customerIp);

            if (! $initResult['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $initResult['message'],
                    'errors' => $initResult['error'] ?? [],
                ], $initResult['status'] ?? 500);
            }

            $token = $initResult['data']['token'];

            // ── STEP 3: Poll until Completed ──────────────────────────────
            $allHotels = [];
            $nextResultsKey = null;
            $status = 'InProgress';
            $maxAttempts = 120;
            $attempts = 0;

            while ($status !== 'Completed' && $attempts < $maxAttempts) {
                $pollResult = $this->hotel->availabilityResults(
                    $token,
                    $nextResultsKey,
                    $customerIp
                );

                if (! $pollResult['success']) {
                    break;
                }

                $pollData = $pollResult['data'];
                $status = $pollData['status'] ?? 'InProgress';
                $nextResultsKey = $pollData['nextResultsKey'] ?? null;

                $allHotels = array_merge($allHotels, $pollData['hotels'] ?? []);

                $attempts++;

                if ($status !== 'Completed') {
                    usleep(500000); // 500ms
                }
            }

            $hotelIds = array_column($allHotels, 'id');

            // ── STEP 4: Get Hotel Content for all hotels ───────────────────
            $contentMap = [];
            $chunks = array_chunk($hotelIds, 50);

            foreach ($chunks as $chunk) {
                $contentResult = $this->hotel->getHotelContent([
                    'hotelIds' => $chunk,
                    'currency' => $currency,
                    'culture' => 'en-US',
                    'contentFields' => ['basic', 'facilities', 'images', 'descriptions'],
                ], $customerIp);

                if ($contentResult['success']) {
                    foreach ($contentResult['data']['hotels'] ?? [] as $content) {
                        $contentId = $content['id'] ?? null;
                        if ($contentId) {
                            $contentMap[$contentId] = $content;
                        }
                    }
                }
            }

            // ── STEP 5: Get Facility Groups for filter summary ─────────────
            $facilityGroupsMap = Cache::get('hotel_facility_groups');

            if (! $facilityGroupsMap) {
                $facilityResult = $this->hotel->getFacilityGroups([], $customerIp);
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
                    Cache::put('hotel_facility_groups', $facilityGroupsMap, now()->addHours(24));
                }
            }

            // ── STEP 6: Merge poll + content ───────────────────────────────
            $mergedHotels = array_map(function ($hotel) use ($contentMap, $facilityGroupsMap, $currency) {
                $id = $hotel['id'] ?? null;
                $content = $contentMap[$id] ?? [];
                $rate = $hotel['rate'] ?? [];
                $options = $hotel['options'] ?? [];

                // Map facility IDs to names
                $hotelFacilities = [];
                foreach ($content['facilities'] ?? [] as $facilityId) {
                    if (is_array($facilityId)) {
                        $fid = (string) ($facilityId['id'] ?? '');
                        if (isset($facilityGroupsMap[$fid])) {
                            $hotelFacilities[] = $facilityGroupsMap[$fid];
                        }
                    } else {
                        $fid = (string) $facilityId;
                        if (isset($facilityGroupsMap[$fid])) {
                            $hotelFacilities[] = $facilityGroupsMap[$fid];
                        }
                    }
                }

                // Get images from links array
                $images = collect($content['images'] ?? [])
                    ->map(fn ($img) => collect($img['links'] ?? [])
                        ->map(fn ($link) => $link['url'] ?? null)
                        ->filter()
                        ->values()
                        ->toArray()
                    )
                    ->filter(fn ($links) => ! empty($links))
                    ->values()
                    ->toArray();

                // Hero image — prefer Xxl from first image
                $heroImage = collect($content['images'][0]['links'] ?? [])
                    ->firstWhere('size', 'Xxl')['url']
                    ?? collect($content['images'][0]['links'] ?? [])
                        ->firstWhere('size', 'Standard')['url']
                    ?? $content['heroImage']
                    ?? null;

                return [
                    'hotelId' => $id,
                    'img' => $heroImage,
                    'images' => $images,
                    'distance' => $content['distance'] ?? '0',
                    'distanceUnit' => 'miles',
                    'lat' => $content['geoCode']['lat'] ?? null,
                    'lon' => $content['geoCode']['long'] ?? null,

                    // From poll
                    'baseRate' => $rate['baseRate'] ?? null,
                    'lowestPrice' => $rate['totalRate'] ?? null,
                    'providerId' => $rate['providerId'] ?? null,
                    'providerName' => $rate['providerName'] ?? null,
                    'suppliers' => $hotel['availableSuppliers'] ?? [],
                    'refundable' => $options['refundable'] ?? false,
                    'payAtHotel' => $options['payAtHotel'] ?? false,
                    'hasCugHotel' => 'NO',
                    'addCharge' => '0.00',
                    'addCurrency' => $currency,

                    // From content
                    'displayName' => $content['name'] ?? null,
                    'address' => $content['contact']['address']['line1'] ?? null,
                    'cityName' => $content['contact']['address']['city']['name'] ?? null,
                    'countryCode' => $content['contact']['address']['country']['code'] ?? null,
                    'zipCode' => null,
                    'phone' => $content['contact']['phones'][0] ?? null,
                    'starRating' => (int) ($content['starRating'] ?? 0),
                    'description' => $content['descriptions'][0]['text'] ?? null,
                    'type' => 'hotels',
                    'category' => $content['type'] ?? null,
                    'basisList' => $content['type'] ? [$content['type']] : [],
                    'hotelFacility' => $hotelFacilities,
                ];
            }, $allHotels);

            // ── STEP 7: Build filter summary ───────────────────────────────
            $filters = HotelFilterHelper::buildFilterSummary($allHotels);

            // ── STEP 8: Calculate total nights ─────────────────────────────
            $checkin = Carbon::parse($request->checkinDate);
            $checkout = Carbon::parse($request->checkoutDate);
            $totalNights = $checkin->diffInDays($checkout);

            // ── STEP 9: Paginate ───────────────────────────────────────────
            $total = count($mergedHotels);
            $totalPages = $total > 0 ? (int) ceil($total / $perPage) : 0;
            $offset = ($page - 1) * $perPage;
            $paginated = array_slice($mergedHotels, $offset, $perPage);

            // ── STEP 10: Save to DB + Cache ────────────────────────────────
            HotelSearch::create([
                'user_id' => $user->id,
                'token' => $token,
                'channel_id' => config('hotelnexus.channel_id'),
                'currency' => $currency,
                'culture' => 'en-US',
                'check_in' => $checkin->format('Y-m-d'),
                'check_out' => $checkout->format('Y-m-d'),
                'occupancies' => $occupancies,
                'search_region' => ['circularRegion' => $initPayload['circularRegion']],
                'nationality' => $initPayload['nationality'],
                'country_of_residence' => $initPayload['countryOfResidence'],
                'status' => $status === 'Completed' ? 'completed' : 'in_progress',
            ]);

            Cache::put("hotel_search_{$token}", [
                'token' => $token,
                'status' => $status,
                'currency' => $currency,
                'hotels' => $mergedHotels,
                'total_collected' => $total,
                'enriched' => true,
            ], now()->addHours(2));

            // ── STEP 11: Build response ────────────────────────────────────
            $response = [
                'status' => $status,
                'success' => true,
                'message' => 'Hotels data fetched successfully',
                'data' => [
                    'token' => $token,
                    'totalNights' => $totalNights,
                    'hotelsCount' => $total,
                    'filters' => array_merge($filters, [
                        'filterHotelsCount' => $total,
                        'totalHotelsCount' => $total,
                    ]),
                    'hotels' => $paginated,
                    'missingHotelDetail' => [
                        'count' => 0,
                        'hotelIds' => [],
                    ],
                    'searchData' => array_merge($request->all(), [
                        'searchId' => $searchId,
                    ]),
                    'warning' => [],
                    'platform' => 'nexus',
                ],
                'resorts' => [],
                'errors' => [],
                'hotelSearchClock' => [
                    'expire' => false,
                    'created_time' => now()->format('Y-m-d H:i:s'),
                    'expire_at_time' => now()->addMinutes(40)->format('Y-m-d H:i:s'),
                    'remaining' => '39:55',
                    'status' => '',
                    'message' => '',
                ],
            ];

            $this->logger->logResponse('SearchHotels', [
                'token' => $token,
                'total_hotels' => $total,
                'status' => $status,
            ]);

            return response()->json($response);

        } catch (Throwable $e) {
            $this->logger->logException('SearchHotels', $e, [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while searching hotels.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error',
            ], 500);
        }
    }
}
