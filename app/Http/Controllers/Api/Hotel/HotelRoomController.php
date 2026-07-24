<?php

namespace App\Http\Controllers\Api\Hotel;

use App\Logging\ApiLogger;
use App\Services\HotelNexusService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Throwable;

class HotelRoomController extends BaseController
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
     * STEP 3 — Rooms and Rates
     * POST /api/hotel/{hotelId}/rooms/{token}
     */
    public function roomsAndRates(Request $request, string $hotelId, string $token)
    {
        $user = auth('api')->user();

        $this->logger->setContext(['user_id' => $user->id, 'hotel_id' => $hotelId, 'token' => $token]);
        $this->logger->logRequest('HotelRoomsAndRates', [
            'hotel_id' => $hotelId,
            'token' => $token,
        ]);

        try {
            $customerIp = $request->ip();

            $this->hotel->setContext(['user_id' => $user->id, 'token' => $token]);
            $result = $this->hotel->roomsAndRates($hotelId, $token, [], $customerIp);

            if (! $result['success']) {
                $errorCode = $result['error']['code'] ?? null;

                $friendlyMessages = [
                    4001 => 'Invalid search request — please check the hotel and dates and try again.',
                    4004 => 'Sorry, this hotel is sold out for the selected dates.',
                    5000 => 'Something went wrong on the supplier side. Please try again shortly.',
                ];

                return response()->json([
                    'success' => false,
                    'message' => $friendlyMessages[$errorCode] ?? $result['message'],
                    'data' => [],
                    'errors' => $result['error'] ?? [],
                ], $result['status'] ?? 500);
            }

            $data = $result['data'];
            $hotel = $data['hotel'] ?? [];

            // standardisedRoomGroups is the doc-recommended primary data source for
            // the room selection UI — it's only present if the mapping service is
            // subscribed on this channel. Fall back gracefully if it's absent.
            $hasMapping = ! empty($hotel['standardisedRoomGroups']);

            $response = [
                'success' => true,
                'message' => 'Rooms and rates fetched successfully.',
                'data' => [
                    'token' => $data['token'] ?? $token,
                    'hotel_id' => $hotelId,
                    'hotel' => $hotel,
                    'has_mapping_service' => $hasMapping,
                    // Primary field for rendering — use this first when present.
                    'standardisedRoomGroups' => $hotel['standardisedRoomGroups'] ?? [],
                    // Kept for reference / fallback when mapping service isn't active.
                    'standardisedRooms' => $hotel['standardisedRooms'] ?? [],
                    'recommendations' => $hotel['recommendations'] ?? [],
                ],
                'errors' => [],
            ];

            $this->logger->logResponse('HotelRoomsAndRates', [
                'hotel_id' => $hotelId,
                'has_mapping_service' => $hasMapping,
                'standardised_room_groups' => count($hotel['standardisedRoomGroups'] ?? []),
                'recommendations' => count($hotel['recommendations'] ?? []),
            ]);

            return response()->json($response);

        } catch (Throwable $e) {
            $this->logger->logException('HotelRoomsAndRates', $e, [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while fetching rooms and rates.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error',
            ], 500);
        }
    }

    /**
     * STEP 4 — Price by Recommendation
     * GET /api/hotel/{hotelId}/{token}/price/{recommendationId}
     */
    public function priceByRecommendation(Request $request, string $hotelId, string $token, string $recommendationId)
    {
        $user = auth('api')->user();

        $this->logger->setContext(['user_id' => $user->id, 'hotel_id' => $hotelId, 'token' => $token]);
        $this->logger->logRequest('HotelPriceByRecommendation', [
            'hotel_id' => $hotelId,
            'token' => $token,
            'recommendation_id' => $recommendationId,
        ]);

        try {
            $customerIp = $request->ip();

            $this->hotel->setContext(['user_id' => $user->id, 'token' => $token]);
            $result = $this->hotel->priceByRecommendation($hotelId, $token, $recommendationId, $customerIp);

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'data' => [],
                    'errors' => $result['error'] ?? [],
                ], $result['status'] ?? 500);
            }

            $data = $result['data'];

            $response = [
                'success' => true,
                'message' => 'Price by recommendation fetched successfully.',
                'data' => [
                    'token' => $data['token'] ?? $token,
                    'hotel_id' => $hotelId,
                    'recommendation_id' => $recommendationId,
                    'rates' => $data['hotel']['rates'] ?? [],
                ],
                'errors' => [],
            ];

            $this->logger->logResponse('HotelPriceByRecommendation', [
                'hotel_id' => $hotelId,
                'recommendation_id' => $recommendationId,
                'rates_count' => count($data['hotel']['rates'] ?? []),
            ]);

            return response()->json($response);

        } catch (Throwable $e) {
            $this->logger->logException('HotelPriceByRecommendation', $e, [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while fetching price by recommendation.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error',
            ], 500);
        }
    }
}
