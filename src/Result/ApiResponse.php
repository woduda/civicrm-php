<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Result;

use Psr\Http\Message\ResponseInterface;

/**
 * Immutable value object encapsulating a successful CiviCRM APIv4 response.
 */
final readonly class ApiResponse
{
    /**
     * @param int                       $version APIv4 version reported by CiviCRM
     * @param int                       $count   Number of returned records
     * @param array<array-key, mixed>   $values  Returned records
     */
    public function __construct(
        public int $version,
        public int $count,
        public array $values,
    ) {}

    /**
     * Creates an ApiResponse from a PSR-7 response body.
     */
    public static function fromResponse(ResponseInterface $response): self
    {
        $json = json_decode($response->getBody()->getContents(), true);

        if (! is_array($json)) {
            $json = [];
        }

        return new self(
            version: isset($json['version']) && is_int($json['version']) ? $json['version'] : 4,
            count: isset($json['count']) && is_int($json['count']) ? $json['count'] : 0,
            values: isset($json['values']) && is_array($json['values']) ? $json['values'] : [],
        );
    }
}
