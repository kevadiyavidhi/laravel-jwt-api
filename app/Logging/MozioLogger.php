<?php

namespace App\Logging;

use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;

class MozioLogger
{
    protected Logger $logger;

    protected array $context = [];

    public function __construct()
    {
        // Create a named  MonologLogger instance for Mozio API calls
        $this->logger = new Logger('mozio');

        // Define the log file path: storage/logs/Mozio/mozio-YYYY-MM-DD.log
        $logPath = storage_path('logs/Mozio/mozio-'.now()->format('Y-m-d').'.log');

        $formatter = new LineFormatter(
            "[%datetime%] %channel%.%level_name%: %message% %context% %extra%\n",
            'Y-m-d H:i:s',
            true,   // allow inline line breaks
            true    // ignore empty context
        );

        // INFO and above → daily log file
        $infoHandler = new StreamHandler($logPath, Level::Info);
        $infoHandler->setFormatter($formatter);

        // ERROR and above → separate error log file for quick debugging
        $errorLogPath = storage_path('logs/Mozio/mozio-error-'.now()->format('Y-m-d').'.log');
        $errorHandler = new StreamHandler($errorLogPath, Level::Error);
        $errorHandler->setFormatter($formatter);

        // Push handlers onto the logger stack
        // Monolog processes handlers in LIFO order (last pushed = first checked)
        $this->logger->pushHandler($infoHandler);
        $this->logger->pushHandler($errorHandler);
    }

    public function setContext(array $context): void
    {
        $this->context = $context;
    }

    private function mergeContext(array $data): array
    {
        return array_merge($this->context, $data);
    }

    /**
     * Log an outgoing Mozio API request
     */
    public function logRequest(string $methodName, string $endpoint, array $params = []): void
    {
        $this->logger->info("Mozio {$methodName} API Request", $this->mergeContext([
            'endpoint' => $endpoint,
            'params' => $params,
        ]));
    }

    /**
     * Log a successful Mozio API response
     */
    public function logResponse(string $methodName, string $endpoint, int $statusCode, array $data = []): void
    {
        $this->logger->info("Mozio {$methodName} API Response", $this->mergeContext([
            'endpoint' => $endpoint,
            'status' => $statusCode,
            'response' => $data,
        ]));
    }

    /**
     * Log a failed Mozio API response (non-2xx)
     */
    public function logFailure(string $methodName, string $endpoint, int $statusCode, mixed $error = null): void
    {
        $this->logger->error("Mozio {$methodName} API Failed", $this->mergeContext([
            'endpoint' => $endpoint,
            'status' => $statusCode,
            'error' => $error,
        ]));
    }

    /**
     * Log an exception thrown during an API call
     */
    public function logException(string $methodName, string $endpoint, array $requestData, \Throwable $e): void
    {
        $this->logger->error("Mozio {$methodName} API Exception", $this->mergeContext([
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
