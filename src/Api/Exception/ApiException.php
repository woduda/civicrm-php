<?php

namespace Woduda\CiviCRM\Api\Exception;

use Psr\Http\Message\ResponseInterface;
use RuntimeException;

class ApiException extends RuntimeException
{

    public static function fromResponse(ResponseInterface $response)
    {
        $json = json_decode($response->getBody()->getContents(), true);

        throw new static(
            $json['error_message'] ?? 'Unknown Api error',
            $json['error_code'] ?? 0
        );
    }
}
