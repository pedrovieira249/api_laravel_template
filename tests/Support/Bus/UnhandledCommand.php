<?php

declare(strict_types=1);

namespace Tests\Support\Bus;

use Core\Shared\Application\Bus\Command;

/**
 * @implements Command<void>
 */
final readonly class UnhandledCommand implements Command {}
