<?php

declare(strict_types=1);

namespace Tests\Support\Bus;

use Core\Shared\Application\Bus\Command;

/**
 * @implements Command<string>
 */
final readonly class PingCommand implements Command
{
    public function __construct(public string $payload) {}
}
