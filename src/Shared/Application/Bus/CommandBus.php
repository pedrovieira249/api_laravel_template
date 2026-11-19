<?php

declare(strict_types=1);

namespace Core\Shared\Application\Bus;

interface CommandBus
{
    /**
     * @template TResult
     *
     * @param  Command<TResult>  $command
     * @return TResult
     */
    public function dispatch(Command $command): mixed;
}
