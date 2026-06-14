<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Http;

use Closure;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Log\LoggerInterface;
use Woduda\CiviCRM\Client;
use Woduda\CiviCRM\Config;
use Woduda\CiviCRM\Contract\TransportInterface;
use Woduda\CiviCRM\Exception\ApiException;
use Woduda\CiviCRM\Exception\TransportException;
use Woduda\CiviCRM\Result\ApiResponse;
use Woduda\CiviCRM\Retry\NoRetry;
use Woduda\CiviCRM\Retry\RetryStrategy;

/**
 * Default PSR-18 transport — delegates to {@see Client}, adding optional
 * retry/back-off and PSR-3 logging.
 */
final readonly class Transport implements TransportInterface
{
    /** @var Closure(int): void */
    private Closure $sleeper;

    /**
     * @param RetryStrategy        $retry   Retry policy; defaults to {@see NoRetry} (single attempt)
     * @param LoggerInterface|null $logger  Optional PSR-3 logger; receives redacted entries only
     * @param Closure(int): void|null $sleeper Injectable sleeper (milliseconds); defaults to usleep
     */
    public function __construct(
        private Client $httpClient,
        private RetryStrategy $retry = new NoRetry(),
        private ?LoggerInterface $logger = null,
        ?Closure $sleeper = null,
    ) {
        $this->sleeper = $sleeper ?? static function (int $ms): void {
            if ($ms > 0) {
                usleep($ms * 1000);
            }
        };
    }

    /**
     * Creates a Transport wired to a new auto-discovered PSR-18 HTTP client.
     */
    public static function createDefault(
        Config $config,
        ?RetryStrategy $retry = null,
        ?LoggerInterface $logger = null,
    ): self {
        return new self(new Client($config), $retry ?? new NoRetry(), $logger);
    }

    /**
     * @param  array<string, mixed> $params
     * @throws ApiException       On HTTP 4xx/5xx responses
     * @throws TransportException On transport-level (network) errors
     */
    public function send(string $entity, string $action, array $params = []): ApiResponse
    {
        $attempt = 1;

        while (true) {
            $this->logger?->debug('CiviCRM API request', [
                'entity' => $entity,
                'action' => $action,
                'attempt' => $attempt,
                'params' => $this->redact($params),
            ]);

            try {
                return $this->httpClient->sendRequest($entity . '/' . $action, $params);
            } catch (ClientExceptionInterface $e) {
                $error = TransportException::fromThrowable($e);
            } catch (ApiException $e) {
                $error = $e;
            }

            if (! $this->retry->shouldRetry($attempt, $error)) {
                $this->logger?->error('CiviCRM API request failed', [
                    'entity' => $entity,
                    'action' => $action,
                    'attempt' => $attempt,
                    'exception' => $error::class,
                    'http_status' => $error instanceof ApiException ? $error->httpStatus : null,
                ]);

                throw $error;
            }

            $delayMs = $this->retry->delayMs($attempt, $error);

            $this->logger?->warning('CiviCRM API retry', [
                'entity' => $entity,
                'action' => $action,
                'attempt' => $attempt,
                'delay_ms' => $delayMs,
                'exception' => $error::class,
                'http_status' => $error instanceof ApiException ? $error->httpStatus : null,
            ]);

            ($this->sleeper)($delayMs);
            $attempt++;
        }
    }

    /**
     * Strips sensitive payloads from request params before logging.
     *
     * The `values` payload (which may carry personal data) is masked. Auth
     * credentials live in {@see Client}'s headers and never reach this array.
     *
     * @param  array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function redact(array $params): array
    {
        if (array_key_exists('values', $params)) {
            $params['values'] = '[REDACTED]';
        }

        return $params;
    }
}
