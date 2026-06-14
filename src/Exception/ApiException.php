<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Exception;

/*
 * Backwards-compatibility alias for the renamed {@see ApiErrorException}.
 *
 * @deprecated since 0.8.0; use {@see ApiErrorException} instead. The alias keeps
 *             existing `catch (ApiException $e)` blocks and `instanceof` checks
 *             working — both names resolve to the same class — and will be
 *             removed in 1.0.
 *
 * class_alias() autoloads the target class (third argument defaults to true),
 * so referencing ApiException alone is enough to load ApiErrorException.
 */
class_alias(
    \Woduda\CiviCRM\Exception\ApiErrorException::class,
    'Woduda\\CiviCRM\\Exception\\ApiException',
);
