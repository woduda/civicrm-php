<?php

namespace Woduda\CiviCRM\Api\Response;

use Psr\Http\Message\ResponseInterface;

class ApiResponse
{
    public function __construct(
        public int $version,
        public int $count,
        public array $values,
    ) {}

    public static function fromResponse(ResponseInterface $response)
    {
        $json = json_decode($response->getBody()->getContents(), true);

        return new static(
            version: $json['version'] ?? 4,
            count: $json['count'] ?? 0,
            values: $json['values'] ?? [],
        );
    }
}
