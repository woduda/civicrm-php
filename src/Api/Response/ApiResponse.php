<?php

namespace Woduda\CiviCRM\Api\Response;

use Psr\Http\Message\ResponseInterface;

/**
 * Basic class encapsulating response from CiviCRM API
 */
class ApiResponse
{
    /**
     * @param integer $version
     * @param integer $count
     * @param array $values
     */
    public function __construct(
        public int $version,
        public int $count,
        public array $values,
    ) {}

    /**
     * Creates ApiResponse from API Response object
     *
     * @param ResponseInterface $response
     * @return ApiResponse
     */
    public static function fromResponse(ResponseInterface $response): ApiResponse
    {
        $json = json_decode($response->getBody()->getContents(), true);

        return new static(
            version: $json['version'] ?? 4,
            count: $json['count'] ?? 0,
            values: $json['values'] ?? [],
        );
    }
}
