<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Cache;

class HotelFilterHelper
{
    public static function buildFilterSummary(array $hotels): array
    {
        $prices = [];
        $distances = [];
        $refundable = 0;
        $nonRefundable = 0;
        $payAtHotel = 0;
        $payOnline = 0;
        $freeCancellation = 0;
        $freeBreakfast = 0;
        $suppliers = [];
        $boardBasis = [];
        $basisList = [];
        $starRating = [0, 0, 0, 0, 0];
        $categoryList = ['rentals' => [], 'hotels' => []];
        $facilityCounts = [];

        $rentalTypes = ['Aparthotel', 'Apartment', 'Serviced Apartment', 'Villa'];

        foreach ($hotels as $hotel) {
            $rate = $hotel['rate'] ?? [];
            $options = $hotel['options'] ?? [];

            $price = (float) ($rate['totalRate'] ?? 0);
            if ($price > 0) {
                $prices[] = $price;
            }

            $distance = (float) ($hotel['distance'] ?? 0);
            if ($distance > 0) {
                $distances[] = $distance;
            }

            if ($options['refundable'] ?? false) {
                $refundable++;
            } else {
                $nonRefundable++;
            }
            if ($options['payAtHotel'] ?? false) {
                $payAtHotel++;
            } else {
                $payOnline++;
            }
            if ($options['freeCancellation'] ?? false) {
                $freeCancellation++;
            }
            if ($options['freeBreakfast'] ?? false) {
                $freeBreakfast++;
            }

            foreach ($hotel['availableSuppliers'] ?? [] as $supplier) {
                $suppliers[$supplier] = ($suppliers[$supplier] ?? 0) + 1;
            }

            $board = $rate['boardBasis']['type'] ?? null;
            if ($board) {
                $boardBasis[$board] = ($boardBasis[$board] ?? 0) + 1;
            }

            foreach ($hotel['facilities'] ?? [] as $facility) {
                if (is_array($facility)) {
                    $fid = (string) ($facility['id'] ?? '');
                    if ($fid) {
                        $facilityCounts[$fid] = ($facilityCounts[$fid] ?? 0) + 1;
                    }
                }
            }

            $type = $hotel['type'] ?? null;
            if ($type) {
                $basisList[$type] = ($basisList[$type] ?? 0) + 1;
                if (in_array($type, $rentalTypes)) {
                    $categoryList['rentals'][$type] = ($categoryList['rentals'][$type] ?? 0) + 1;
                } else {
                    $categoryList['hotels'][$type] = ($categoryList['hotels'][$type] ?? 0) + 1;
                }
            }

            $stars = (int) ($hotel['starRating'] ?? 0);
            if ($stars >= 1 && $stars <= 5) {
                $starRating[$stars - 1]++;
            }
        }

        $summary = [
            'priceRange' => [
                'minPrice' => ! empty($prices) ? round(min($prices), 2) : 0,
                'maxPrice' => ! empty($prices) ? round(max($prices), 2) : 0,
            ],

            'distance' => [
                'minDistance' => ! empty($distances) ? number_format(min($distances), 2) : 0,
                'maxDistance' => ! empty($distances) ? number_format(max($distances), 2) : 0,
            ],

            'options' => [
                'refundable' => $refundable,
                'nonRefundable' => $nonRefundable,
                'payAtHotel' => $payAtHotel,
                'payOnline' => $payOnline,
                'freeCancellation' => $freeCancellation,
                'freeBreakfast' => $freeBreakfast,
            ],
            'suppliers' => $suppliers,
            'boardBasis' => $boardBasis,
            'type' => 'all',
            'total' => count($hotels),
        ];

        if (! empty($distances)) {
            $summary['distance'] = [
                'minDistance' => number_format(min($distances), 2),
                'maxDistance' => number_format(max($distances), 2),
            ];
        }

        if (! empty($basisList)) {
            $summary['basisList'] = $basisList;
            $summary['categoryList'] = $categoryList;
        }

        if (array_sum($starRating) > 0) {
            $summary['starRating'] = $starRating;
        }

        $facilityGroupsMap = Cache::get('hotel_facility_groups', []);

        if (! empty($facilityGroupsMap)) {
            $hotelFacilities = [];
            foreach ($facilityGroupsMap as $id => $facility) {
                $key = preg_replace('/\s+/', '', $facility['name']);
                $hotelFacilities[$key] = [
                    'id' => $facility['id'],
                    'name' => $facility['name'],
                    'count' => $facilityCounts[$id] ?? 0,
                ];
            }
            $summary['hotelFacilities'] = $hotelFacilities;
        }

        return $summary;
    }

    /**
     * Apply filters to hotel collection
     */
    public static function applyFilters(array $hotels, array $filters): array
    {
        if (empty($filters)) {
            return $hotels;
        }

        return array_values(array_filter($hotels, function ($hotel) use ($filters) {
            $rate = $hotel['rate'] ?? [];
            $options = $hotel['options'] ?? [];

            // Price range
            if (isset($filters['min_price'])) {
                if (($rate['totalRate'] ?? 0) < $filters['min_price']) {
                    return false;
                }
            }
            if (isset($filters['max_price'])) {
                if (($rate['totalRate'] ?? 0) > $filters['max_price']) {
                    return false;
                }
            }

            // Distance (enriched only)

            if (isset($filters['min_distance'])) {
                if (($hotel['distance'] ?? 0) < $filters['min_distance']) {
                    return false;
                }
            }
            if (isset($filters['max_distance'])) {
                if (($hotel['distance'] ?? 0) > $filters['max_distance']) {
                    return false;
                }
            }

            // Options
            if (! empty($filters['free_cancellation'])) {
                if (! ($options['freeCancellation'] ?? false)) {
                    return false;
                }
            }
            if (! empty($filters['free_breakfast'])) {
                if (! ($options['freeBreakfast'] ?? false)) {
                    return false;
                }
            }
            if (! empty($filters['refundable'])) {
                if (! ($options['refundable'] ?? false)) {
                    return false;
                }
            }
            if (! empty($filters['pay_at_hotel'])) {
                if (! ($options['payAtHotel'] ?? false)) {
                    return false;
                }
            }

            // Board basis
            if (! empty($filters['board_basis'])) {
                $board = $rate['boardBasis']['type'] ?? null;
                if (! in_array($board, (array) $filters['board_basis'])) {
                    return false;
                }
            }

            // Suppliers
            if (! empty($filters['suppliers'])) {
                $available = $hotel['availableSuppliers'] ?? [];
                if (empty(array_intersect($filters['suppliers'], $available))) {
                    return false;
                }
            }

            // Star rating (enriched only)
            if (! empty($filters['star_ratings'])) {
                $stars = (int) ($hotel['starRating'] ?? 0);
                if (! in_array($stars, $filters['star_ratings'])) {
                    return false;
                }
            }

            // Property type (enriched only)
            if (! empty($filters['hotel_types'])) {
                $type = $hotel['type'] ?? null;
                if (! in_array($type, $filters['hotel_types'])) {
                    return false;
                }
            }

            // Facilities (enriched only)
            if (! empty($filters['facilities'])) {
                $hotelFacilityNames = array_column($hotel['facilities'] ?? [], 'name');
                foreach ($filters['facilities'] as $required) {
                    if (! in_array($required, $hotelFacilityNames)) {
                        return false;
                    }
                }
            }

            return true;
        }));
    }
}
