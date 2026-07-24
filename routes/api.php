<?php

use App\Http\Controllers\Admin\DatabaseController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Hotel\HotelContentController;
use App\Http\Controllers\Api\Hotel\HotelLocationController;
use App\Http\Controllers\Api\Hotel\HotelRoomController;
use App\Http\Controllers\Api\Hotel\HotelSearchController;
use App\Http\Controllers\Api\Hotel\HotelSearchHotelsController;
use App\Http\Controllers\Api\Mozio\AmenitiesController;
use App\Http\Controllers\Api\Mozio\GetSessionClockController;
use App\Http\Controllers\Api\Mozio\ReservationController;
use App\Http\Controllers\Api\Mozio\SearchAndFilterController;
use App\Services\MozioService;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {

    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:api')->group(function () {

        Route::post('/logout', [AuthController::class, 'logout']);
        Route::post('/refresh', [AuthController::class, 'refresh']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::middleware('auth:api')->group(function () {
    Route::post('/mozio/search', [SearchAndFilterController::class, 'search']);

    Route::get('/mozio/poll/{searchId?}', function ($searchId = null) {
        if (! $searchId) {
            return response()->json([
                'success' => false,
                'status' => 400,
                'message' => 'You forgot to provide the Search ID in URL.',
            ], 400);
        }

        return app(SearchAndFilterController::class)->poll($searchId);
    });

    Route::post('/mozio/filter', [SearchAndFilterController::class, 'filterRides']);

    Route::get('/mozio/getSessionClock/{searchId}', [GetSessionClockController::class, 'getSessionClock'])->where('searchId', '.*');
    Route::get('/mozio/getSessionClockAmenities/{searchId}/{resultId}', [GetSessionClockController::class, 'getSelectedAmenitiesSessionClock']);

    Route::get('/mozio/amenities', [MozioService::class, 'getAmenities']);
    Route::post('/mozio/pricing', [AmenitiesController::class, 'pricing']);

    Route::post('/mozio/ride/getRideAmenities', [AmenitiesController::class, 'getRideAmenities']);
    Route::post('/mozio/selected-amenities/list', [AmenitiesController::class, 'selectedAmenities']);
    Route::post('/mozio/selected-amenities/add', [AmenitiesController::class, 'addAmenity']);
    Route::post('/mozio/selected-amenities/remove', [AmenitiesController::class, 'removeAmenity']);
    Route::post('/mozio/selected-amenities/summary', [AmenitiesController::class, 'selectedAmenitiesSummary']);

    Route::post('/mozio/reserve', [ReservationController::class, 'reserve']);

    Route::get('/mozio/reservations/{searchId}/poll', [ReservationController::class, 'bookingDetails']);
});

Route::middleware('auth:api')->get('/protected', function () {
    return response()->json([
        'message' => 'Access granted',
    ]);
});

// ── Hotel Nexus ──
Route::prefix('hotel')->group(function () {

    Route::get('/locations', [HotelLocationController::class, 'search']);
    Route::get('/locations/LocationContent/{locationId}', [HotelLocationController::class, 'details']);

    Route::post('/content', [HotelContentController::class, 'getContent']);
    Route::get('/content/facilities', [HotelContentController::class, 'getFacilityGroups']);

    Route::post('/search/init', [HotelSearchController::class, 'init']);
    Route::get('/search/{token}/results', [HotelSearchController::class, 'results']);
    Route::post('/search/{token}/filter', [HotelSearchController::class, 'filter']);
    Route::get('/search/{token}/poll', [HotelSearchController::class, 'poll']);
    Route::get('/search/{token}/enrich', [HotelSearchController::class, 'enrich']);

    Route::get('/availability/async/{token}/results', [HotelSearchController::class, 'results']);
    Route::post('/availability', [HotelSearchController::class, 'availability']);

    Route::post('/searchHotels', [HotelSearchController::class, 'searchHotels']);

    Route::post('/searchHotels', [HotelSearchHotelsController::class, 'search']);

    Route::post('/{hotelId}/rooms/{token}', [HotelRoomController::class, 'roomsAndRates']);
    Route::get('/{hotelId}/{token}/price/{recommendationId}', [HotelRoomController::class, 'priceByRecommendation']);
});

Route::get('/mozio/database', [DatabaseController::class, 'index']);
