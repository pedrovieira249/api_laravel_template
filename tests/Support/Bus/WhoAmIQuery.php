<?php

declare(strict_types=1);

namespace Tests\Support\Bus;

use Core\Shared\Application\Bus\Query;

/**
 * @implements Query<string>
 */
final readonly class WhoAmIQuery implements Query {}
