<?php

declare(strict_types=1);

use Core\Shared\Domain\Entity\AggregateRoot;
use Core\Shared\Domain\Event\DomainEvent;
use Core\Shared\Domain\ValueObject\Uuid;

beforeEach(function (): void {
    // Subclasse anônima: AggregateRoot é abstrata e record() é protected,
    // então o teste precisa de um agregado concreto que exponha um comportamento.
    $this->aggregate = new class extends AggregateRoot
    {
        public function doSomething(DomainEvent $event): void
        {
            $this->record($event);
        }
    };

    $this->event = fn (): DomainEvent => new class implements DomainEvent
    {
        public function occurredOn(): DateTimeImmutable
        {
            return new DateTimeImmutable('2026-11-09 10:00:00');
        }

        public function aggregateId(): Uuid
        {
            return Uuid::fromString('019a5c6e-7b3a-7c1d-8f2e-3a4b5c6d7e8f');
        }
    };
});

it('starts with no recorded events', function (): void {
    expect($this->aggregate->pullDomainEvents())->toBe([]);
});

it('returns the recorded events in order', function (): void {
    $first = ($this->event)();
    $second = ($this->event)();

    $this->aggregate->doSomething($first);
    $this->aggregate->doSomething($second);

    expect($this->aggregate->pullDomainEvents())->toBe([$first, $second]);
});

it('clears the events once they are pulled', function (): void {
    $this->aggregate->doSomething(($this->event)());

    expect($this->aggregate->pullDomainEvents())->toHaveCount(1)
        ->and($this->aggregate->pullDomainEvents())->toBe([]);
});

it('warns when the pulled events are discarded', function (): void {
    $warning = null;
    set_error_handler(function (int $errno, string $message) use (&$warning): bool {
        $warning = $message;

        return true;
    });

    try {
        $this->aggregate->pullDomainEvents();
    } finally {
        restore_error_handler();
    }

    expect($warning)->toContain('pullDomainEvents()', 'should either be used');
});
