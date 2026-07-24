<?php

namespace App\Console\Commands;

use App\Models\HotelSearch;
use App\Services\HotelNexusService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class EnrichHotels extends Command
{
    protected $signature = 'hotel:enrich {token} {--limit=50}';

    protected $description = 'Enrich all hotels for a search token with content, facilities and images';

    protected HotelNexusService $hotel;

    public function __construct(HotelNexusService $hotel)
    {
        parent::__construct();
        $this->hotel = $hotel;
    }

    public function handle(): int
    {
        $token = $this->argument('token');
        $limit = (int) $this->option('limit');

        $cacheKey = "hotel_search_{$token}";
        $cachedData = Cache::get($cacheKey);

        if (! $cachedData) {
            $this->error("No cached data found for token: {$token}");

            return Command::FAILURE;
        }

        $hotels = $cachedData['hotels'] ?? [];
        $totalHotels = count($hotels);

        if ($totalHotels === 0) {
            $this->error('No hotels found in cache.');

            return Command::FAILURE;
        }

        $this->info("Found {$totalHotels} hotels to enrich in batches of {$limit}.");

        // ── Step 1: Get facility groups (cached 24 hours) ─────────────────
        $facilityGroupsCacheKey = 'hotel_facility_groups';
        $facilityGroupsMap = Cache::get($facilityGroupsCacheKey);

        if (! $facilityGroupsMap) {
            $this->info('Fetching facility groups...');
            $facilityResult = $this->hotel->getFacilityGroups([]);

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
                Cache::put($facilityGroupsCacheKey, $facilityGroupsMap, now()->addHours(24));
                $this->info('Facility groups cached: '.count($facilityGroupsMap).' facilities.');
            } else {
                $this->warn('Failed to fetch facility groups — facilities will be empty.');
                $facilityGroupsMap = [];
            }
        } else {
            $this->info('Facility groups loaded from cache: '.count($facilityGroupsMap).' facilities.');
        }

        // ── Step 2: Loop through all hotels in batches ────────────────────
        $offset = 0;
        $batchNumber = 1;
        $totalBatches = (int) ceil($totalHotels / $limit);

        $bar = $this->output->createProgressBar($totalBatches);
        $bar->start();

        while ($offset < $totalHotels) {
            $slice = array_slice($hotels, $offset, $limit);
            $hotelIds = array_column($slice, 'id');

            // Fetch content for this batch
            $result = $this->hotel->getHotelContent([
                'hotelIds' => $hotelIds,
                'currency' => $cachedData['currency'] ?? 'USD',
                'culture' => 'en-US',
                'contentFields' => 'basic,facilities,images,descriptions',
            ]);

            $contentMap = [];
            if ($result['success']) {
                foreach ($result['data']['hotels'] ?? [] as $content) {
                    $contentId = $content['id'] ?? null;
                    if ($contentId) {
                        $contentMap[$contentId] = $content;
                    }
                }
            }

            // Merge content into this slice
            $enrichedSlice = array_map(function ($hotel) use ($contentMap, $facilityGroupsMap) {
                $id = $hotel['id'] ?? null;
                $content = $contentMap[$id] ?? [];

                $hotelFacilities = [];
                foreach ($content['facilities'] ?? [] as $facilityId) {
                    $fid = (string) $facilityId;
                    if (isset($facilityGroupsMap[$fid])) {
                        $hotelFacilities[] = $facilityGroupsMap[$fid];
                    } elseif (is_array($facilityId) && isset($facilityId['name'])) {
                        $hotelFacilities[] = [
                            'id' => (string) ($facilityId['id'] ?? ''),
                            'name' => $facilityId['name'],
                            'type' => $facilityId['type'] ?? '',
                        ];
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
                    'images' => collect($content['images'] ?? [])
                        ->take(5)
                        ->map(fn ($img) => [
                            'url' => $img['url'] ?? null,
                            'caption' => $img['caption'] ?? null,
                        ])->values()->toArray(),
                    'heroImage' => $content['heroImage'] ?? null,
                    'facilities' => $hotelFacilities,
                    'description' => $content['descriptions'][0]['text'] ?? null,
                    'imageCount' => $content['imageCount'] ?? null,
                ]);
            }, $slice);

            // Splice enriched slice back into hotels array
            array_splice($hotels, $offset, $limit, $enrichedSlice);

            // Save updated hotels back to cache after each batch
            $allEnriched = ($offset + $limit) >= $totalHotels;
            Cache::put($cacheKey, array_merge($cachedData, [
                'hotels' => $hotels,
                'enriched' => $allEnriched,
            ]), now()->addHours(2));

            $offset += $limit;
            $batchNumber++;
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();

        // Update DB status
        HotelSearch::where('token', $token)->update(['status' => 'enriched']);

        $this->info("All {$totalHotels} hotels enriched successfully!");

        return Command::SUCCESS;
    }
}
