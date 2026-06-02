<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Exception;

use Throwable;

/**
 * Marker interface implemented by every exception thrown by this library.
 *
 * Lets consumers catch anything originating from the client with a single
 * `catch (CivicrmException $e)` regardless of the concrete type.
 */
interface CivicrmException extends Throwable {}
