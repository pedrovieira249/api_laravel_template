<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use Core\Shared\Application\Contract\Clock;

/**
 * Test double no estilo null object: sempre o mesmo instante, para que asserções
 * sobre "placed at" / "expires at" sejam exatas.
 */
final class FrozenClock implements Clock
{
    public function __construct(private \DateTimeImmutable $now = new \DateTimeImmutable('2026-11-09 10:00:00')) {}

    public static function at(string $datetime): self
    {
        return new self(new \DateTimeImmutable($datetime));
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(string $modifier): void
    {
        $this->now = $this->now->modify($modifier);
    }
}
