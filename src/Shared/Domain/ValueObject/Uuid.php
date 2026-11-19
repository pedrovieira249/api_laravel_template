<?php

declare(strict_types=1);

namespace Core\Shared\Domain\ValueObject;

use Core\Shared\Domain\Exception\InvalidUuid;

final readonly class Uuid implements \Stringable
{
    private const string PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/';

    private function __construct(public string $value) {}

    /**
     * @throws InvalidUuid
     */
    public static function fromString(string $value): self
    {
        $normalized = strtolower($value);

        if (preg_match(self::PATTERN, $normalized) !== 1) {
            throw InvalidUuid::fromString($value);
        }

        return new self($normalized);
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
