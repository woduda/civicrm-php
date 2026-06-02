<?php

declare(strict_types=1);

/**
 * Connectivity smoke test against a live CiviCRM APIv4 instance.
 *
 * Usage:
 *   CIVICRM_BASE_URL="https://example.org/civicrm/ajax/api4/" \
 *   CIVICRM_API_KEY="your-api-key" \
 *   php bin/smoke-test.php [Entity]
 *
 * Requires a real PSR-18 client to be installed, e.g.:
 *   composer require --dev guzzlehttp/guzzle
 *
 * Probes the read-only `getFields` action (no data or write permission needed).
 */

require __DIR__ . '/../vendor/autoload.php';

use Psr\Http\Client\ClientExceptionInterface;
use Woduda\CiviCRM\Client;
use Woduda\CiviCRM\Config;
use Woduda\CiviCRM\Exception\ApiException;

$baseUrl = getenv('CIVICRM_BASE_URL') ?: '';
$apiKey = getenv('CIVICRM_API_KEY') ?: '';
$entity = $argv[1] ?? 'Contact';

if ($baseUrl === '' || $apiKey === '') {
    fwrite(STDERR, "Set CIVICRM_BASE_URL and CIVICRM_API_KEY environment variables.\n");
    exit(2);
}

echo "Probing {$entity}/getFields at {$baseUrl}\n";

try {
    $client = new Client(new Config(baseUrl: $baseUrl, apiKey: $apiKey));
    $result = $client->sendRequest("{$entity}/getFields", []);

    printf("PASS — connected and authenticated. %d field definitions returned.\n", $result->count);
    exit(0);
} catch (ApiException $e) {
    fwrite(STDERR, sprintf(
        "FAIL — CiviCRM returned an API error (code %d): %s\n"
        . "Check the API key, the authx extension, and the user's permissions.\n",
        $e->getCode(),
        $e->getMessage(),
    ));
    exit(1);
} catch (ClientExceptionInterface $e) {
    fwrite(STDERR, sprintf(
        "FAIL — transport error: %s\n"
        . "Check the base URL, DNS/TLS, and network connectivity.\n",
        $e->getMessage(),
    ));
    exit(1);
} catch (\Throwable $e) {
    fwrite(STDERR, sprintf(
        "FAIL — %s: %s\n"
        . "If this mentions PSR-18 discovery, install an HTTP client: composer require --dev guzzlehttp/guzzle\n",
        $e::class,
        $e->getMessage(),
    ));
    exit(1);
}
