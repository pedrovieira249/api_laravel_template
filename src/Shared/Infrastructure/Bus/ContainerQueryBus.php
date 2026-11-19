<?php

declare(strict_types=1);

namespace Core\Shared\Infrastructure\Bus;

use Core\Shared\Application\Bus\Query;
use Core\Shared\Application\Bus\QueryBus;
use Illuminate\Contracts\Container\Container;

final readonly class ContainerQueryBus implements QueryBus
{
    public function __construct(
        private Container $container,
        private HandlerResolver $resolver,
    ) {}

    /**
     * @template TResult
     *
     * @param  Query<TResult>  $query
     * @return TResult
     */
    public function ask(Query $query): mixed
    {
        $handler = $this->container->make($this->resolver->resolve($query::class));

        return $handler($query);
    }
}
