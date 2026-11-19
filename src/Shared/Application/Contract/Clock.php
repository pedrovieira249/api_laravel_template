<?php

declare(strict_types=1);

namespace Core\Shared\Application\Contract;

/**
 * Port de saída (driven): "que horas são?". Handlers nunca chamam `new \DateTimeImmutable()`
 * diretamente, então os testes podem congelar o tempo (ver Tests\Support\Fakes\FrozenClock).
 */
interface Clock
{
    public function now(): \DateTimeImmutable;
}
