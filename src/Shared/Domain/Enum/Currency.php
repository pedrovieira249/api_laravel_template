<?php

declare(strict_types=1);

namespace Core\Shared\Domain\Enum;

enum Currency: string
{
    case Brl = 'BRL';
    case Usd = 'USD';

    /**
     * Número de dígitos da unidade menor (centavos). Fica no enum para que a
     * formatação nunca fixe "2" no código: um futuro case JPY devolveria 0.
     */
    public function decimals(): int
    {
        return match ($this) {
            self::Brl, self::Usd => 2,
        };
    }
}
