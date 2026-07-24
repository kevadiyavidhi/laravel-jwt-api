<?php

namespace App\Http\Controllers\Api\Hotel;

use App\Logging\ApiLogger;
use App\Services\HotelNexusService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Throwable;

class HotelLocationController extends BaseController
{
    protected ApiLogger $logger;

    protected string $locationApiUrl;

    protected HotelNexusService $hotel;

    public function __construct(HotelNexusService $hotel)
    {
        $this->hotel = $hotel;
        $this->logger = new ApiLogger;
        $this->middleware('auth:api');
    }

    public function search(Request $request)
    {
        $request->validate([
            'SearchLocation' => ['required', 'string', 'min:2'],
        ]);

        $searchTerm = $request->input('SearchLocation');
        $user = auth('api')->user();

        $this->logger->setContext(['user_id' => $user->id]);
        $this->logger->logRequest('HotelLocationSearch', ['SearchLocation' => $searchTerm]);

        try {
            $this->hotel->setContext(['user_id' => $user->id]);
            $result = $this->hotel->locationSearch($searchTerm, $request->ip());

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'data' => [],
                    'errors' => $result['error'] ?? [],
                ], $result['status'] ?? 500);
            }

            $this->logger->logResponse('HotelLocationSearch', $result);

            return response()->json($result);

        } catch (Throwable $e) {
            $this->logger->logException('HotelLocationSearch', $e, [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while searching locations.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error',
            ], 500);
        }
    }

    /**
     * STEP 2 — Get Location Details (city types only)
     * GET /api/hotel/locations/{id}/details
     * Only call for type: city, multiCity, region, neighbourhood
     * Skip for: hotel, airport, pointOfInterest, trainStation
     * Returns polygon boundary coordinates to pass into Search Init
     */
    public function details(Request $request, string $locationId)
    {
        $user = auth('api')->user();

        $this->logger->setContext(['user_id' => $user->id]);
        $this->logger->logRequest('HotelLocationDetails', ['location_id' => $locationId]);

        try {
            $this->hotel->setContext(['user_id' => $user->id]);
            $result = $this->hotel->locationDetails($locationId, $request->ip());

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'message' => $result['message'],
                    'data' => [],
                    'errors' => $result['error'] ?? [],
                ], $result['status'] ?? 500);
            }

            $this->logger->logResponse('HotelLocationDetails', $result);

            return response()->json($result);

        } catch (Throwable $e) {
            $this->logger->logException('HotelLocationDetails', $e, [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong while fetching location details.',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal Server Error',
            ], 500);
        }
    }
}
