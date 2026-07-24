<?php

namespace App\Logging;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

class ApiLogger
{
    protected Logger $logger;

    protected array $context = [];

    public function __construct()
    {
        $this->logger = new Logger('api');

        $formatter = new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            'Y-m-d H:i:s',
            true,
            true
        );

        // INFO+ → storage/logs/Api/api-YYYY-MM-DD.log
        $infoHandler = new StreamHandler(
            storage_path('logs/Api/api-'.now()->format('Y-m-d').'.log'),
            Level::Info
        );
        $infoHandler->setFormatter($formatter);

        // ERROR only → storage/logs/Api/api-error-YYYY-MM-DD.log
        $errorHandler = new StreamHandler(
            storage_path('logs/Api/api-error-'.now()->format('Y-m-d').'.log'),
            Level::Error
        );
        $errorHandler->setFormatter($formatter);

        $this->logger->pushHandler($infoHandler);
        $this->logger->pushHandler($errorHandler);
    }

    /**
     * Set shared context (user_id, search_id, etc.) for all subsequent log calls
     */
    public function setContext(array $context): static
    {
        $this->context = $context;

        return $this;
    }

    private function mergeContext(array $data): array
    {
        return array_merge($this->context, $data);
    }

    /**
     * Log an incoming API request
     */
    public function logRequest(string $action, array $data = []): void
    {
        $this->logger->info("{$action} API Request", $this->mergeContext([
            'request' => $data,
        ]));
    }

    /**
     * Log a successful API response
     */
    public function logResponse(string $action, array $data = []): void
    {
        $this->logger->info("{$action} API Response", $this->mergeContext([
            'response' => $data,
        ]));
    }

    /**
     * Log a warning (e.g. failed login, not found, etc.)
     */
    public function logWarning(string $action, string $endpoint, int $statusCode, mixed $error = null): void
    {
        $this->logger->warning("{$action} Warning", $this->mergeContext([
            'endpoint' => $endpoint,
            'status' => $statusCode,
            'error' => $error,
        ]));
    }

    /**
     * Log an exception from a catch block
     */
    public function logException(string $action, \Throwable $e, array $extra = []): void
    {
        $this->logger->error("{$action} Exception", $this->mergeContext(array_merge([
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $e->getTraceAsString(),
        ], $extra)));
    }
}
