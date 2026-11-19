<?php

declare(strict_types=1);

namespace Core\Shared\Infrastructure\Clock;

use Core\Shared\Application\Contract\Clock;

final class SystemClock implements Clock
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable;
    }
}
