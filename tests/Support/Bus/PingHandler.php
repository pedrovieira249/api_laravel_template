<?php

declare(strict_types=1);

namespace Tests\Support\Bus;

use Core\Shared\Application\Contract\Clock;

final readonly class PingHandler
{
    public function __construct(private Clock $clock) {}

    public function __invoke(PingCommand $command): string
    {
        return sprintf('pong:%s@%s', $command->payload, $this->clock->now()->format('Y'));
    }
}
