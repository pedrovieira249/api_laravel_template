<?php

declare(strict_types=1);

namespace Core\Shared\Application\Contract;

use Core\Shared\Domain\ValueObject\Uuid;

/**
 * Port de saída (driven): identificadores são gerados antes do INSERT, então a
 * entidade nasce completa e o banco nunca "preenche" o id depois.
 */
interface IdGenerator
{
    public function generate(): Uuid;
}
