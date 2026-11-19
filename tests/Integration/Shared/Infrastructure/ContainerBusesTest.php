<?php

declare(strict_types=1);

use Core\Shared\Application\Bus\CommandBus;
use Core\Shared\Application\Bus\QueryBus;
use Core\Shared\Infrastructure\Bus\HandlerNotFound;
use Tests\Support\Bus\PingCommand;
use Tests\Support\Bus\UnhandledCommand;
use Tests\Support\Bus\WhoAmIQuery;

it('dispatches a command to a handler resolved from the container', function (): void {
    $result = $this->app->make(CommandBus::class)->dispatch(new PingCommand('hi'));

    expect($result)->toStartWith('pong:hi@');
});

it('asks a query to a handler resolved from the container', function (): void {
    expect($this->app->make(QueryBus::class)->ask(new WhoAmIQuery))->toBe('template');
});

it('throws when a command has no handler', function (): void {
    $this->app->make(CommandBus::class)->dispatch(new UnhandledCommand);
})->throws(HandlerNotFound::class);
