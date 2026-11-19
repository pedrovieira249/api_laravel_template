<?php

declare(strict_types=1);

namespace Core\Shared\Application\Contract;

use Core\Shared\Domain\Event\DomainEvent;

/**
 * Port de saída (driven): publica os eventos que um agregado registrou. O adapter
 * Laravel adia a publicação até o commit da transação envolvente.
 */
interface EventDispatcher
{
    public function dispatch(DomainEvent ...$events): void;
}
