<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Support\Number;

/**
 * Forma JSON compartilhada para dinheiro. Centavos inteiros são o contrato; "formatted"
 * é uma conveniência para humanos e o único lugar em que um float aparece.
 */
final readonly class MoneyResource
{
    /**
     * @return array{amount_cents: int, currency: string, formatted: string}
     */
    public static function make(int $cents, string $currency): array
    {
        return [
            'amount_cents' => $cents,
            'currency' => $currency,
            'formatted' => (string) Number::currency($cents / 100, in: $currency, locale: app()->getLocale()),
        ];
    }
}
