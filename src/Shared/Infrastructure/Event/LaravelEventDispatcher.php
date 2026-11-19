<?php

declare(strict_types=1);

namespace Core\Shared\Infrastructure\Event;

use Core\Shared\Application\Contract\EventDispatcher;
use Core\Shared\Domain\Event\DomainEvent;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;

/**
 * Faz a ponte entre os eventos de domínio e o sistema de eventos do Laravel.
 *
 * Cada evento é entregue ao hook afterCommit() da conexão: dentro de uma
 * transação ele espera o commit (um listener nunca deve observar dados que
 * ainda podem sofrer rollback, ADR 0005); fora de uma, roda imediatamente.
 * Listeners são listeners comuns do Laravel com type-hint da classe do evento de domínio.
 */
final readonly class LaravelEventDispatcher implements EventDispatcher
{
    public function __construct(
        private Dispatcher $events,
        private DatabaseManager $connections,
    ) {}

    public function dispatch(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->connections->connection()->afterCommit(fn () => $this->events->dispatch($event));
        }
    }
}
