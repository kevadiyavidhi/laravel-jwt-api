<?php

namespace App\Services;

use App\Logging\HotelNexusLogger;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class HotelNexusService
{
    protected string $apiKey;

    protected string $accountId;

    protected string $channelId;

    protected string $baseUrl;

    protected string $contentUrl;

    protected HotelNexusLogger $logger;

    public function __construct()
    {
        $this->apiKey = config('hotelnexus.api_key');
        $this->accountId = config('hotelnexus.account_id');
        $this->channelId = config('hotelnexus.channel_id');
        $this->baseUrl = config('hotelnexus.base_url');
        $this->contentUrl = config('hotelnexus.content_url');
        $this->logger = new HotelNexusLogger;
    }

    public function setContext(array $context): static
    {
        $this->logger->setContext($context);

        return $this;
    }

    /**
     * Build common headers — sent on every request
     * correlationId is a unique UUID per request
     */
    private function headers(string $customerIp = '127.0.0.1'): array
    {
        return [
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept-Encoding' => 'gzip, deflate',
            'apiKey' => $this->apiKey,
            'accountId' => $this->accountId,
            'correlationId' => (string) Str::uuid(),
            'customer-ip' => $customerIp,
        ];
    }

    /**
     * Search hotel locations by name (autosuggest)
     * Used before availability init to get lat/lng/id for a location
     */
    public function locationSearch(string $term, string $customerIp = '127.0.0.1'): array
    {
        $endpoint = 'https://autosuggest-v2.us.prod.zentrumhub.com/api/locations/locationcontent/autosuggest';

        try {
            $this->logger->logRequest(__FUNCTION__, $endpoint, ['term' => $term]);

            $response = Http::withHeaders([
                'accountId' => $this->accountId,
                'apiKey' => $this->apiKey,
            ])->get($endpoint, ['term' => $term]);

            if ($response->successful()) {
                $this->logger->logResponse(__FUNCTION__, $endpoint, $response->status(), $response->json());
            } else {
                $this->logger->logFailure(__FUNCTION__, $endpoint, $response->status(), $response->json());
            }

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful()
                    ? 'Locations fetched successfully.'
                    : ($response->json()['message'] ?? 'Failed to fetch locations.'),
                'data' => $response->successful() ? $response->json() : null,
                'error' => $response->successful() ? [] : $response->json(),
            ];

        } catch (Throwable $e) {
            $this->logger->logException(__FUNCTION__, $endpoint, [
                'method' => 'GET',
                'payload' => ['term' => $term],
            ], $e);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
                'data' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get Location Details
     * GET {locationBaseUrl}/api/location/{id}
     * Only for city, multiCity, region, neighbourhood types
     * Returns polygon boundary to pass into Search Init
     */
    public function locationDetails(string $locationId, string $customerIp = '127.0.0.1'): array
    {
        $locationBaseUrl = config('hotelnexus.location_url',
            'https://autosuggest-v2.us.prod.zentrumhub.com');

        $endpoint = "{$locationBaseUrl}/api/locations/LocationContent/location/{$locationId}";

        try {
            $this->logger->logRequest(__FUNCTION__, $endpoint, ['locationId' => $locationId]);

            $response = Http::withHeaders([
                'accept' => 'application/json',
                'accountId' => $this->accountId,
                'apiKey' => $this->apiKey,
            ])->get($endpoint);

            if ($response->successful()) {
                $this->logger->logResponse(__FUNCTION__, $endpoint, $response->status(), $response->json());
            } else {
                $this->logger->logFailure(__FUNCTION__, $endpoint, $response->status(), $response->json());
            }

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful()
                    ? 'Location details fetched successfully.'
                    : ($response->json()['message'] ?? 'Failed to fetch location details.'),
                'data' => $response->successful() ? $response->json() : null,
                'error' => $response->successful() ? [] : $response->json(),
            ];

        } catch (Throwable $e) {
            $this->logger->logException(__FUNCTION__, $endpoint, [
                'method' => 'GET',
                'payload' => ['locationId' => $locationId],
            ], $e);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
                'data' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * CONTENT API — Get Hotel Content
     * POST https://nexus.prod.zentrumhub.com/api/content/hotelcontent/getHotelContent
     * Used to get hotel details, images, facilities etc.
     */
    public function getHotelContent(array $params, string $customerIp = '127.0.0.1'): array
    {
        $endpoint = 'https://nexus.prod.zentrumhub.com/api/content/HotelContent/getHotelContent';

        $payload = array_merge($params, [
            'channelId' => $this->channelId,
        ]);

        try {
            $this->logger->logRequest(__FUNCTION__, $endpoint, $payload);

            $response = Http::withHeaders($this->headers($customerIp))
                ->post($endpoint, $payload);

            if ($response->successful()) {
                $this->logger->logResponse(__FUNCTION__, $endpoint, $response->status(), $response->json());
            } else {
                $this->logger->logFailure(__FUNCTION__, $endpoint, $response->status(), $response->json());
            }

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful()
                    ? 'Hotel content fetched successfully.'
                    : ($response->json()['message'] ?? 'Failed to fetch hotel content.'),
                'data' => $response->successful() ? $response->json() : null,
                'error' => $response->successful() ? [] : $response->json(),
            ];

        } catch (Throwable $e) {
            $this->logger->logException(__FUNCTION__, $endpoint, [
                'method' => 'POST',
                'payload' => $params,
            ], $e);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
                'data' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function getFacilityGroups(array $params, string $customerIp = '127.0.0.1'): array
    {
        $endpoint = 'https://nexus.prod.zentrumhub.com/api/content/HotelContent/getFacilityGroups';

        try {
            $this->logger->logRequest(__FUNCTION__, $endpoint, $params);

            $response = Http::withHeaders([
                'accept' => 'application/json',
                'accountId' => $this->accountId,
                'apiKey' => $this->apiKey,
            ])->get($endpoint);

            if ($response->successful()) {
                $this->logger->logResponse(__FUNCTION__, $endpoint, $response->status(), $response->json());
            } else {
                $this->logger->logFailure(__FUNCTION__, $endpoint, $response->status(), $response->json());
            }

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful()
                    ? 'Hotel content fetched successfully.'
                    : ($response->json()['message'] ?? 'Failed to fetch hotel content.'),
                'data' => $response->successful() ? $response->json() : null,
                'error' => $response->successful() ? [] : $response->json(),
            ];

        } catch (Throwable $e) {
            $this->logger->logException(__FUNCTION__, $endpoint, [
                'method' => 'GET',
                'payload' => $params,
            ], $e);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
                'data' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function availability(array $params, string $customerIp = '127.0.0.1'): array
    {
        $endpoint = "{$this->baseUrl}/availability";

        $params['channelId'] = $this->channelId;

        try {
            $this->logger->logRequest(__FUNCTION__, $endpoint, $params);

            $response = Http::withHeaders($this->headers($customerIp))
                ->post($endpoint, $params);

            if ($response->successful()) {
                $this->logger->logResponse(__FUNCTION__, $endpoint, $response->status(), $response->json());
            } else {
                $this->logger->logFailure(__FUNCTION__, $endpoint, $response->status(), $response->json());
            }

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful()
                    ? 'Availability fetched successfully.'
                    : ($response->json()['message'] ?? 'Failed to fetch availability.'),
                'data' => $response->successful() ? $response->json() : null,
                'error' => $response->successful() ? [] : $response->json(),
            ];

        } catch (Throwable $e) {
            $this->logger->logException(__FUNCTION__, $endpoint, [
                'method' => 'POST',
                'payload' => $params,
            ], $e);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
                'data' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * STEP 1 — Availability Init
     * The token is used for all subsequent calls in this search session
     */
    public function availabilityInit(array $params, string $customerIp = '127.0.0.1'): array
    {
        $endpoint = "{$this->baseUrl}/availability/init";

        $params['channelId'] = $this->channelId;

        try {
            $this->logger->logRequest(__FUNCTION__, $endpoint, $params);

            $response = Http::withHeaders($this->headers($customerIp))
                ->post($endpoint, $params);

            if ($response->successful()) {
                $this->logger->logResponse(__FUNCTION__, $endpoint, $response->status(), $response->json());
            } else {
                $this->logger->logFailure(__FUNCTION__, $endpoint, $response->status(), $response->json());
            }

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful()
                    ? 'Availability search initiated successfully.'
                    : ($response->json()['message'] ?? 'Failed to initiate availability search.'),
                'data' => $response->successful() ? $response->json() : null,
                'error' => $response->successful() ? [] : $response->json(),
            ];

        } catch (Throwable $e) {
            $this->logger->logException(__FUNCTION__, $endpoint, [
                'method' => 'POST',
                'payload' => $params,
            ], $e);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
                'data' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * STEP 2 & 3 — Availability Async Results
     * GET /availability/async/{token}
     * GET /availability/async/{token}?nextResultsKey={key}
     * Poll until status = "Completed"
     */
    public function availabilityResults(string $token, ?string $nextResultsKey = null, string $customerIp = '127.0.0.1'): array
    {
        $endpoint = "{$this->baseUrl}/availability/async/{$token}/results";

        $query = $nextResultsKey ? ['nextResultsKey' => $nextResultsKey] : [];

        try {
            $this->logger->logRequest(__FUNCTION__, $endpoint, [
                'nextResultsKey' => $nextResultsKey,
            ]);

            $response = Http::withHeaders($this->headers($customerIp))
                ->get($endpoint, $query);

            if ($response->successful()) {
                $this->logger->logResponse(__FUNCTION__, $endpoint, $response->status(), $response->json());
            } else {
                $this->logger->logFailure(__FUNCTION__, $endpoint, $response->status(), $response->json());
            }

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful()
                    ? 'Availability results fetched successfully.'
                    : ($response->json()['message'] ?? 'Failed to fetch availability results.'),
                'data' => $response->successful() ? $response->json() : null,
                'error' => $response->successful() ? [] : $response->json(),
            ];

        } catch (Throwable $e) {
            $this->logger->logException(__FUNCTION__, $endpoint, [
                'method' => 'GET',
                'payload' => ['token' => $token, 'nextResultsKey' => $nextResultsKey],
            ], $e);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
                'data' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * STEP 3 — Rooms and Rates
     */
    public function roomsAndRates(string $hotelId, string $token, array $params = [], string $customerIp = '127.0.0.1'): array
    {
        $endpoint = "{$this->baseUrl}/{$hotelId}/roomsandrates/{$token}";

        $params = array_merge([
            'currency' => 'USD',
            'channelId' => $this->channelId,
        ], $params);

        try {
            $this->logger->logRequest(__FUNCTION__, $endpoint, $params);

            $headers = array_merge($this->headers($customerIp), [
                'Content-Type' => 'application/*+json',
                'Accept' => 'application/json',
            ]);

            $response = Http::withHeaders($headers)
                ->post($endpoint, $params);

            if ($response->successful()) {
                $this->logger->logResponse(__FUNCTION__, $endpoint, $response->status(), $response->json());
            } else {
                $this->logger->logFailure(__FUNCTION__, $endpoint, $response->status(), $response->json());
            }

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful()
                    ? 'Rooms and rates fetched successfully.'
                    : ($response->json()['message'] ?? 'Failed to fetch rooms and rates.'),
                'data' => $response->successful() ? $response->json() : null,
                'error' => $response->successful() ? [] : $response->json(),
            ];

        } catch (Throwable $e) {
            $this->logger->logException(__FUNCTION__, $endpoint, [
                'method' => 'POST',
                'payload' => $params,
            ], $e);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
                'data' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * STEP 4 — Price by Recommendation
     * GET /{hotelId}/{token}/price/recommendation/{recommendationId}
     * Returns: { token, hotel: { rates[] } }
     * rates[] contains rateId, roomId, cardRequired — needed for booking
     */
    public function priceByRecommendation(string $hotelId, string $token, string $recommendationId, string $customerIp = '127.0.0.1'): array
    {
        $endpoint = "{$this->baseUrl}/{$hotelId}/{$token}/price/recommendation/{$recommendationId}";

        try {
            $this->logger->logRequest(__FUNCTION__, $endpoint);

            $response = Http::withHeaders($this->headers($customerIp))
                ->get($endpoint);

            if ($response->successful()) {
                $this->logger->logResponse(__FUNCTION__, $endpoint, $response->status(), $response->json());
            } else {
                $this->logger->logFailure(__FUNCTION__, $endpoint, $response->status(), $response->json());
            }

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful()
                    ? 'Price by recommendation fetched successfully.'
                    : ($response->json()['message'] ?? 'Failed to fetch price by recommendation.'),
                'data' => $response->successful() ? $response->json() : null,
                'error' => $response->successful() ? [] : $response->json(),
            ];

        } catch (Throwable $e) {
            $this->logger->logException(__FUNCTION__, $endpoint, [
                'method' => 'GET',
                'payload' => [],
            ], $e);

            return [
                'success' => false,
                'status' => 500,
                'message' => $e->getMessage(),
                'data' => null,
                'error' => $e->getMessage(),
            ];
        }
    }
}
