<?php

declare(strict_types=1);

namespace Woduda\CiviCRM\Query;

/**
 * Comparison operators supported by CiviCRM APIv4 `where`/`having` clauses.
 */
enum Operator: string
{
    case Equals = '=';
    case NotEquals = '!=';
    case GreaterThan = '>';
    case LessThan = '<';
    case GreaterOrEqual = '>=';
    case LessOrEqual = '<=';
    case Like = 'LIKE';
    case NotLike = 'NOT LIKE';
    case In = 'IN';
    case NotIn = 'NOT IN';
    case Between = 'BETWEEN';
    case NotBetween = 'NOT BETWEEN';
    case IsNull = 'IS NULL';
    case IsNotNull = 'IS NOT NULL';
    case Contains = 'CONTAINS';

    /**
     * Whether the operator takes a value operand.
     *
     * `IS NULL` / `IS NOT NULL` are unary and produce a two-element clause.
     *
     * Example:
     * ```php
     * Operator::Equals->requiresValue();  // true
     * Operator::IsNull->requiresValue();  // false
     * ```
     */
    public function requiresValue(): bool
    {
        return match ($this) {
            self::IsNull, self::IsNotNull => false,
            default => true,
        };
    }
}
