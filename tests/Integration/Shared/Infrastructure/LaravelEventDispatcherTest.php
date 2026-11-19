<?php

declare(strict_types=1);

use Core\Shared\Application\Contract\EventDispatcher;
use Core\Shared\Application\Contract\TransactionManager;
use Core\Shared\Domain\Event\DomainEvent;
use Core\Shared\Domain\ValueObject\Uuid;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    $this->event = new class implements DomainEvent
    {
        public function occurredOn(): DateTimeImmutable
        {
            return new DateTimeImmutable;
        }

        public function aggregateId(): Uuid
        {
            return Uuid::fromString('019a5c6e-7b3a-7c1d-8f2e-3a4b5c6d7e8f');
        }
    };
});

it('dispatches immediately outside a transaction', function (): void {
    Event::fake();

    $this->app->make(EventDispatcher::class)->dispatch($this->event);

    Event::assertDispatched($this->event::class);
});

it('defers dispatching until the transaction commits', function (): void {
    Event::fake();
    $dispatcher = $this->app->make(EventDispatcher::class);
    $event = $this->event;

    $this->app->make(TransactionManager::class)->run(function () use ($dispatcher, $event): void {
        $dispatcher->dispatch($event);

        Event::assertNotDispatched($event::class);
    });

    Event::assertDispatched($event::class);
});

it('drops the events when the transaction rolls back', function (): void {
    Event::fake();
    $dispatcher = $this->app->make(EventDispatcher::class);
    $event = $this->event;

    try {
        $this->app->make(TransactionManager::class)->run(function () use ($dispatcher, $event): never {
            $dispatcher->dispatch($event);

            throw new RuntimeException('boom');
        });
    } catch (RuntimeException) {
    }

    Event::assertNotDispatched($event::class);
});
