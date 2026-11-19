<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use Core\Shared\Application\Contract\IdGenerator;
use Core\Shared\Domain\ValueObject\Uuid;

/**
 * Ids determinísticos: 00000000-0000-7000-8000-000000000001, ...02, ...03.
 */
final class SequentialIdGenerator implements IdGenerator
{
    private int $next = 1;

    public function generate(): Uuid
    {
        return Uuid::fromString(sprintf('00000000-0000-7000-8000-%012d', $this->next++));
    }

    public function last(): Uuid
    {
        return Uuid::fromString(sprintf('00000000-0000-7000-8000-%012d', $this->next - 1));
    }
}
