<?php

namespace App\Http\Controllers\Api\Mozio;

use App\Logging\ApiLogger;
use App\Models\Booking;
use App\Models\Customer;
use App\Models\Passenger;
use App\Models\Reservation;
use App\Services\MozioService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class ReservationController extends BaseController
{
    protected MozioService $mozio;

    protected ApiLogger $logger;

    public function __construct(MozioService $mozio)
    {
        $this->mozio = $mozio;
        $this->logger = new ApiLogger;
        $this->middleware('auth:api', [
            'only' => ['reserve', 'bookingDetails'],
        ]);
    }

    public function reserve(Request $request)
    {
        try {
            $user = auth('api')->user();

            $passengers = $request->input('passengers', []);

            $customerIds = [];

            foreach ($passengers as $passenger) {
                $customer = Customer::firstOrCreate(
                    ['email' => $passenger['email']],
                    [
                        'user_id' => $user->id,
                        'first_name' => $passenger['first_name'] ?? null,
                        'last_name' => $passenger['last_name'] ?? null,
                        'phone_number' => $passenger['phone_number'] ?? null,
                        'birth_date' => $passenger['birth_date'] ?? null,
                        'country_code_name' => $passenger['country_code_name'] ?? null,
                    ]
                );

                $customerIds[] = $customer->id;
            }

            $this->logger->setContext([
                'user_id' => $user->id,
                'search_id' => $request->search_id,
            ]);

            $this->logger->logRequest('Reserve', $request->all());

            $searchId = $request->search_id;
            $resultId = $request->result_id;

            $cacheKey = "selected_amenities_{$searchId}_{$resultId}";
            $cachedData = Cache::get($cacheKey);

            $selectedAmenitiesCache = isset($cachedData['selected_amenities'])
                ? array_column($cachedData['selected_amenities'], 'key')
                : [];

            $optionalAmenities = $selectedAmenitiesCache;

            $primaryPassenger = $passengers[0] ?? [];

            $partnerTrackingId = (string) Str::uuid();

            $payload = [
                'search_id' => $searchId,
                'result_id' => $resultId,
                'mode' => $request->mode,
                'airline' => $request->airline,
                'flight_number' => $request->flight_number,
                'email' => $primaryPassenger['email'] ?? null,
                'country_code_name' => $primaryPassenger['country_code_name'] ?? null,
                'phone_number' => $primaryPassenger['phone_number'] ?? null,
                'first_name' => $primaryPassenger['first_name'] ?? null,
                'last_name' => $primaryPassenger['last_name'] ?? null,
                'optional_amenities' => $optionalAmenities,
                'partner_tracking_id' => $partnerTrackingId,
            ];

            if ($payload['mode'] === 'round_trip') {
                $payload['return_airline'] = $request->input('return_airline', 'AI');
                $payload['return_flight_number'] = $request->input('return_flight_number', '987');
            }

            $this->mozio->setContext([
                'user_id' => auth('api')->id(),
                'search_id' => $searchId,
            ]);

            $reservationResponse = $this->mozio->createReservation($payload);

            if (! $reservationResponse['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $reservationResponse['message'] ?? 'Reservation failed.',
                    'data' => [],
                    'errors' => $reservationResponse['error'] ?? [],
                ], 500);
            }

            Reservation::updateOrCreate(
                ['search_id' => $searchId],
                [
                    'user_id' => $user->id,
                    'result_id' => $resultId,
                    'partner_tracking_id' => $partnerTrackingId,
                    'customer_ids' => $customerIds,
                    'status' => $reservationResponse['data']['reservation_response']['status'] ?? 'pending',
                ]
            );

            $response = [
                'success' => true,
                'message' => 'Reservation created successfully.',
                'data' => [
                    'search_id' => $searchId,
                    'result_id' => $resultId,
                    'optional_amenities' => $optionalAmenities,
                    'reservation_response' => $reservationResponse['data'],
                    'partner_tracking_id' => $partnerTrackingId,
                ],
                'errors' => [],
            ];

            $this->logger->logResponse('Reserve', $response);

            return response()->json($response);

        } catch (Throwable $e) {

            $user = auth('api')->user();

            $this->logger->logException('Reserve', $e, [
                'user_id' => $user->id,
                'search_id' => $searchId ?? null,
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'payload' => $request->all(),
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while Reservation rides.',
                'error' => config('app.debug')
                    ? $e->getMessage()
                    : 'Internal Server Error',
            ], 500);
        }
    }

    public function bookingDetails(string $searchId)
    {
        try {
            $user = auth('api')->user();

            $this->logger->setContext(['user_id' => $user->id, 'search_id' => $searchId]);
            $this->logger->logRequest('Booking Details');

            $this->mozio->setContext([
                'user_id' => $user->id,
                'search_id' => $searchId,
            ]);

            $response = $this->mozio->reservationPoll($searchId);

            $reservationRecord = Reservation::where('search_id', $searchId)->first();

            if (! $reservationRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reservation not found.',
                    'data' => [],
                    'errors' => [],
                ], 404);
            }

            $resultId = $reservationRecord->result_id;

            $customerIds = $reservationRecord->customer_ids ?? [];

            $customers = ! empty($customerIds)
                ? Customer::whereIn('id', $customerIds)->get()
                : collect();

            if (! $response['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $response['message'] ?? 'Failed to fetch booking details.',
                    'data' => [],
                    'errors' => $response['error'] ?? [],
                ], $response['status'] ?? 500);
            }

            if (! empty($response['data']['reservations'])) {

                $reservations = $response['data']['reservations'];

                $returnReservation = $reservations[1] ?? null;

                $pollCacheData = Cache::get("mozio_poll_{$searchId}");

                $amenitiesCacheKey = "selected_amenities_{$searchId}_{$resultId}";
                $amenitiesCachedData = Cache::get($amenitiesCacheKey);
                $selectedAmenitiesKeys = isset($amenitiesCachedData['selected_amenities'])
                    ? array_column($amenitiesCachedData['selected_amenities'], 'key')
                    : [];
                $selectedAmenities = implode(', ', $selectedAmenitiesKeys);

                foreach ($reservations as $reservation) {

                    $reservationRecord->update([
                        'reservation_id' => $reservation['id'],
                        'status' => 'confirmed',
                    ]);

                    $pickupDatetime = ! empty($reservation['voyage']['departure_datetime'])
                        ? Carbon::parse($reservation['voyage']['departure_datetime'])->format('Y-m-d H:i:s')
                        : null;

                    $returnPickupDatetime = ! empty($returnReservation['voyage']['departure_datetime'])
                        ? Carbon::parse($returnReservation['voyage']['departure_datetime'])->format('Y-m-d H:i:s')
                        : null;

                    $booking = Booking::updateOrCreate(
                        [
                            'reservation_id' => $reservation['id'],
                        ],
                        [
                            'user_id' => $user->id,
                            'booking_engine' => 'mozio',
                            'search_id' => $searchId,
                            'confirmation_number' => $reservation['confirmation_number'] ?? null,
                            'pickup_address' => data_get($reservation, 'voyage.start_location.full_address'),
                            'dropoff_address' => data_get($reservation, 'voyage.end_location.full_address'),
                            'pickup_datetime' => $pickupDatetime,
                            'return_pickup_datetime' => $returnPickupDatetime,
                            'price' => data_get($reservation, 'total_price.value'),
                            'currency' => data_get($reservation, 'total_price.total_price.currency', 'USD'),
                            'selected_amenities' => $selectedAmenities,
                            'booking_details' => $response,
                        ]
                    );

                    if ($customers->isNotEmpty()) {
                        foreach ($customers as $customer) {
                            Passenger::updateOrCreate([
                                'booking_id' => $booking->id,
                                'customer_id' => $customer->id,
                            ]);
                        }
                    }
                }
            }

            $response = [
                'success' => true,
                'message' => 'Booking details fetched successfully.',
                'data' => $response['data'],
                'errors' => [],
            ];

            $this->logger->setContext([
                'user_id' => $user->id,
                'search_id' => $searchId,
                'result_id' => $resultId,
            ]);

            $this->logger->logResponse('Booking Details', $response);

            return response()->json($response);

        } catch (Throwable $e) {
            $user = auth('api')->user();

            $this->logger->logException('Booking Details', $e, [
                'url' => request()->fullUrl(),
                'method' => request()->method(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data' => [],
                'errors' => [],
            ], 500);
        }
    }
}
