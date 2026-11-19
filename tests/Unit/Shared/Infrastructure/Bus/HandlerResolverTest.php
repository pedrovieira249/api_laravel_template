<?php

declare(strict_types=1);

use Core\Shared\Infrastructure\Bus\HandlerNotFound;
use Core\Shared\Infrastructure\Bus\HandlerResolver;
use Tests\Support\Bus\PingCommand;
use Tests\Support\Bus\PingHandler;
use Tests\Support\Bus\UnhandledCommand;
use Tests\Support\Bus\WhoAmIHandler;
use Tests\Support\Bus\WhoAmIQuery;

it('maps a command to its handler by naming convention', function (): void {
    expect(new HandlerResolver()->resolve(PingCommand::class))->toBe(PingHandler::class);
});

it('maps a query to its handler by naming convention', function (): void {
    expect(new HandlerResolver()->resolve(WhoAmIQuery::class))->toBe(WhoAmIHandler::class);
});

it('fails loudly when the handler does not exist', function (): void {
    new HandlerResolver()->resolve(UnhandledCommand::class);
})->throws(HandlerNotFound::class, 'UnhandledHandler');

it('fails loudly when the class does not follow the suffix convention', function (): void {
    new HandlerResolver()->resolve(HandlerResolver::class);
})->throws(HandlerNotFound::class);
