<?php

declare(strict_types=1);

namespace Core\Shared\Domain\Exception;

use Core\Shared\Domain\Enum\Currency;

final class CurrencyMismatch extends BusinessRuleViolation
{
    public static function between(Currency $expected, Currency $actual): self
    {
        return new self(sprintf('Currency mismatch: expected %s, got %s', $expected->value, $actual->value));
    }
}
