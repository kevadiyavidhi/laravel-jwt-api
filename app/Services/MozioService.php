<?php

namespace App\Services;

use App\Logging\MozioLogger;
use Illuminate\Support\Facades\Http;
use Throwable;

class MozioService
{
    protected string $apiKey;

    protected string $baseUrl;

    protected $mozioService;

    protected MozioLogger $logger;

    public function __construct()
    {
        $this->apiKey = config('mozio.api_key');
        $this->baseUrl = config('mozio.base_url');
        $this->logger = new MozioLogger;

        // Shared HTTP client with common headers
        $this->mozioService = Http::withHeaders([
            'API-KEY' => $this->apiKey,
            'Content-Type' => 'application/json',
        ]);
    }

    public function setContext(array $context): static
    {
        $this->logger->setContext($context);

        return $this;
    }

    /**
     * Search for available ground transportation
     */
    public function searchRide(array $params): array
    {
        $endpoint = "{$this->baseUrl}/search/";

        try {
            $this->logger->logRequest(__FUNCTION__, $endpoint, $params);
            $response = $this->mozioService->post($endpoint, $params);

            if ($response->successful()) {
                $this->logger->logResponse(__FUNCTION__, $endpoint, $response->status(), $response->json());
            } else {
                $this->logger->logFailure(__FUNCTION__, $endpoint, $response->status(), $response->json());
            }

            return [
                'success' => $response->successful(),
                'message' => $response->successful()
                    ? 'Search completed successfully'
                    : ($response->json()['message'] ?? 'Something went wrong'),
                'data' => $response->successful() ? $response->json() : null,
                'error' => $response->successful() ? [] : $response->json(),
            ];

        } catch (Throwable $e) {
            $this->logger->logException(__FUNCTION__, $endpoint, [
                'method' => 'POST', 'payload' => $params,
            ], $e);

            return [
                'status' => 500,
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Poll search results using the search_id from search()
     */
    public function searchResults(string $searchId): array
    {
        $endpoint = "{$this->baseUrl}/search/{$searchId}/poll/";

        try {
            $this->logger->logRequest(__FUNCTION__, $endpoint);

            $response = $this->mozioService->get($endpoint);

            if ($response->successful()) {
                $this->logger->logResponse(__FUNCTION__, $endpoint, $response->status(), $response->json());
            } else {
                $this->logger->logFailure(__FUNCTION__, $endpoint, $response->status(), $response->json());
            }

            return [
                'success' => $response->successful(),
                'message' => $response->successful()
                    ? 'Search completed successfully'
                    : ($response->json()['message'] ?? 'Something went wrong'),
                'data' => $response->successful() ? $response->json() : null,
                'error' => $response->successful() ? [] : $response->json(),
            ];

        } catch (Throwable $e) {
            $this->logger->logException(__FUNCTION__, $endpoint, [
                'method' => 'GET',
                'payload' => [],
            ], $e);

            return [
                'status' => 500,
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Reserve a ride (booking)
     */
    public function createReservation(array $params): array
    {
        $endpoint = "{$this->baseUrl}/reservations/";

        try {
            $this->logger->logRequest(__FUNCTION__, $endpoint, $params);

            $response = $this->mozioService->post($endpoint, $params);

            if ($response->successful()) {
                $this->logger->logResponse(__FUNCTION__, $endpoint, $response->status(), $response->json());
            } else {
                $this->logger->logFailure(__FUNCTION__, $endpoint, $response->status(), $response->json());
            }

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful()
                    ? 'Reservation created successfully'
                    : ($response->json()['message'] ?? 'Reservation failed'),
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
     * Poll reservation status using the search_id
     */
    public function reservationPoll(string $searchId): array
    {
        $endpoint = "{$this->baseUrl}/reservations/{$searchId}/poll/";

        try {
            $this->logger->logRequest(__FUNCTION__, $endpoint);

            $response = $this->mozioService->get($endpoint);

            if ($response->successful()) {
                $this->logger->logResponse(__FUNCTION__, $endpoint, $response->status(), $response->json());
            } else {
                $this->logger->logFailure(__FUNCTION__, $endpoint, $response->status(), $response->json());
            }

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful()
                    ? 'Reservation details fetched successfully'
                    : ($response->json()['message'] ?? 'Reservation poll failed'),
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

    /**
     * Get all available amenities
     */
    public function getAmenities(): array
    {
        $endpoint = "{$this->baseUrl}/amenities/";

        try {
            $this->logger->logRequest(__FUNCTION__, $endpoint);

            $response = $this->mozioService->get($endpoint);

            if ($response->successful()) {
                $this->logger->logResponse(__FUNCTION__, $endpoint, $response->status(), $response->json());
            } else {
                $this->logger->logFailure(__FUNCTION__, $endpoint, $response->status(), $response->json());
            }

            return [
                'success' => $response->successful(),
                'message' => $response->successful()
                    ? 'Amenities fetched successfully'
                    : ($response->json()['message'] ?? 'Failed to fetch amenities'),
                'data' => $response->successful() ? $response->json() : null,
                'error' => $response->successful() ? [] : $response->json(),
            ];

        } catch (Throwable $e) {
            $this->logger->logException(__FUNCTION__, $endpoint, [
                'method' => 'GET',
                'payload' => [],
            ], $e);

            return [
                'status' => 500,
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Get pricing for a ride with optional amenities
     */
    public function pricing(array $params): array
    {
        $endpoint = "{$this->baseUrl}/pricing/";

        try {
            $this->logger->logRequest(__FUNCTION__, $endpoint, $params);

            $response = $this->mozioService->post($endpoint, $params);

            if ($response->successful()) {
                $this->logger->logResponse(__FUNCTION__, $endpoint, $response->status(), $response->json());
            } else {
                $this->logger->logFailure(__FUNCTION__, $endpoint, $response->status(), $response->json());
            }

            return [
                'success' => $response->successful(),
                'message' => $response->successful()
                    ? 'Pricing fetched successfully'
                    : ($response->json()['message'] ?? 'Pricing fetch failed'),
                'data' => $response->successful() ? $response->json() : null,
                'error' => $response->successful() ? [] : $response->json(),
            ];

        } catch (Throwable $e) {
            $this->logger->logException(__FUNCTION__, $endpoint, [
                'method' => 'POST',
                'payload' => $params,
            ], $e);

            return [
                'status' => 500,
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $e->getMessage(),
                'data' => null,
            ];
        }
    }

    /**
     * Cancel Reservation
     */
    public function cancelReservation(string $reservationId): array
    {
        $endpoint = "{$this->baseUrl}/reservations/{$reservationId}/";

        try {
            $this->logger->logRequest(__FUNCTION__, $endpoint);

            $response = $this->mozioService->delete($endpoint);

            if ($response->successful()) {
                $this->logger->logResponse(__FUNCTION__, $endpoint, $response->status(), $response->json());
            } else {
                $this->logger->logFailure(__FUNCTION__, $endpoint, $response->status(), $response->json());
            }

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful()
                    ? 'Reservation cancelled successfully'
                    : ($response->json()['message'] ?? 'Cancellation failed'),
                'data' => $response->successful() ? $response->json() : null,
                'error' => $response->successful() ? [] : $response->json(),
            ];

        } catch (Throwable $e) {
            $this->logger->logException(__FUNCTION__, $endpoint, [
                'method' => 'DELETE',
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
