<?php

declare(strict_types=1);

namespace Core\Shared\Domain\Exception;

final class InvalidEmail extends DomainException
{
    public static function fromString(string $value): self
    {
        return new self(sprintf('Invalid email: "%s"', $value));
    }
}
