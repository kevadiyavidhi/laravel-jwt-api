<?php

namespace App\Logging;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

class HotelNexusLogger
{
    protected Logger $logger;

    protected array $context = [];

    public function __construct()
    {
        $this->logger = new Logger('hotel');

        $formatter = new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            'Y-m-d H:i:s',
            true,
            true
        );

        $infoHandler = new StreamHandler(
            storage_path('logs/Hotel/hotel-'.now()->format('Y-m-d').'.log'),
            Level::Info
        );
        $infoHandler->setFormatter($formatter);

        $errorHandler = new StreamHandler(
            storage_path('logs/Hotel/hotel-error-'.now()->format('Y-m-d').'.log'),
            Level::Error
        );
        $errorHandler->setFormatter($formatter);

        $this->logger->pushHandler($infoHandler);
        $this->logger->pushHandler($errorHandler);
    }

    public function setContext(array $context): static
    {
        $this->context = $context;

        return $this;
    }

    private function mergeContext(array $data): array
    {
        return array_merge($this->context, $data);
    }

    public function logRequest(string $methodName, string $endpoint, array $params = []): void
    {
        $this->logger->info("Hotel {$methodName} API Request", $this->mergeContext([
            'endpoint' => $endpoint,
            'params' => $params,
        ]));
    }

    public function logResponse(string $methodName, string $endpoint, int $statusCode, array $data = []): void
    {
        $this->logger->info("Hotel {$methodName} API Response", $this->mergeContext([
            'endpoint' => $endpoint,
            'status' => $statusCode,
            'response' => $data,
        ]));
    }

    public function logFailure(string $methodName, string $endpoint, int $statusCode, mixed $error = null): void
    {
        $this->logger->error("Hotel {$methodName} API Failed", $this->mergeContext([
            'endpoint' => $endpoint,
            'status' => $statusCode,
            'error' => $error,
        ]));
    }

    public function logException(string $methodName, string $endpoint, array $requestData, \Throwable $e): void
    {
        $this->logger->error("Hotel {$methodName} API Exception", $this->mergeContext([
            'url' => $endpoint,
            'method' => $requestData['method'] ?? null,
            'payload' => $requestData['payload'] ?? [],
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ]));
    }
}
