<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Exception;

/**
 * Thrown when CiviCRM rejects the request's credentials (HTTP 401/403).
 *
 * Never retried — a fresh token or key is required, not another attempt.
 */
final class AuthenticationException extends ApiErrorException {}
