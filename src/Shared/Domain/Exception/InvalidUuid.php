<?php

declare(strict_types=1);

namespace Core\Shared\Domain\Exception;

final class InvalidUuid extends DomainException
{
    public static function fromString(string $value): self
    {
        return new self(sprintf('Invalid UUID: "%s"', $value));
    }
}
