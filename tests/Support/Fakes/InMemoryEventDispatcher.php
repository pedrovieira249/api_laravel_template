<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use Core\Shared\Application\Contract\EventDispatcher;
use Core\Shared\Domain\Event\DomainEvent;

final class InMemoryEventDispatcher implements EventDispatcher
{
    /** @var list<DomainEvent> */
    public array $dispatched = [];

    public function dispatch(DomainEvent ...$events): void
    {
        $this->dispatched = [...$this->dispatched, ...$events];
    }

    /**
     * @param  class-string<DomainEvent>  $eventClass
     */
    public function has(string $eventClass): bool
    {
        return array_any($this->dispatched, fn (DomainEvent $event): bool => $event instanceof $eventClass);
    }
}
