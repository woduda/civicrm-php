<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Query;

/**
 * Logical conjunction used to combine `where` clauses.
 */
enum Conjunction: string
{
    case And_ = 'AND';
    case Or_ = 'OR';
}
