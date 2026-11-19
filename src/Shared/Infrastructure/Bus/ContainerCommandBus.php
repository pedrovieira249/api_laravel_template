<?php

declare(strict_types=1);

namespace Core\Shared\Infrastructure\Bus;

use Core\Shared\Application\Bus\Command;
use Core\Shared\Application\Bus\CommandBus;
use Illuminate\Contracts\Container\Container;

/**
 * O bus mais simples possível: resolve o handler pelo service container
 * (para que seus ports sejam injetados) e o invoca. Sem middleware, sem fila;
 * um command que precisa rodar de forma assíncrona é embrulhado em um Job pelo chamador.
 */
final readonly class ContainerCommandBus implements CommandBus
{
    public function __construct(
        private Container $container,
        private HandlerResolver $resolver,
    ) {}

    /**
     * @template TResult
     *
     * @param  Command<TResult>  $command
     * @return TResult
     */
    public function dispatch(Command $command): mixed
    {
        $handler = $this->container->make($this->resolver->resolve($command::class));

        return $handler($command);
    }
}
