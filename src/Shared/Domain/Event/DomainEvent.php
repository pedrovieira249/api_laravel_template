<?php

declare(strict_types=1);

namespace Core\Shared\Domain\Event;

use Core\Shared\Domain\ValueObject\Uuid;

interface DomainEvent
{
    public function occurredOn(): \DateTimeImmutable;

    public function aggregateId(): Uuid;
}
