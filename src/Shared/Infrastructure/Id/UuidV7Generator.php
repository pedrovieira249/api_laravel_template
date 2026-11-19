<?php

declare(strict_types=1);

namespace Core\Shared\Infrastructure\Id;

use Core\Shared\Application\Contract\IdGenerator;
use Core\Shared\Domain\ValueObject\Uuid;
use Illuminate\Support\Str;

/**
 * UUID v7 é ordenado por tempo: ids novos ordenam depois dos antigos, o que mantém
 * o índice primário clusterizado do InnoDB anexando no fim em vez de dividir páginas (ADR 0002).
 */
final class UuidV7Generator implements IdGenerator
{
    public function generate(): Uuid
    {
        return Uuid::fromString((string) Str::uuid7());
    }
}
