<?php

declare(strict_types=1);

/**
 * Connectivity smoke test against a live CiviCRM APIv4 instance.
 *
 * Fetches and prints every contact of a contact sub-type (default: "Wolontariusz"),
 * exercising the typed query builder end-to-end.
 *
 * Usage:
 *   CIVICRM_BASE_URL="https://example.org/civicrm/ajax/api4/" \
 *   CIVICRM_API_KEY="your-api-key" \
 *   php bin/smoke-test.php ["Contact sub-type"]
 *
 * The sub-type may also be passed via the CIVICRM_CONTACT_SUBTYPE environment variable.
 *
 * Requires a real PSR-18 client to be installed, e.g.:
 *   composer require --dev guzzlehttp/guzzle
 */

require __DIR__ . '/../vendor/autoload.php';

use Psr\Http\Client\ClientExceptionInterface;
use Woduda\CiviCRM\CiviCrmClient;
use Woduda\CiviCRM\Config;
use Woduda\CiviCRM\Exception\ApiErrorException;
use Woduda\CiviCRM\Query\GetQuery;
use Woduda\CiviCRM\Query\Operator;

$baseUrl = getenv('CIVICRM_BASE_URL') ?: '';
$apiKey = getenv('CIVICRM_API_KEY') ?: '';
$subType = $argv[1] ?? (getenv('CIVICRM_CONTACT_SUBTYPE') ?: 'Wolontariusz');

if ($baseUrl === '' || $apiKey === '') {
    fwrite(STDERR, "Set CIVICRM_BASE_URL and CIVICRM_API_KEY environment variables.\n");
    exit(2);
}

echo "Fetching contacts of sub-type \"{$subType}\" from {$baseUrl}\n";

$query = GetQuery::new()
    ->select('id', 'display_name', 'email_primary.email')
    ->where('contact_sub_type', Operator::Equals, $subType)
    ->orderBy('display_name');

try {
    $client = CiviCrmClient::create(new Config(baseUrl: $baseUrl, apiKey: $apiKey));
    $contacts = $client->entity('Contact')->get($query);

    printf("PASS — connected and authenticated. %d contact(s) found.\n", count($contacts));

    foreach ($contacts as $contact) {
        if (! is_array($contact)) {
            continue;
        }

        printf(
            "  #%s  %s  %s\n",
            (string) ($contact['id'] ?? '?'),
            (string) ($contact['display_name'] ?? '(no name)'),
            (string) ($contact['email_primary.email'] ?? ''),
        );
    }

    exit(0);
} catch (ApiErrorException $e) {
    fwrite(STDERR, sprintf(
        "FAIL — CiviCRM returned an API error (code %d): %s\n"
        . "Check the API key, the authx extension, the user's permissions, and that the sub-type exists.\n",
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
